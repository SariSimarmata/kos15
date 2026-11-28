<?php

namespace App\Controllers\Pemilik;

use App\Controllers\BaseController;
use App\Libraries\PembayaranService;
use App\Models\PembayaranModel;
use CodeIgniter\HTTP\RedirectResponse;
use DomainException;
use Throwable;

/**
 * Konfirmasi pembayaran tunai sisi pemilik (Kartu 6).
 *
 *   GET  pemilik/konfirmasi-tunai                 daftar pengajuan yang menunggu (menu "Konfirmasi Tunai")
 *   POST pemilik/konfirmasi-tunai/{id}/konfirmasi uang sudah diterima -> pembayaran berhasil, tagihan lunas
 *   POST pemilik/konfirmasi-tunai/{id}/tolak      uang belum diterima -> pembayaran gagal, tagihan tetap belum bayar
 *
 * Login dan peran pemilik dijaga filter 'peran:pemilik' di Routes.php. Id pemilik yang tercatat sebagai
 * id_konfirmator diambil dari session, bukan dari form. Aturan ada di PembayaranService.
 */
class KonfirmasiTunai extends BaseController
{
    protected $helpers = ['tanggal', 'kos'];

    private const HALAMAN = 'pemilik/konfirmasi-tunai';

    public function index()
    {
        return view('pemilik/konfirmasi_tunai/index', [
            'judul'  => 'Konfirmasi Tunai',
            'daftar' => model(PembayaranModel::class)->daftarTunaiMenunggu(),
        ]);
    }

    public function konfirmasi(int $idPembayaran): RedirectResponse
    {
        try {
            (new PembayaranService())->konfirmasiTunai($idPembayaran, (int) session('id_pengguna'));
        } catch (DomainException $e) {
            return redirect()->to(self::HALAMAN)->with('gagal', $e->getMessage());
        } catch (Throwable $e) {
            return $this->gagalTakTerduga('Konfirmasi', $idPembayaran, $e);
        }

        return redirect()->to(self::HALAMAN)->with('sukses', 'Pembayaran tunai dikonfirmasi. Tagihan sudah lunas dan penghuni diberi tahu.');
    }

    public function tolak(int $idPembayaran): RedirectResponse
    {
        if (! $this->validate(
            ['alasan' => 'permit_empty|max_length[200]'],
            ['alasan' => ['max_length' => 'Alasan maksimal 200 karakter.']]
        )) {
            return redirect()->to(self::HALAMAN)->with('gagal', implode(' ', $this->validator->getErrors()));
        }

        try {
            (new PembayaranService())->tolakTunai(
                $idPembayaran,
                (int) session('id_pengguna'),
                (string) $this->request->getPost('alasan')
            );
        } catch (DomainException $e) {
            return redirect()->to(self::HALAMAN)->with('gagal', $e->getMessage());
        } catch (Throwable $e) {
            return $this->gagalTakTerduga('Penolakan', $idPembayaran, $e);
        }

        return redirect()->to(self::HALAMAN)->with('sukses', 'Pengajuan tunai ditolak. Tagihan tetap belum dibayar dan penghuni diberi tahu.');
    }

    private function gagalTakTerduga(string $aksi, int $idPembayaran, Throwable $e): RedirectResponse
    {
        log_message('error', '{aksi} tunai pembayaran {id} gagal: {pesan}', [
            'aksi'  => $aksi,
            'id'    => $idPembayaran,
            'pesan' => $e->getMessage(),
        ]);

        return redirect()->to(self::HALAMAN)
            ->with('gagal', $aksi . ' belum berhasil dan tidak ada data yang berubah. Coba lagi beberapa saat.');
    }
}
