<?php
// Halaman di bawah /pemilik memakai layout pemilik (sidebar, sesuai Figma); peran lain tetap layout biasa
$halamanPemilik = str_starts_with(trim(uri_string(), '/'), 'pemilik/');
if ($halamanPemilik && ($judul ?? '') === 'Dasbor Pemilik') {
    $this->setVar('judul', 'Dashboard'); // sesuai Figma
    $judul = 'Dashboard';
}
?>
<?php $halamanPenghuni = str_starts_with(trim(uri_string(), '/'), 'penghuni/'); ?>
<?= $this->extend($halamanPemilik ? 'layouts/pemilik' : ($halamanPenghuni ? 'layouts/penghuni' : 'layouts/main')) ?>

<?= $this->section('content') ?>
<!-- Halaman sementara (Kartu 5). Diganti oleh kartu pemilik halaman ini. -->
<?php if ($halamanPemilik): ?>
    <?php
    helper('tanggal');
    $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $hariIni = new DateTime('today', new DateTimeZone('Asia/Jakarta'));
    ?>
    <div class="header-halaman">
        <h1 class="judul-halaman mb-1"><?= esc($judul) ?></h1>
        <p class="mb-0">Ringkasan kos per <?= esc($hari[(int) $hariIni->format('w')] . ', ' . tanggal_indonesia($hariIni->format('Y-m-d'))) ?></p>
    </div>
    <div class="banner-info mb-4"><i class="bi bi-bell me-2" aria-hidden="true"></i>Halaman ini masih kosong dan akan dikerjakan di <?= esc($kartu) ?>.</div>
    <div class="card kartu-panel">
        <span class="lencana lencana-kuning align-self-start">Segera</span>
        <p class="mb-0">Isi halaman menyusul.</p>
    </div>
<?php else: ?>
    <h1 class="judul-halaman"><?= esc($judul) ?></h1>
    <div class="banner-info mb-4">Halaman ini masih kosong dan akan dikerjakan di <?= esc($kartu) ?>.</div>
    <div class="card p-4">
        <span class="lencana lencana-kuning align-self-start mb-2">Segera</span>
        <p class="mb-0">Isi halaman menyusul.</p>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>
