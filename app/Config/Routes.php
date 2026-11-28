<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Katalog::index');

// ---------------------------------------------------------------------
// Autentikasi (Kartu 5)
// ---------------------------------------------------------------------
$routes->get('login', 'Auth::login');
$routes->post('login', 'Auth::attemptLogin');
$routes->get('daftar', 'Auth::register');
$routes->post('daftar', 'Auth::attemptRegister');
$routes->post('logout', 'Auth::logout');

// ---------------------------------------------------------------------
// Hak akses per peran
// Tambahkan route halamanmu DI DALAM grup peran yang sesuai.
// Filter 'peran:...' otomatis menolak peran lain (403) dan tamu (ke login).
// ---------------------------------------------------------------------

// Pemilik (Kartu 6, 8, dan seterusnya)
$routes->group('pemilik', ['filter' => 'peran:pemilik'], static function ($routes) {
    $routes->get('dashboard', 'Sementara::dashboardPemilik'); // SEMENTARA

    // Kelola kamar (Kartu 6)
    $routes->get('kamar', 'Pemilik\Kamar::index');
    $routes->get('kamar/tambah', 'Pemilik\Kamar::tambah');
    $routes->post('kamar/simpan', 'Pemilik\Kamar::simpan');
    $routes->get('kamar/(:num)/ubah', 'Pemilik\Kamar::ubah/$1');
    $routes->post('kamar/(:num)/perbarui', 'Pemilik\Kamar::perbarui/$1');
    $routes->post('kamar/(:num)/hapus', 'Pemilik\Kamar::hapus/$1');
    $routes->post('kamar/(:num)/media', 'Pemilik\Kamar::unggahMedia/$1');
    $routes->post('media/(:num)/hapus', 'Pemilik\Kamar::hapusMedia/$1');

    // Data penghuni (Kartu 4 Sprint 4)
    $routes->get('penghuni', 'Pemilik\Penghuni::index');
    $routes->get('penghuni/tambah', 'Pemilik\Penghuni::tambah');
    $routes->post('penghuni/simpan', 'Pemilik\Penghuni::simpan');
    $routes->get('penghuni/(:num)/ubah', 'Pemilik\Penghuni::ubah/$1');
    $routes->post('penghuni/(:num)/perbarui', 'Pemilik\Penghuni::perbarui/$1');
    $routes->post('penghuni/(:num)/hapus', 'Pemilik\Penghuni::hapus/$1');

    // Tagihan (Kartu 3): seluruh tagihan dengan filter belum lunas dan lewat jatuh tempo
    $routes->get('tagihan', 'Pemilik\Tagihan::index');

    // Input meteran bulanan (Kartu 9): membuat tagihan listrik_air per penghuni
    $routes->get('meteran', 'Pemilik\Meteran::index');
    $routes->post('meteran/simpan', 'Pemilik\Meteran::simpan');

    // Info kos dan tarif (tabel info_kos hanya satu baris, jadi hanya ubah)
    $routes->get('info-kos', 'Pemilik\InfoKos::index');
    $routes->post('info-kos/simpan', 'Pemilik\InfoKos::simpan');

    // Kelola pengumuman dan peraturan
    $routes->get('pengumuman', 'Pemilik\Pengumuman::index');
    $routes->get('pengumuman/tambah', 'Pemilik\Pengumuman::tambah');
    $routes->post('pengumuman/simpan', 'Pemilik\Pengumuman::simpan');
    $routes->get('pengumuman/(:num)/ubah', 'Pemilik\Pengumuman::ubah/$1');
    $routes->post('pengumuman/(:num)/perbarui', 'Pemilik\Pengumuman::perbarui/$1');
    $routes->post('pengumuman/(:num)/hapus', 'Pemilik\Pengumuman::hapus/$1');

    // Kartu 8: persetujuan pemesanan
    $routes->get('pemesanan', 'Pemesanan::daftar');
    $routes->post('pemesanan/(:num)/setujui', 'Pemesanan::setujui/$1');
    $routes->post('pemesanan/(:num)/tolak', 'Pemesanan::tolak/$1');

    // Kartu 11: perpanjangan dan pembatalan sewa
    $routes->post('perpanjangan/(:num)/setujui', 'Pemilik\Perpanjangan::setujui/$1');
    $routes->post('perpanjangan/(:num)/tolak', 'Pemilik\Perpanjangan::tolak/$1');
    $routes->post('sewa/(:num)/batalkan', 'Pemilik\Perpanjangan::batalkan/$1');
        $routes->get('perpanjangan', 'Pemilik\DaftarPerpanjangan::index');
});

// Penghuni
$routes->group('penghuni', ['filter' => 'peran:penghuni'], static function ($routes) {
    // Tagihan (Kartu 3): daftar tagihan dan pilihan skema DP 50% / lunas
    $routes->get('tagihan', 'Penghuni\Tagihan::index');
    $routes->post('tagihan/skema', 'Penghuni\Tagihan::pilihSkema');

    // Kartu Pembayaran QRIS. URL sama dengan menu di partials/sidebar_penghuni.php
    $routes->get('bayar', 'Penghuni\Pembayaran::mulai');                        // menu "Bayar Tagihan"
    $routes->get('tagihan/(:num)/bayar', 'Penghuni\Pembayaran::pilih/$1');
    $routes->post('tagihan/(:num)/qris', 'Penghuni\Pembayaran::buatQris/$1');
    $routes->get('pembayaran/(:num)', 'Penghuni\Pembayaran::qris/$1');
    $routes->get('pembayaran/(:num)/status', 'Penghuni\Pembayaran::status/$1'); // JSON untuk polling
    $routes->get('pembayaran/(:num)/kwitansi', 'Penghuni\Riwayat::kwitansi/$1');
    $routes->get('riwayat', 'Penghuni\Riwayat::index');                         // menu "Riwayat Pembayaran"
    $routes->get('riwayat/unduh', 'Penghuni\Riwayat::unduhRekap');              // tombol "Unduh Rekap"

    // Masa sewa (Kartu 4 Sprint 4)
    $routes->get('sewa', 'Penghuni\Sewa::index');

    // Kartu 11: ajukan perpanjangan sewa
    $routes->post('sewa/(:num)/perpanjangan', 'Penghuni\Perpanjangan::ajukan/$1');
});

// Katalog publik (Kartu 7). Bisa dibuka tanpa login, jadi tanpa filter.
$routes->get('katalog', 'Katalog::index');
$routes->get('katalog/(:num)', 'Katalog::detail/$1');
$routes->get('katalog/(:num)/survei', 'Survei::form/$1');
$routes->post('katalog/(:num)/survei', 'Survei::simpan/$1');
$routes->get('katalog/survei/(:num)', 'Survei::konfirmasi/$1');

// Fitur calon penghuni yang wajib login (pesan kamar, pantau pengajuan)
// Kartu 8: /pemesanan/baru/{id_kamar} dipakai tombol "Pesan" di katalog (Kartu 7)
$routes->group('pemesanan', ['filter' => 'peran:calon_penghuni'], static function ($routes) {
    $routes->get('baru', 'Pemesanan::form');
    $routes->get('baru/(:num)', 'Pemesanan::form/$1');
    $routes->post('/', 'Pemesanan::simpan');
});

// Status Pengajuan juga boleh dibuka penghuni, supaya pemesanan yang sudah disetujui
// (dan nomor pemesanannya) tetap bisa dilihat setelah peran berubah menjadi penghuni.
$routes->get('pemesanan/status', 'Pemesanan::status', ['filter' => 'peran:calon_penghuni,penghuni']);
