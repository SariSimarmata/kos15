<?php

namespace App\Filters;

use App\Controllers\Auth;
use App\Models\PenggunaModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Satu filter untuk semua peran (Kartu 5).
 *
 * Pemakaian di Routes.php:
 *   'filter' => 'peran'                          cukup sudah login
 *   'filter' => 'peran:pemilik'                  hanya pemilik
 *   'filter' => 'peran:penghuni,calon_penghuni'  salah satu dari dua peran
 *
 * Belum login  -> diarahkan ke halaman login
 * Peran salah  -> halaman 403 (akses ditolak)
 */
class PeranFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();

        if (! $session->get('logged_in')) {
            // Simpan alamat tujuan agar setelah login bisa kembali ke sana
            $session->set('redirect_url', current_url());

            return redirect()->to(site_url('login'))
                ->with('gagal', 'Silakan masuk terlebih dahulu.');
        }

        // Peran di session bisa sudah usang, misalnya calon penghuni yang baru disetujui pemilik
        // (peran di database sudah penghuni). Samakan dulu dengan database, lalu arahkan ke
        // halaman utama peran barunya supaya tidak kena halaman 403.
        $pengguna = model(PenggunaModel::class)->find((int) $session->get('id_pengguna'));
        if ($pengguna === null) {
            $session->destroy();

            return redirect()->to(site_url('login'))
                ->with('gagal', 'Akunmu tidak ditemukan. Silakan masuk lagi.');
        }
        if ($pengguna['role'] !== $session->get('role')) {
            $session->set('role', $pengguna['role']);

            return redirect()->to(site_url(Auth::TUJUAN[$pengguna['role']] ?? '/'))
                ->with('sukses', $pengguna['role'] === 'penghuni'
                    ? 'Pemesananmu disetujui. Kamu sekarang terdaftar sebagai penghuni.'
                    : 'Peran akunmu sudah diperbarui.');
        }

        if (empty($arguments)) {
            return null;
        }

        if (! in_array($session->get('role'), $arguments, true)) {
            return service('response')
                ->setStatusCode(403)
                ->setBody(view('errors/akses_ditolak'));
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
