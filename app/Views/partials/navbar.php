<?php
/** @var \Config\Menu $menuConfig */
$menuConfig = config('Menu');
$peran      = session('role');
$masuk      = (bool) session('logged_in');
// Tamu melihat menu publik (Katalog) seperti di prototipe
$menu     = $masuk ? $menuConfig->untuk($peran) : $menuConfig->tamu;
$sekarang = trim(uri_string(), '/');
?>
<nav class="navbar navbar-expand-lg navbar-kos">
    <div class="container">
        <a class="navbar-brand" href="<?= site_url(array_key_first($menu) ?? 'katalog') ?>">
            <span class="logo-kos">15</span> Kos 15
        </a>

        <div class="d-flex align-items-center gap-1 order-lg-last">
            <?php if ($masuk): ?>
                <?= view_cell('LoncengCell') ?>
                <span class="nama-pengguna d-none d-xl-inline mx-2"><?= esc(session('nama')) ?></span>
                <form action="<?= site_url('logout') ?>" method="post" class="d-none d-lg-block ms-1">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary">Keluar</button>
                </form>
            <?php else: ?>
                <a class="nav-link d-none d-lg-block px-3 small" href="<?= site_url('login') ?>">Masuk</a>
                <a class="btn btn-primary d-none d-lg-inline-block" href="<?= site_url('daftar') ?>">Daftar</a>
            <?php endif; ?>

            <button class="navbar-toggler border-0 ms-1" type="button" data-bs-toggle="collapse" data-bs-target="#menuUtama"
                aria-controls="menuUtama" aria-expanded="false" aria-label="Buka menu">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>

        <div class="collapse navbar-collapse" id="menuUtama">
            <ul class="navbar-nav ms-auto me-lg-3">
                <?php foreach ($menu as $url => $label): ?>
                    <?php $aktif = $sekarang === $url || str_starts_with($sekarang, $url . '/'); ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $aktif ? 'active' : '' ?>" <?= $aktif ? 'aria-current="page"' : '' ?>
                            href="<?= site_url($url) ?>"><?= esc($label) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <!-- Tombol akun versi HP (di layar lebar tampil di kanan) -->
            <div class="d-lg-none border-top pt-3 mt-2 pb-1">
                <?php if ($masuk): ?>
                    <div class="nama-pengguna mb-2"><?= esc(session('nama')) ?> · <?= esc($menuConfig->labelPeran[$peran] ?? $peran) ?></div>
                    <form action="<?= site_url('logout') ?>" method="post">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-primary w-100">Keluar</button>
                    </form>
                <?php else: ?>
                    <a class="btn btn-outline-primary w-100 mb-2" href="<?= site_url('login') ?>">Masuk</a>
                    <a class="btn btn-primary w-100" href="<?= site_url('daftar') ?>">Daftar</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
