<?php

namespace App\Controllers\Penghuni;

use App\Controllers\BaseController;
use App\Libraries\QrisGateway;
use App\Models\PembayaranModel;
use App\Models\TagihanModel;
use CodeIgniter\HTTP\ResponseInterface;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Riwayat pembayaran penghuni (kartu Pembayaran QRIS). Halaman Tagihan ada di Penghuni\Tagihan (Kartu 3).
 *
 *   GET penghuni/riwayat                     Riwayat Pembayaran (menu sidebar), ?cari=&status=
 *   GET penghuni/riwayat/unduh               Unduh Rekap (CSV)
 *   GET penghuni/pembayaran/{id}/kwitansi    Kwitansi pembayaran berhasil (siap cetak)
 *
 * Login dan peran penghuni dijaga filter 'peran:penghuni' di Routes.php.
 */
class Riwayat extends BaseController
{
    protected $helpers = ['tanggal', 'kos'];

    public function index()
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
