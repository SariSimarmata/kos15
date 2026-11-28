<?php

namespace App\Libraries;

use App\Models\InfoKosModel;
use App\Models\NotifikasiModel;
use App\Models\PenggunaModel;
use App\Models\TagihanModel;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Satu pintu untuk membuat notifikasi (kartu Notifikasi dan Pengingat Harian).
 *
 * 1. Pengingat tagihan H-7, H-3, H-1  -> pengingatTagihan(), dipakai `php spark pengingat:kirim`
 * 2. Notifikasi untuk pemilik         -> pemesananBaru(), pembayaranTunai(), perpanjanganBaru(), laporanKerusakan()
 * 3. Halaman tujuan saat diklik       -> tujuan()
 *
 * Contoh pemakaian dari kartu lain:
 *   (new NotifikasiService())->laporanKerusakan($idLaporan, session('nama'), 'K-02', 'Lampu kamar mati');
 */
class NotifikasiService
{
    public const ZONA_WAKTU = 'Asia/Jakarta';

    /** Dipakai jika info_kos.jadwal_pengingat kosong atau tidak valid. */
    public const JADWAL_BAWAAN = [7, 3, 1];

    private NotifikasiModel $notifikasi;

    public function __construct()
    {
        $this->notifikasi = model(NotifikasiModel::class);
    }

    // ------------------------------------------------------------------
    //  1. Pengingat tagihan harian
    // ------------------------------------------------------------------

    /**
     * Hari pengingat dari info_kos.jadwal_pengingat, mis. "7,3,1" -> [7, 3, 1].
     *
     * @return list<int>
     */
    public function jadwalPengingat(): array
    {
        $info  = model(InfoKosModel::class)->first();
        $hari  = array_map('intval', explode(',', (string) ($info['jadwal_pengingat'] ?? '')));
        $hari  = array_values(array_unique(array_filter($hari, static fn (int $h) => $h > 0 && $h <= 60)));
        rsort($hari);

        return $hari !== [] ? $hari : self::JADWAL_BAWAAN;
    }

    /**
     * Buat notifikasi pengingat untuk semua tagihan BELUM BAYAR yang jatuh tempo
     * tepat H-n dari $tanggal (n dari jadwal_pengingat).
     * Tagihan lunas tidak pernah diambil, jadi pengingat otomatis berhenti setelah lunas.
     *
     * @param bool $cobaSaja true = hanya menghitung, tidak menyimpan apa pun
     *
     * @return array{tanggal: string, jadwal: list<int>, dikirim: list<array>, dilewati: list<array>}
     */
    public function pengingatTagihan(DateTimeImmutable $tanggal, bool $cobaSaja = false): array
    {
        helper(['tanggal', 'kos']);

        $tanggal = $tanggal->setTimezone(new DateTimeZone(self::ZONA_WAKTU))->setTime(0, 0);
        $jadwal  = $this->jadwalPengingat();
        $hasil   = ['tanggal' => $tanggal->format('Y-m-d'), 'jadwal' => $jadwal, 'dikirim' => [], 'dilewati' => []];

        // Waktu kirim = tanggal (asli atau uji) + jam sekarang, supaya "notifikasi hari itu" terbaca benar
        $jamSekarang = (new DateTimeImmutable('now', new DateTimeZone(self::ZONA_WAKTU)))->format('H:i:s');
        $waktuKirim  = $tanggal->format('Y-m-d') . ' ' . $jamSekarang;

        foreach ($jadwal as $h) {
            $jatuhTempo = $tanggal->modify('+' . $h . ' days')->format('Y-m-d');

            foreach ($this->tagihanBelumBayar($jatuhTempo) as $t) {
                $baris = [
                    'h'          => $h,
                    'id_tagihan' => (int) $t['id_tagihan'],
                    'penghuni'   => $t['nama'],
                    'jenis'      => TagihanModel::LABEL_JENIS[$t['jenis_tagihan']] ?? $t['jenis_tagihan'],
                    'nominal'    => rupiah($t['jumlah_tagihan']),
                    'jatuh_tempo' => $jatuhTempo,
                ];

                if ($this->pengingatSudahAda((int) $t['id_pengguna'], (int) $t['id_tagihan'], $h, $tanggal)) {
                    $hasil['dilewati'][] = $baris;

                    continue;
                }

                if (! $cobaSaja) {
                    $this->notifikasi->kirim(
                        (int) $t['id_pengguna'],
                        'tagihan',
                        $this->pesanPengingat($t, $h),
                        (int) $t['id_tagihan'],
                        null,
                        $waktuKirim
                    );
                }
                $hasil['dikirim'][] = $baris;
            }
        }

        return $hasil;
    }

    /** "Pengingat: tagihan Listrik & Air Rp127.500 jatuh tempo 25 Nov 2026 (H-7). ..." */
    public function pesanPengingat(array $tagihan, int $h): string
    {
        helper(['tanggal', 'kos']);
        $jenis = TagihanModel::LABEL_JENIS[$tagihan['jenis_tagihan']] ?? $tagihan['jenis_tagihan'];

        return 'Pengingat: tagihan ' . $jenis . ' ' . rupiah($tagihan['jumlah_tagihan'])
            . ' jatuh tempo ' . tanggal_indonesia($tagihan['tanggal_jatuh_tempo']) . ' (H-' . $h . ').'
            . ($h === 1 ? ' Besok terakhir, segera bayar.' : ' Bayar lewat QRIS atau tunai ke pemilik.');
    }

    /** Tagihan belum bayar milik penghuni yang jatuh tempo pada tanggal ini. */
    private function tagihanBelumBayar(string $jatuhTempo): array
    {
        return db_connect()->table('tagihan t')
            ->select('t.id_tagihan, t.jenis_tagihan, t.jumlah_tagihan, t.tanggal_jatuh_tempo, p.id_pengguna, g.nama')
            ->join('sewa s', 's.id_sewa = t.id_sewa')
            ->join('pemesanan p', 'p.id_pemesanan = s.id_pemesanan')
            ->join('pengguna g', 'g.id_pengguna = p.id_pengguna')
            ->where('t.status_tagihan', 'belum_bayar')
            ->where('t.tanggal_jatuh_tempo', $jatuhTempo)
            ->whereIn('s.status_sewa', ['menunggu_pembayaran', 'aktif'])
            ->orderBy('t.id_tagihan', 'ASC')
            ->get()->getResultArray();
    }

    /**
     * Anti ganda: lewati jika untuk tagihan ini sudah ada
     *  - pengingat dengan tanda "(H-n)" yang sama, ATAU
     *  - notifikasi tagihan lain yang dikirim pada tanggal yang sama.
     * Jadi menjalankan perintah dua kali dalam sehari tidak membuat notifikasi baru.
     */
    private function pengingatSudahAda(int $idPengguna, int $idTagihan, int $h, DateTimeImmutable $tanggal): bool
    {
        return $this->notifikasi->builder()
            ->where('id_pengguna', $idPengguna)
            ->where('id_tagihan', $idTagihan)
            ->where('jenis_notifikasi', 'tagihan')
            ->groupStart()
                ->like('pesan', '(H-' . $h . ')', 'both', null, true)
                ->orWhere('waktu_kirim >=', $tanggal->format('Y-m-d 00:00:00'))
                ->where('waktu_kirim <=', $tanggal->format('Y-m-d 23:59:59'))
            ->groupEnd()
            ->countAllResults() > 0;
    }

    // ------------------------------------------------------------------
    //  2. Notifikasi untuk pemilik
    // ------------------------------------------------------------------

    /**
     * Kirim ke semua akun pemilik. Mengembalikan jumlah notifikasi yang dibuat.
     */
    public function keSemuaPemilik(string $jenis, string $pesan, ?int $idTagihan = null, ?int $idLaporan = null): int
    {
        $jumlah = 0;
        foreach (model(PenggunaModel::class)->where('role', 'pemilik')->findAll() as $pemilik) {
            $this->notifikasi->kirim((int) $pemilik['id_pengguna'], $jenis, $pesan, $idTagihan, $idLaporan);
            $jumlah++;
        }

        return $jumlah;
    }

    /** Pemesanan kamar baru dari calon penghuni (Kartu 8). */
    public function pemesananBaru(string $nomorPemesanan, string $namaCalon, string $nomorKamar, string $tanggalMulai): int
    {
        helper('tanggal');

        return $this->keSemuaPemilik('pemesanan', 'Pemesanan baru ' . $nomorPemesanan . ' dari ' . $namaCalon
            . ' untuk kamar ' . $nomorKamar . ', mulai sewa ' . tanggal_indonesia($tanggalMulai) . '.');
    }

    /** Penghuni melapor sudah membayar tunai; pemilik perlu mengonfirmasi. */
    public function pembayaranTunai(int $idTagihan, string $namaPenghuni, string $nomorKamar, float $nominal): int
    {
        helper('kos');

        return $this->keSemuaPemilik('pembayaran', $namaPenghuni . ' (Kamar ' . $nomorKamar . ') melapor pembayaran tunai '
            . rupiah($nominal) . '. Mohon konfirmasi pembayaran tunai.', $idTagihan);
    }

    /** Penghuni mengajukan perpanjangan sewa. */
    public function perpanjanganBaru(string $namaPenghuni, string $nomorKamar, string $tanggalPerpanjangan): int
    {
        helper('tanggal');

        return $this->keSemuaPemilik('perpanjangan', $namaPenghuni . ' mengajukan perpanjangan sewa Kamar ' . $nomorKamar
            . ' mulai ' . tanggal_indonesia($tanggalPerpanjangan) . '.');
    }

    /** Penghuni melaporkan kerusakan. */
    public function laporanKerusakan(int $idLaporan, string $namaPenghuni, string $nomorKamar, string $ringkasan): int
    {
        $ringkasan = mb_strimwidth(trim($ringkasan), 0, 80, '...');

        return $this->keSemuaPemilik('aduan', 'Laporan kerusakan baru dari ' . $namaPenghuni . ' (Kamar ' . $nomorKamar
            . '): ' . $ringkasan, null, $idLaporan);
    }

    // ------------------------------------------------------------------
    //  3. Halaman tujuan saat notifikasi diklik
    // ------------------------------------------------------------------

    /**
     * Alamat halaman yang terkait dengan notifikasi, sesuai peran pembacanya.
     * Jika halaman itu belum dibuat oleh kartunya, kembali ke daftar notifikasi.
     */
    public function tujuan(array $notif, string $peran): string
    {
        $idTagihan = $notif['id_tagihan'] !== null ? (int) $notif['id_tagihan'] : null;
        $jenis     = (string) $notif['jenis_notifikasi'];

        $calon = match ($peran) {
            'pemilik' => match ($jenis) {
                'pemesanan'    => ['pemilik/pemesanan'],
                'pembayaran'   => str_contains(mb_strtolower((string) $notif['pesan']), 'tunai')
                    ? ['pemilik/konfirmasi-tunai', 'pemilik/pembayaran']
                    : ['pemilik/pembayaran', 'pemilik/tagihan'],
                'perpanjangan' => ['pemilik/perpanjangan', 'pemilik/penghuni'],
                'aduan'        => ['pemilik/kerusakan'],
                'piket'        => ['pemilik/piket'],
                default        => ['pemilik/tagihan'],      // tagihan, denda, meteran
            },
            'penghuni' => match ($jenis) {
                'tagihan', 'denda', 'pembayaran', 'meteran' => $idTagihan !== null
                    ? ['penghuni/tagihan/' . $idTagihan . '/bayar', 'penghuni/tagihan']
                    : ['penghuni/tagihan'],
                'aduan'        => ['penghuni/kerusakan'],
                'piket'        => ['penghuni/piket'],
                'perpanjangan' => ['penghuni/sewa'],
                default        => ['penghuni/tagihan'],      // pemesanan disetujui -> lanjut bayar
            },
            'calon_penghuni' => ['pemesanan/status', 'katalog'],
            default          => [],
        };

        foreach ($calon as $uri) {
            if ($this->adaHalaman($uri)) {
                return $uri;
            }
        }

        return 'notifikasi';
    }

    /** true jika ada route GET yang cocok dengan alamat ini. */
    private function adaHalaman(string $uri): bool
    {
        foreach (array_keys(service('routes')->getRoutes('GET')) as $pola) {
            $pola = trim((string) $pola, '/');
            if ($pola === $uri || @preg_match('#^' . $pola . '$#u', $uri) === 1) {
                return true;
            }
        }

        return false;
    }
}
