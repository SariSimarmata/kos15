<!DOCTYPE html>
<html lang="id">

<head>
    <?= $this->include('partials/head') ?>
    <?= $this->renderSection('head') ?>
</head>

<?php
/*
 * Layout halaman penghuni (Kartu 4 Sprint 4), disamakan dengan layout pemilik:
 * sidebar biru di kiri, navbar putih (remah + lonceng + profil) di atas, konten di kanan.
 * Halaman penghuni cukup memakai: <?= $this->extend('layouts/penghuni') ?>
 */
$nama = (string) (session('nama') ?: 'Penghuni');
$kata = preg_split('/\s+/', trim($nama)) ?: [];
$inisialPengguna = '';
foreach (array_slice($kata, 0, 2) as $k) {
    $inisialPengguna .= mb_strtoupper(mb_substr($k, 0, 1));
}
?>

<body class="panel-pemilik">
    <?= $this->include('partials/sidebar_penghuni') ?>
    <div class="sidebar-tirai" data-tutup-sidebar aria-hidden="true"></div>

    <div class="pemilik-utama">
        <header class="topbar-pemilik">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <button type="button" class="tombol-menu d-lg-none" data-buka-sidebar aria-label="Buka menu" aria-controls="sidebarPenghuni" aria-expanded="false">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
                <nav class="remah-pemilik text-truncate" aria-label="Lokasi halaman">
                    Panel Penghuni <span aria-hidden="true">/</span> <span aria-current="page"><?= esc($judul ?? '') ?></span>
                </nav>
            </div>

            <div class="d-flex align-items-center gap-3">
                <?= view_cell('LoncengCell') ?>
                <div class="profil-pemilik">
                    <span class="avatar-profil"><?= esc($inisialPengguna ?: 'PH') ?></span>
                    <span class="d-none d-sm-block">
                        <span class="nama"><?= esc($nama) ?></span>
                        <span class="peran">Penghuni</span>
                    </span>
                </div>
            </div>
        </header>

        <main class="konten-pemilik">
            <?= $this->include('partials/flash') ?>
            <?= $this->renderSection('content') ?>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar di layar kecil: buka/tutup dengan tombol menu, tirai, atau tombol Esc
        (function () {
            var tombol = document.querySelector('[data-buka-sidebar]');
            function atur(buka) {
                document.body.classList.toggle('sidebar-terbuka', buka);
                if (tombol) tombol.setAttribute('aria-expanded', buka ? 'true' : 'false');
            }
            if (tombol) tombol.addEventListener('click', function () { atur(!document.body.classList.contains('sidebar-terbuka')); });
            document.querySelectorAll('[data-tutup-sidebar]').forEach(function (el) {
                el.addEventListener('click', function () { atur(false); });
            });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') atur(false); });
        })();
    </script>
    <?= $this->renderSection('script') ?>
</body>

</html>
