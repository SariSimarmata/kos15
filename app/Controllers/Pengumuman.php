<?php

namespace App\Controllers;

use App\Models\InfoKosModel;
use App\Models\PengumumanModel;

/**
 * Halaman baca pengumuman dan peraturan untuk penghuni dan calon penghuni.
 * Route dilindungi filter 'peran:penghuni,calon_penghuni' di Config/Routes.php.
 * Pemilik mengelolanya lewat Pemilik\Pengumuman.
 */
class Pengumuman extends BaseController
{
    protected $helpers = ['tanggal', 'kos'];

    /** GET /pengumuman */
    public function index()
    {
        $model = model(PengumumanModel::class);
        $info  = model(InfoKosModel::class)->ambil();
        $wa    = (string) $info['no_whatsapp'];

        // Penghuni memakai panel dengan sidebar, calon penghuni memakai tampilan publik
        $layout = session('role') === 'penghuni' ? 'layouts/penghuni' : 'layouts/main';

        return view('pengumuman/index', [
            'judul'      => 'Pengumuman dan Peraturan',
            'layout'     => $layout,
            'pengumuman' => $model->pengumuman(),
            'peraturan'  => $model->peraturan(),
            'info'       => $info,
            'waLink'     => $wa !== '' ? wa_link($wa, 'Halo, saya ingin bertanya tentang Kos 15.') : null,
        ]);
    }
}
