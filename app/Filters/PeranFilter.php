<?php

namespace App\Filters;

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
