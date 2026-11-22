<?php

namespace App\Controllers;

/**
 * Halaman sementara agar navbar dan filter bisa diuji (Kartu 5).
 * Hapus route ke controller ini begitu kartu pemilik halaman membuat halaman aslinya.
 */
class Sementara extends BaseController
{
    public function dashboardPemilik()
    {
        return view('sementara/index', ['judul' => 'Dasbor Pemilik', 'kartu' => 'kartu dasbor pemilik']);
    }

    public function tagihanPenghuni()
    {
        return view('sementara/index', ['judul' => 'Tagihan Saya', 'kartu' => 'kartu tagihan']);
    }
}
