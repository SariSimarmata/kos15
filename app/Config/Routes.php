<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

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
});

// Penghuni
$routes->group('penghuni', ['filter' => 'peran:penghuni'], static function ($routes) {
    $routes->get('tagihan', 'Sementara::tagihanPenghuni'); // SEMENTARA
});

// Katalog publik (Kartu 7). Bisa dibuka tanpa login, jadi tanpa filter.
$routes->get('katalog', 'Sementara::katalog'); // SEMENTARA

// Fitur calon penghuni yang wajib login (pesan kamar, pantau pengajuan)
$routes->group('', ['filter' => 'peran:calon_penghuni'], static function ($routes) {
    // contoh Kartu 8: $routes->post('pemesanan', 'Pemesanan::simpan');
});
