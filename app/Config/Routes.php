<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

// Autentikasi (Kartu 5)
$routes->get('login', 'Auth::login');
$routes->post('login', 'Auth::attemptLogin');
$routes->get('daftar', 'Auth::register');
$routes->post('daftar', 'Auth::attemptRegister');
$routes->post('logout', 'Auth::logout');

// SEMENTARA: halaman tujuan redirect agar login bisa diuji.
// Hapus tiga baris ini setelah Kartu 6 sampai 8 membuat halaman aslinya.
$routes->get('pemilik/dashboard', static fn() => 'Dashboard pemilik (sementara)');
$routes->get('penghuni/tagihan', static fn() => 'Tagihan penghuni (sementara)');
$routes->get('katalog', static fn() => 'Katalog kamar (sementara)');
