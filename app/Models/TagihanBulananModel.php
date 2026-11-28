<?php

namespace App\Models;

use CodeIgniter\Model;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Model tabel `tagihan_bulanan`
 * Rincian tagihan listrik, air, sampah, dan pengunjung per bulan.
 *
 * Tagihan listrik_air (Kartu 9) HANYA dibuat lewat terbitkan(), supaya satu sewa tidak bisa
 * punya dua tagihan untuk bulan yang sama. Pengunci utamanya adalah UNIQUE (id_sewa, periode)
 * di database; pemeriksaan di PHP hanya untuk pesan yang jelas.
 * Semua nominal disimpan sebagai angka bulat rupiah. Aturan jatuh tempo ada di README bagian 13.
 */
class TagihanBulananModel extends Model
{
    protected $table            = 'tagihan_bulanan';
    protected $primaryKey       = 'id_tagih_bulanan';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id_sewa',
        'id_tagihan',
        'id_kunjungan',
        'periode',
        'tagihan_listrik',
        'tagihan_air',
        'tagihan_sampah',
        'tagihan_pengunjung',
    ];

    // Tabel tidak memiliki kolom created_at / updated_at
    protected $useTimestamps = false;

    /** Tagihan listrik dan air jatuh tempo pada tanggal ini di bulan setelah periode pemakaian. */
    public const TANGGAL_JATUH_TEMPO = 25;

    /** Bila tanggal itu sudah terlewat saat tagihan dibuat, jatuh tempo = hari ini + sekian hari. */
    public const HARI_MINIMAL = 3;

    // ------------------------------------------------------------------
    //  Aturan hitung (murni, tanpa database)
    // ------------------------------------------------------------------

    /**
     * Angka meteran dari form menjadi bilangan bulat "per seratus" (maksimal 2 angka desimal),
     * supaya hitungan tidak terkena kesalahan pembulatan angka pecahan.
     * "1250" -> 125000, "1250,5" -> 125050. Mengembalikan null bila formatnya salah.
     */
    public static function keSeratus(?string $teks): ?int
    {
        $teks = str_replace(',', '.', trim((string) $teks));
        if (! preg_match('/^\d{1,8}(\.\d{1,2})?$/', $teks)) {
            return null;
        }

        return (int) round(((float) $teks) * 100);
    }

    /** Biaya sampah dari form: bilangan bulat rupiah, nol boleh. null bila formatnya salah. */
    public static function sampahRupiah(?string $teks): ?int
    {
        $teks = str_replace(['.', ' '], '', trim((string) $teks));

        return preg_match('/^\d{1,9}$/', $teks) ? (int) $teks : null;
    }

    /**
     * Biaya pemakaian = (meter akhir - meter awal) x tarif, dibulatkan ke rupiah terdekat
     * (setengah ke atas). Contoh: 60 kWh x Rp1.500 = Rp90.000.
     *
     * @param int       $awal100  meter awal, bilangan "per seratus" (lihat keSeratus)
     * @param int       $akhir100 meter akhir, bilangan "per seratus"
     * @param float|int|string $tarif tarif per satuan (kolom DECIMAL(12,2) dari info_kos)
     *
     * @return array{pemakaian100: int, biaya: int}
     *
     * @throws InvalidArgumentException bila meter akhir lebih kecil daripada meter awal
     */
    public static function hitungPemakaian(int $awal100, int $akhir100, $tarif): array
    {
        if ($akhir100 < $awal100) {
            throw new InvalidArgumentException('Meter akhir tidak boleh lebih kecil daripada meter awal.');
        }

        $pemakaian100 = $akhir100 - $awal100;
        $tarif100     = (int) round(((float) $tarif) * 100);
        // (pemakaian100/100) x (tarif100/100) = pemakaian100 x tarif100 / 10000, dibulatkan setengah ke atas
        $biaya = intdiv($pemakaian100 * $tarif100 * 2 + 10000, 20000);

        return ['pemakaian100' => $pemakaian100, 'biaya' => $biaya];
    }

    /** Bilangan "per seratus" untuk tampilan: 6000 -> "60", 6050 -> "60,5", 6005 -> "60,05". */
    public static function tampilSatuan(int $seratus): string
    {
        $teks = number_format($seratus / 100, 2, ',', '.');

        return rtrim(rtrim($teks, '0'), ',');
    }

    /** Periode berformat YYYY-MM yang sah (bulan 01 sampai 12). */
    public static function periodeValid(?string $periode): bool
    {
        return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $periode);
    }

    /** Bulan sebelum $hariIni (Y-m-d), format YYYY-MM. Bawaan periode di form. */
    public static function periodeBulanLalu(string $hariIni): string
    {
        return (new DateTimeImmutable(substr($hariIni, 0, 7) . '-01'))->modify('-1 month')->format('Y-m');
    }

    /**
     * Jatuh tempo tagihan listrik dan air untuk satu periode pemakaian.
     *   dasar = tanggal 25 bulan setelah periode (contoh Okt 2026 -> 25 Nov 2026)
     *   paling cepat = hari ini + 3 hari, supaya penghuni tidak menerima tagihan yang sudah telat
     * Hasilnya yang lebih akhir dari keduanya.
     */
    public static function jatuhTempo(string $periode, string $hariIni): string
    {
        $bulanDepan = (new DateTimeImmutable($periode . '-01'))->modify('+1 month');
        $dasar      = $bulanDepan->format('Y-m-') . sprintf('%02d', self::TANGGAL_JATUH_TEMPO);
        $tercepat = (new DateTimeImmutable($hariIni))->modify('+' . self::HARI_MINIMAL . ' days')->format('Y-m-d');

        return max($dasar, $tercepat);
    }

    // ------------------------------------------------------------------
    //  Query halaman input bulanan
    // ------------------------------------------------------------------

    /**
     * Sewa aktif yang masa sewanya mencakup sebagian dari bulan $periode, lengkap dengan
     * rincian tagihan bulan itu bila sudah dibuat (kolom `id_tagih_bulanan` terisi).
     * `sampah_terakhir` = biaya sampah bulan terakhir yang pernah ditagihkan, dipakai sebagai isian awal form.
     */
    public function sewaUntukPeriode(string $periode): array
    {
        $awalBulan  = $periode . '-01';
        $akhirBulan = (new DateTimeImmutable($awalBulan))->format('Y-m-t');

        return $this->db->table('sewa s')
            ->select('s.id_sewa, s.tanggal_mulai, s.tanggal_berakhir, m.id_pengguna, p.nama AS nama_penghuni, '
                . 'k.nomor_kamar, tb.id_tagih_bulanan, tb.tagihan_listrik, tb.tagihan_air, tb.tagihan_sampah, '
                . 'tb.id_tagihan, '
                . '(SELECT x.tagihan_sampah FROM tagihan_bulanan x WHERE x.id_sewa = s.id_sewa '
                . 'ORDER BY x.periode DESC LIMIT 1) AS sampah_terakhir', false)
            ->join('pemesanan m', 'm.id_pemesanan = s.id_pemesanan')
            ->join('pengguna p', 'p.id_pengguna = m.id_pengguna')
            ->join('kamar k', 'k.id_kamar = m.id_kamar')
            ->join('tagihan_bulanan tb', 'tb.id_sewa = s.id_sewa AND tb.periode = ' . $this->db->escape($periode), 'left', false)
            ->where('s.status_sewa', 'aktif')
            ->where('s.tanggal_mulai <=', $akhirBulan)
            ->where('s.tanggal_berakhir >=', $awalBulan)
            ->orderBy('k.nomor_kamar', 'ASC')
            ->get()->getResultArray();
    }

    // ------------------------------------------------------------------
    //  Pembuat tagihan listrik_air
    // ------------------------------------------------------------------

    /**
     * Membuat tagihan jenis listrik_air beserta rinciannya (tagihan_bulanan) untuk satu sewa dan satu bulan.
     * Keduanya disimpan dalam satu transaksi: gagal salah satu, tidak ada yang tersimpan.
     *
     * Urutan: baris tagihan_bulanan dimasukkan DULU. Kunci UNIQUE (id_sewa, periode) langsung
     * menolak kalau bulan itu sudah ada (termasuk dua permintaan yang datang bersamaan).
     *
     * @return array{id_tagihan: int, jumlah_tagihan: int, tanggal_jatuh_tempo: string}
     *
     * @throws DomainException   aturan bisnis dilanggar (sewa tidak aktif, bulan sudah ditagih, total nol)
     * @throws RuntimeException  penyimpanan ke database gagal
     */
    public function terbitkan(int $idSewa, string $periode, int $listrik, int $air, int $sampah, ?string $hariIni = null): array
    {
        $hariIni ??= TagihanModel::hariIni();

        if (! self::periodeValid($periode)) {
            throw new DomainException('Periode harus berformat tahun-bulan, misalnya 2026-10.');
        }
        if ($listrik < 0 || $air < 0 || $sampah < 0) {
            throw new DomainException('Nominal tagihan tidak boleh negatif.');
        }
        $total = $listrik + $air + $sampah;
        if ($total <= 0) {
            throw new DomainException('Total tagihan nol, tidak ada yang perlu ditagihkan.');
        }

        $db = $this->db;

        $sewa = $this->sewaUntukPeriodeSatu($idSewa, $periode);
        if ($sewa === null) {
            throw new DomainException('Sewa tidak ditemukan, belum aktif, atau masa sewanya tidak mencakup bulan ini.');
        }
        if ($this->sudahAda($idSewa, $periode)) {
            throw new DomainException('Tagihan listrik dan air bulan ini untuk penghuni tersebut sudah dibuat.');
        }

        $jatuhTempo = self::jatuhTempo($periode, $hariIni);
        $db->transStart();

        try {
            $idBulanan = $this->insert([
                'id_sewa'            => $idSewa,
                'id_tagihan'         => null,
                'id_kunjungan'       => null,
                'periode'            => $periode,
                'tagihan_listrik'    => $listrik,
                'tagihan_air'        => $air,
                'tagihan_sampah'     => $sampah,
                'tagihan_pengunjung' => 0,
            ]);
            if ($idBulanan === false || (int) $idBulanan < 1) {
                throw new RuntimeException('Gagal menyimpan rincian tagihan bulanan.');
            }

            $tagihan  = model(TagihanModel::class);
            $idTagihan = $tagihan->insert([
                'id_sewa'             => $idSewa,
                'jenis_tagihan'       => 'listrik_air',
                'jumlah_tagihan'      => $total,
                'tanggal_jatuh_tempo' => $jatuhTempo,
                'status_tagihan'      => 'belum_bayar',
            ]);
            if ($idTagihan === false || (int) $idTagihan < 1) {
                throw new RuntimeException('Gagal menyimpan tagihan listrik dan air.');
            }

            $ok = $this->update((int) $idBulanan, ['id_tagihan' => (int) $idTagihan]);
            if ($ok === false) {
                throw new RuntimeException('Gagal menghubungkan rincian dengan tagihan.');
            }

            $db->transComplete();
            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi pembuatan tagihan dibatalkan.');
            }
        } catch (Throwable $e) {
            $db->transRollback();

            // Gagal karena bulan ini baru saja dibuat oleh permintaan lain: beri pesan yang jelas
            if ($this->sudahAda($idSewa, $periode)) {
                throw new DomainException('Tagihan listrik dan air bulan ini untuk penghuni tersebut sudah dibuat.');
            }

            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage(), 0, $e);
        }

        return [
            'id_tagihan'          => (int) $idTagihan,
            'jumlah_tagihan'      => $total,
            'tanggal_jatuh_tempo' => $jatuhTempo,
            'id_pengguna'         => (int) $sewa['id_pengguna'],
        ];
    }

    /** Apakah sewa ini sudah punya rincian tagihan bulanan untuk periode tersebut. */
    public function sudahAda(int $idSewa, string $periode): bool
    {
        return $this->db->table('tagihan_bulanan')
            ->where('id_sewa', $idSewa)
            ->where('periode', $periode)
            ->countAllResults() > 0;
    }

    /** Sewa aktif yang masa sewanya mencakup bulan itu, dengan id penghuninya. Null bila tidak memenuhi. */
    private function sewaUntukPeriodeSatu(int $idSewa, string $periode): ?array
    {
        $awalBulan  = $periode . '-01';
        $akhirBulan = (new DateTimeImmutable($awalBulan))->format('Y-m-t');

        $baris = $this->db->table('sewa s')
            ->select('s.id_sewa, m.id_pengguna')
            ->join('pemesanan m', 'm.id_pemesanan = s.id_pemesanan')
            ->where('s.id_sewa', $idSewa)
            ->where('s.status_sewa', 'aktif')
            ->where('s.tanggal_mulai <=', $akhirBulan)
            ->where('s.tanggal_berakhir >=', $awalBulan)
            ->get()->getRowArray();

        return $baris ?: null;
    }
}
