<?php

namespace App\Models;

use CodeIgniter\Model;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use InvalidArgumentException;
use RuntimeException;

/**
 * Model tabel `tagihan`
 * Semua jenis tagihan (DP, pelunasan, listrik_air, denda_piket, menginap_pengunjung).
 *
 * Tagihan sewa (dp / pelunasan) HANYA dibuat lewat buatTagihanSewa(), supaya pemesanan baru
 * (Kartu 3), perpanjangan sewa, dan kartu lain yang butuh tagihan sewa memakai aturan yang sama.
 * Semua nominal disimpan sebagai angka bulat rupiah. Aturan jatuh tempo ada di README bagian 9.
 */
class TagihanModel extends Model
{
    protected $table            = 'tagihan';
    protected $primaryKey       = 'id_tagihan';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id_sewa',
        'jenis_tagihan',
        'jumlah_tagihan',
        'tanggal_jatuh_tempo',
        'status_tagihan',
    ];

    // Tabel tidak memiliki kolom created_at / updated_at
    protected $useTimestamps = false;

    /** Nilai ENUM jenis_tagihan beserta labelnya. */
    public const JENIS = [
        'dp'                  => 'DP sewa',
        'pelunasan'           => 'Pelunasan sewa',
        'listrik_air'         => 'Listrik & air',
        'denda_piket'         => 'Denda piket',
        'menginap_pengunjung' => 'Menginap pengunjung',
    ];

    /** Jenis tagihan sewa: hanya dua ini yang dibuat oleh buatTagihanSewa(). */
    public const JENIS_SEWA = ['dp', 'pelunasan'];

    /** Skema pembayaran sewa yang boleh dipilih penghuni. */
    public const SKEMA = [
        'dp'    => 'DP 50% lalu pelunasan 50%',
        'lunas' => 'Lunas 100%',
    ];

    /** Besar DP dalam persen dari harga sewa tahunan. */
    public const PERSEN_DP = 50;

    /** DP (atau pelunasan penuh) jatuh tempo sekian hari setelah skema dipilih. */
    public const HARI_JATUH_TEMPO_DP = 3;

    private const ZONA_WAKTU = 'Asia/Jakarta';

    /** Filter halaman pemilik. */
    public const FILTER = [
        'semua'             => 'Semua',
        'belum_lunas'       => 'Belum lunas',
        'lewat_jatuh_tempo' => 'Lewat jatuh tempo',
    ];

    // ------------------------------------------------------------------
    //  Aturan skema sewa (murni, tanpa database)
    // ------------------------------------------------------------------

    /** Hari ini menurut waktu Indonesia barat (server memakai UTC), format Y-m-d. */
    public static function hariIni(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone(self::ZONA_WAKTU)))->format('Y-m-d');
    }

    /** Harga dari kolom DECIMAL dijadikan rupiah bulat. */
    public static function keRupiahBulat($nilai): int
    {
        return (int) round((float) $nilai);
    }

    /**
     * Rencana tagihan sewa untuk satu skema. Hanya menghitung, tidak menyimpan.
     *
     * Aturan nominal (angka bulat, jumlah semua tagihan selalu sama dengan harga sewa):
     *   dp    -> DP = harga / 2 dibulatkan ke bawah, pelunasan = sisanya
     *   lunas -> satu tagihan pelunasan sebesar harga penuh
     *
     * Aturan jatuh tempo (SRS: pelunasan dibayar sebelum tanggal mulai sewa):
     *   batas = tanggal mulai dikurangi 1 hari (H-1)
     *   DP, atau pelunasan penuh pada skema lunas = hari ini + 3 hari, tetapi tidak melewati batas
     *   pelunasan pada skema dp = batas
     *   tidak ada jatuh tempo yang sebelum hari ini (kalau batas sudah lewat, jatuh tempo = hari ini)
     *
     * @return list<array{jenis_tagihan: string, jumlah_tagihan: int, tanggal_jatuh_tempo: string}>
     */
    public static function rencanaTagihanSewa(int $hargaSewa, string $skema, string $tanggalMulai, string $hariIni): array
    {
        if ($hargaSewa <= 0) {
            throw new InvalidArgumentException('Harga sewa harus lebih dari nol.');
        }
        if (! array_key_exists($skema, self::SKEMA)) {
            throw new InvalidArgumentException('Skema pembayaran tidak dikenal: ' . $skema);
        }

        $batas      = (new DateTimeImmutable($tanggalMulai))->modify('-1 day')->format('Y-m-d');
        $awal       = (new DateTimeImmutable($hariIni))->modify('+' . self::HARI_JATUH_TEMPO_DP . ' days')->format('Y-m-d');
        $tempoAwal  = max(min($awal, $batas), $hariIni);
        $tempoAkhir = max($batas, $hariIni);

        if ($skema === 'lunas') {
            return [[
                'jenis_tagihan'       => 'pelunasan',
                'jumlah_tagihan'      => $hargaSewa,
                'tanggal_jatuh_tempo' => $tempoAwal,
            ]];
        }

        $dp = intdiv($hargaSewa * self::PERSEN_DP, 100);

        return [
            [
                'jenis_tagihan'       => 'dp',
                'jumlah_tagihan'      => $dp,
                'tanggal_jatuh_tempo' => $tempoAwal,
            ],
            [
                'jenis_tagihan'       => 'pelunasan',
                'jumlah_tagihan'      => $hargaSewa - $dp,
                'tanggal_jatuh_tempo' => $tempoAkhir,
            ],
        ];
    }

    /**
     * Label dan warna status untuk tampilan. Lewat jatuh tempo = belum dibayar dan
     * tanggal jatuh tempo sebelum hari ini (di hari jatuh tempo sendiri masih belum terlambat).
     *
     * @return array{0: string, 1: string} [kelas lencana, label]
     */
    public static function statusTampil(array $tagihan, string $hariIni): array
    {
        if ($tagihan['status_tagihan'] === 'lunas') {
            return ['lencana-hijau', 'Lunas'];
        }

        if ($tagihan['tanggal_jatuh_tempo'] < $hariIni) {
            $hari = (new DateTimeImmutable($tagihan['tanggal_jatuh_tempo']))->diff(new DateTimeImmutable($hariIni))->days;

            return ['lencana-merah', 'Lewat jatuh tempo ' . $hari . ' hari'];
        }

        return ['lencana-kuning', 'Belum dibayar'];
    }

    // ------------------------------------------------------------------
    //  Pembuat tagihan sewa (dipakai Kartu 3, 5, 6, 11)
    // ------------------------------------------------------------------

    /**
     * Data sewa yang dibutuhkan untuk menghitung tagihan: tanggal mulai, status, harga kamar.
     * Harga selalu diambil dari kamar milik pemesanan sewa itu, bukan dari input pengguna.
     */
    public function dataSewaUntukTagihan(int $idSewa): ?array
    {
        $baris = $this->db->table('sewa s')
            ->select('s.id_sewa, s.tanggal_mulai, s.tanggal_berakhir, s.status_sewa, '
                . 'm.id_pengguna, k.nomor_kamar, k.harga_sewa_tahunan')
            ->join('pemesanan m', 'm.id_pemesanan = s.id_pemesanan')
            ->join('kamar k', 'k.id_kamar = m.id_kamar')
            ->where('s.id_sewa', $idSewa)
            ->get()->getRowArray();

        return $baris ?: null;
    }

    /** Rencana tagihan untuk satu sewa dan skema (tanpa menyimpan), dipakai untuk pratinjau di halaman. */
    public function rencanaUntukSewa(int $idSewa, string $skema, ?string $hariIni = null): array
    {
        $sewa = $this->dataSewaUntukTagihan($idSewa);
        if ($sewa === null) {
            throw new DomainException('Sewa tidak ditemukan.');
        }

        return self::rencanaTagihanSewa(
            self::keRupiahBulat($sewa['harga_sewa_tahunan']),
            $skema,
            $sewa['tanggal_mulai'],
            $hariIni ?? self::hariIni()
        );
    }

    /**
     * Membuat tagihan sewa (dp + pelunasan, atau pelunasan penuh) untuk satu sewa.
     * Satu sewa hanya boleh punya satu set tagihan sewa; pemanggilan kedua ditolak.
     *
     * Aman dipanggil di dalam transaksi pemanggil. Kalau gagal, melempar exception dan
     * pemanggil yang memanggil transRollback() (pola yang sama dengan Pemesanan::setujui).
     *
     * @return list<array{id_tagihan: int, jenis_tagihan: string, jumlah_tagihan: int, tanggal_jatuh_tempo: string}>
     *
     * @throws DomainException   aturan bisnis dilanggar (sewa tidak ada, bukan menunggu pembayaran, tagihan sudah ada)
     * @throws RuntimeException  penyimpanan ke database gagal
     */
    public function buatTagihanSewa(int $idSewa, string $skema, ?string $hariIni = null): array
    {
        $hariIni ??= self::hariIni();

        $db = $this->db;
        $db->transStart();

        try {
            // Kunci baris sewa: dua permintaan bersamaan untuk sewa yang sama dibuat antre
            $db->query('SELECT id_sewa FROM sewa WHERE id_sewa = ? FOR UPDATE', [$idSewa]);

            $sewa = $this->dataSewaUntukTagihan($idSewa);
            if ($sewa === null) {
                throw new DomainException('Sewa tidak ditemukan.');
            }
            if ($sewa['status_sewa'] !== 'menunggu_pembayaran') {
                throw new DomainException('Tagihan sewa hanya bisa dibuat untuk sewa yang menunggu pembayaran.');
            }

            $sudahAda = $db->table('tagihan')
                ->where('id_sewa', $idSewa)
                ->whereIn('jenis_tagihan', self::JENIS_SEWA)
                ->countAllResults();
            if ($sudahAda > 0) {
                throw new DomainException('Tagihan sewa untuk sewa ini sudah dibuat.');
            }

            $rencana = self::rencanaTagihanSewa(
                self::keRupiahBulat($sewa['harga_sewa_tahunan']),
                $skema,
                $sewa['tanggal_mulai'],
                $hariIni
            );

            $hasil = [];
            foreach ($rencana as $baris) {
                $id = $this->insert($baris + ['id_sewa' => $idSewa, 'status_tagihan' => 'belum_bayar']);
                if ($id === false || (int) $id < 1) {
                    throw new RuntimeException('Gagal menyimpan tagihan ' . $baris['jenis_tagihan']);
                }
                $hasil[] = ['id_tagihan' => (int) $id] + $baris;
            }

            $db->transComplete();
            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi pembuatan tagihan dibatalkan.');
            }

            return $hasil;
        } catch (\Throwable $e) {
            $db->transRollback();

            throw $e;
        }
    }

    // ------------------------------------------------------------------
    //  Query halaman tagihan
    // ------------------------------------------------------------------

    /**
     * Sewa milik pengguna yang menunggu pembayaran tetapi belum punya tagihan sewa,
     * artinya penghuni belum memilih skema.
     */
    public function sewaBelumPilihSkema(int $idPengguna): array
    {
        return $this->db->table('sewa s')
            ->select('s.id_sewa, s.tanggal_mulai, s.tanggal_berakhir, k.nomor_kamar, k.harga_sewa_tahunan')
            ->join('pemesanan m', 'm.id_pemesanan = s.id_pemesanan')
            ->join('kamar k', 'k.id_kamar = m.id_kamar')
            ->where('m.id_pengguna', $idPengguna)
            ->where('s.status_sewa', 'menunggu_pembayaran')
            ->where("NOT EXISTS (SELECT 1 FROM tagihan t WHERE t.id_sewa = s.id_sewa AND t.jenis_tagihan IN ('dp','pelunasan'))", null, false)
            ->orderBy('s.tanggal_mulai', 'ASC')
            ->get()->getResultArray();
    }

    /**
     * Semua tagihan milik satu pengguna, lengkap dengan rincian tagihan bulanan (listrik, air,
     * sampah, pengunjung). Hanya tagihan dari sewa milik id ini yang ikut, sehingga penghuni
     * tidak bisa melihat tagihan orang lain. Yang belum dibayar di atas, jatuh tempo paling dekat dulu.
     */
    public function milikPengguna(int $idPengguna): array
    {
        return $this->db->table('tagihan t')
            ->select('t.*, k.nomor_kamar, tb.periode, tb.tagihan_listrik, tb.tagihan_air, '
                . 'tb.tagihan_sampah, tb.tagihan_pengunjung')
            ->join('sewa s', 's.id_sewa = t.id_sewa')
            ->join('pemesanan m', 'm.id_pemesanan = s.id_pemesanan')
            ->join('kamar k', 'k.id_kamar = m.id_kamar')
            ->join('tagihan_bulanan tb', 'tb.id_tagihan = t.id_tagihan', 'left')
            ->where('m.id_pengguna', $idPengguna)
            ->orderBy("CASE WHEN t.status_tagihan = 'belum_bayar' THEN 0 ELSE 1 END", 'ASC', false)
            ->orderBy("CASE WHEN t.status_tagihan = 'belum_bayar' THEN t.tanggal_jatuh_tempo END", 'ASC', false)
            ->orderBy('t.tanggal_jatuh_tempo', 'DESC')
            ->orderBy('t.id_tagihan', 'DESC')
            ->get()->getResultArray();
    }

    /**
     * Seluruh tagihan untuk halaman pemilik, dengan nama penghuni dan nomor kamar.
     * $filter: semua | belum_lunas | lewat_jatuh_tempo (nilai lain dianggap semua).
     */
    public function daftarPemilik(string $filter, string $hariIni): array
    {
        $builder = $this->db->table('tagihan t')
            ->select('t.*, p.nama AS nama_penghuni, p.username, k.nomor_kamar')
            ->join('sewa s', 's.id_sewa = t.id_sewa')
            ->join('pemesanan m', 'm.id_pemesanan = s.id_pemesanan')
            ->join('pengguna p', 'p.id_pengguna = m.id_pengguna')
            ->join('kamar k', 'k.id_kamar = m.id_kamar');

        if ($filter === 'belum_lunas') {
            $builder->where('t.status_tagihan', 'belum_bayar');
        } elseif ($filter === 'lewat_jatuh_tempo') {
            $builder->where('t.status_tagihan', 'belum_bayar')
                ->where('t.tanggal_jatuh_tempo <', $hariIni);
        }

        return $builder
            ->orderBy("CASE WHEN t.status_tagihan = 'belum_bayar' THEN 0 ELSE 1 END", 'ASC', false)
            ->orderBy("CASE WHEN t.status_tagihan = 'belum_bayar' THEN t.tanggal_jatuh_tempo END", 'ASC', false)
            ->orderBy('t.tanggal_jatuh_tempo', 'DESC')
            ->orderBy('t.id_tagihan', 'DESC')
            ->get()->getResultArray();
    }

    /**
     * Jumlah dan total rupiah tagihan: seluruhnya, belum lunas, dan lewat jatuh tempo.
     * Tanpa $idPengguna dihitung untuk semua penghuni (pemilik), dengan $idPengguna hanya miliknya.
     *
     * @return array{semua: array{jumlah: int, total: int}, belum_lunas: array{jumlah: int, total: int}, lewat_jatuh_tempo: array{jumlah: int, total: int}}
     */
    public function ringkasan(string $hariIni, ?int $idPengguna = null): array
    {
        $h = $this->db->escape($hariIni);

        $builder = $this->db->table('tagihan t')
            ->select('COUNT(*) AS semua_jumlah, COALESCE(SUM(t.jumlah_tagihan), 0) AS semua_total, '
                . "COALESCE(SUM(t.status_tagihan = 'belum_bayar'), 0) AS belum_jumlah, "
                . "COALESCE(SUM(CASE WHEN t.status_tagihan = 'belum_bayar' THEN t.jumlah_tagihan ELSE 0 END), 0) AS belum_total, "
                . "COALESCE(SUM(t.status_tagihan = 'belum_bayar' AND t.tanggal_jatuh_tempo < {$h}), 0) AS lewat_jumlah, "
                . "COALESCE(SUM(CASE WHEN t.status_tagihan = 'belum_bayar' AND t.tanggal_jatuh_tempo < {$h} THEN t.jumlah_tagihan ELSE 0 END), 0) AS lewat_total", false);

        if ($idPengguna !== null) {
            $builder->join('sewa s', 's.id_sewa = t.id_sewa')
                ->join('pemesanan m', 'm.id_pemesanan = s.id_pemesanan')
                ->where('m.id_pengguna', $idPengguna);
        }

        $r = $builder->get()->getRowArray();

        return [
            'semua'             => ['jumlah' => (int) $r['semua_jumlah'], 'total' => (int) round((float) $r['semua_total'])],
            'belum_lunas'       => ['jumlah' => (int) $r['belum_jumlah'], 'total' => (int) round((float) $r['belum_total'])],
            'lewat_jatuh_tempo' => ['jumlah' => (int) $r['lewat_jumlah'], 'total' => (int) round((float) $r['lewat_total'])],
        ];
    }
}
