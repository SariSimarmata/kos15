<?php
/*
 * Sidebar penghuni (Kartu 4 Sprint 4), memakai gaya yang sama dengan sidebar pemilik.
 * Format: 'url relatif' => [label, ikon Bootstrap Icons]
 * Piket dan Pengunjung belum tampil karena ditunda di Sprint 4 (KF-24, KF-25, KF-26).
 */
$grupMenu = [
    'UTAMA' => [
        'penghuni/tagihan'   => ['Tagihan', 'bi-receipt'],
        'penghuni/riwayat'   => ['Riwayat Pembayaran', 'bi-clock-history'],
        'penghuni/sewa'      => ['Masa Sewa', 'bi-calendar-range'],
    ],
    'KOS' => [
        'penghuni/kerusakan' => ['Lapor Kerusakan', 'bi-wrench'],
        'pengumuman'         => ['Pengumuman', 'bi-megaphone'],
        'katalog'            => ['Katalog Kamar', 'bi-grid'],
    ],
];

$sekarang = trim(uri_string(), '/');
?>
<aside class="sidebar-pemilik" id="sidebarPenghuni" aria-label="Menu penghuni">
    <a class="sidebar-logo" href="<?= site_url('penghuni/tagihan') ?>">
        <span class="logo-tanda">15</span>
        <span>
            <span class="nama">Kos 15</span>
            <span class="sub">Panel Penghuni</span>
        </span>
    </a>

    <nav class="sidebar-menu">
        <?php foreach ($grupMenu as $grup => $menu): ?>
            <div class="sidebar-grup"><?= esc($grup) ?></div>
            <?php foreach ($menu as $url => [$label, $ikon]): ?>
                <?php $aktif = $sekarang === $url || str_starts_with($sekarang, $url . '/'); ?>
                <a href="<?= site_url($url) ?>" class="sidebar-item <?= $aktif ? 'aktif' : '' ?>" <?= $aktif ? 'aria-current="page"' : '' ?>>
                    <i class="bi <?= $ikon ?>" aria-hidden="true"></i>
                    <span class="flex-grow-1"><?= esc($label) ?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <form action="<?= site_url('logout') ?>" method="post" class="sidebar-keluar">
        <?= csrf_field() ?>
        <button type="submit" class="sidebar-item">
            <i class="bi bi-box-arrow-left" aria-hidden="true"></i>
            <span>Keluar</span>
        </button>
    </form>
</aside>
