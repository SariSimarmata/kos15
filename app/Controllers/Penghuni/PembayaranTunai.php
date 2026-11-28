<?php

namespace App\Controllers\Penghuni;

use App\Controllers\BaseController;
use App\Libraries\PembayaranService;
use CodeIgniter\HTTP\RedirectResponse;
use DomainException;
use Throwable;

/**
 * Pembayaran tunai sisi penghuni (Kartu 6).
 *
 *   POST penghuni/tagihan/{id}/tunai   penghuni mengajukan pembayaran tunai
 *
 * Tombolnya ada di Views/pembayaran/pilih.php. Riwayat dan Kwitansi memakai halaman yang sudah ada
 * (Penghuni\Riwayat). Semua aturan ada di PembayaranService::ajukanTunai(); controller ini hanya
 * meneruskan dan menampilkan pesan. Login dan peran dijaga filter 'peran:penghuni' di Routes.php.
 */
class PembayaranTunai extends BaseController
{
    public function ajukan(int $idTagihan): RedirectResponse
    {
        $kembali = 'penghuni/tagihan/' . $idTagihan . '/bayar';

        try {
            (new PembayaranService())->ajukanTunai($idTagihan, (int) session('id_pengguna'));
        } catch (DomainException $e) {
            return redirect()->to($kembali)->with('gagal', $e->getMessage());
        } catch (Throwable $e) {
            log_message('error', 'Pengajuan tunai tagihan {id} gagal: {pesan}', [
                'id'    => $idTagihan,
                'pesan' => $e->getMessage(),
            ]);

            return redirect()->to($kembali)
                ->with('gagal', 'Pengajuan belum bisa dikirim dan tidak ada data yang berubah. Coba lagi beberapa saat.');
        }

        return redirect()->to('penghuni/riwayat')
            ->with('sukses', 'Pengajuan bayar tunai terkirim. Tagihan menjadi lunas setelah pemilik mengonfirmasi.');
    }
}
