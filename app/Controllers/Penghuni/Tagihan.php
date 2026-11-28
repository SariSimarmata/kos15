<?php

namespace App\Controllers\Penghuni;

use App\Controllers\BaseController;
use App\Libraries\QrisGateway;
use App\Libraries\TagihanService;
use App\Models\PembayaranModel;
use App\Models\TagihanModel;
use CodeIgniter\HTTP\ResponseInterface;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Halaman penghuni untuk kartu tagihan & pembayaran (menu di partials/sidebar_penghuni.php dan Config/Menu.php):
 *
 *   GET penghuni/tagihan                     Tagihan Saya (menu "Tagihan")
 *   GET penghuni/bayar                       tombol "Bayar Tagihan": buka tagihan belum bayar paling dekat jatuh tempo
 *   GET penghuni/riwayat                     Riwayat Bayar (menu "Riwayat Bayar"), ?cari=&status=
 *   GET penghuni/riwayat/unduh               Unduh Rekap (CSV)
 *   GET penghuni/pembayaran/{id}/kwitansi    Kwitansi pembayaran berhasil (siap cetak)
 *
 * Login dan peran penghuni dijaga filter 'peran:penghuni' di Routes.php.
 */
class Tagihan extends BaseController
{
    protected $helpers = ['tanggal', 'kos'];

    public function index()
    {
        $idPengguna = (int) session('id_pengguna');

        // Penghuni lama yang disetujui sebelum fitur ini ada: buatkan tagihan DP + pelunasan sekali saja
        (new TagihanService())->lengkapiUntukPenghuni($idPengguna);
        model(PembayaranModel::class)->kedaluwarsakanMilikPenghuni($idPengguna, $this->sekarang());

        $tagihan = model(TagihanModel::class)->daftarPenghuni($idPengguna, $this->sekarang());

        $hariIni = substr($this->sekarang(), 0, 10);
        $total   = ['belum' => 0.0, 'jumlahBelum' => 0, 'terlambat' => 0, 'lunas' => 0.0, 'jumlahLunas' => 0, 'terdekat' => null];
        foreach ($tagihan as $t) {
            if ($t['status_tagihan'] === 'lunas') {
                $total['lunas'] += (float) $t['jumlah_tagihan'];
                $total['jumlahLunas']++;

                continue;
            }
            $total['belum'] += (float) $t['jumlah_tagihan'];
            $total['jumlahBelum']++;
            if ($t['tanggal_jatuh_tempo'] < $hariIni) {
                $total['terlambat']++;
            }
            if ($total['terdekat'] === null) {
                $total['terdekat'] = $t; // daftar sudah urut: belum bayar, jatuh tempo terdekat dulu
            }
        }

        return view('penghuni/tagihan', [
            'judul'   => 'Tagihan Saya',
            'tagihan' => $tagihan,
            'total'   => $total,
            'hariIni' => $hariIni,
        ]);
    }

    /** Tombol "Bayar Tagihan" di header: langsung ke tagihan belum bayar yang paling dekat jatuh tempo. */
    public function bayar()
    {
        $idPengguna = (int) session('id_pengguna');
        (new TagihanService())->lengkapiUntukPenghuni($idPengguna);

        foreach (model(TagihanModel::class)->daftarPenghuni($idPengguna, $this->sekarang()) as $t) {
            if ($t['status_tagihan'] === 'belum_bayar') {
                return redirect()->to('penghuni/tagihan/' . $t['id_tagihan'] . '/bayar');
            }
        }

        return redirect()->to('penghuni/tagihan')->with('sukses', 'Semua tagihan sudah lunas. Tidak ada yang perlu dibayar.');
    }

    public function riwayat()
    {
        $cari   = trim((string) $this->request->getGet('cari'));
        $status = (string) $this->request->getGet('status');
        model(PembayaranModel::class)->kedaluwarsakanMilikPenghuni((int) session('id_pengguna'), $this->sekarang());

        return view('penghuni/riwayat', [
            'judul'   => 'Riwayat Pembayaran',
            'riwayat' => model(PembayaranModel::class)->riwayatPenghuni((int) session('id_pengguna'), $cari, $status),
            'cari'    => $cari,
            'status'  => $status,
        ]);
    }

    /** Unduh Rekap: semua pembayaran berhasil milik penghuni ini dalam CSV (bisa dibuka di Excel). */
    public function unduhRekap(): ResponseInterface
    {
        $baris = model(PembayaranModel::class)->riwayatPenghuni((int) session('id_pengguna'), null, 'berhasil');

        $isi = fopen('php://temp', 'r+');
        fwrite($isi, "\xEF\xBB\xBF"); // BOM supaya Excel membaca UTF-8
        fputcsv($isi, ['Tanggal Bayar', 'Tagihan', 'Kamar', 'Metode', 'Nomor Transaksi', 'Jumlah'], ';');
        foreach ($baris as $b) {
            fputcsv($isi, [
                $b['tanggal_bayar'],
                TagihanModel::LABEL_JENIS[$b['jenis_tagihan']] ?? $b['jenis_tagihan'],
                $b['nomor_kamar'],
                strtoupper($b['metode']),
                $b['order_id'] ?? '-',
                (int) round((float) $b['jumlah_bayar']),
            ], ';');
        }
        rewind($isi);
        $csv = stream_get_contents($isi);
        fclose($isi);

        return $this->response->download('rekap-pembayaran-' . date('Ymd') . '.csv', $csv)
            ->setContentType('text/csv; charset=UTF-8');
    }

    public function kwitansi(int $idPembayaran)
    {
        $bayar = model(PembayaranModel::class)->detailLengkap($idPembayaran);

        if ($bayar === null || (int) $bayar['id_pengguna'] !== (int) session('id_pengguna')) {
            return $this->response->setStatusCode(403)->setBody(view('errors/akses_ditolak'));
        }
        if ($bayar['status_pembayaran'] !== 'berhasil') {
            return redirect()->to('penghuni/riwayat')->with('gagal', 'Kwitansi hanya tersedia untuk pembayaran yang berhasil.');
        }

        return view('penghuni/kwitansi', ['judul' => 'Kwitansi Pembayaran', 'bayar' => $bayar]);
    }

    private function sekarang(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone(QrisGateway::ZONA_WAKTU)))->format('Y-m-d H:i:s');
    }
}
