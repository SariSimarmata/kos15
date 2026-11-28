<?php
/*
 * Sidebar penghuni, sesuai desain "Halaman Penghuni" (gaya sama dengan sidebar pemilik).
 * Format: 'url relatif' => [label, ikon Bootstrap Icons, awalan URL lain yang ikut membuat menu aktif]
 * Piket dan Menginap (penghuni/piket, penghuni/pengunjung) halamannya dibuat di kartu masing-masing.
 */
$grupMenu = [
    'UTAMA' => [
        'penghuni/tagihan' => ['Tagihan', 'bi-receipt', []],
        'penghuni/bayar'   => ['Bayar Tagihan', 'bi-credit-card', ['penghuni/tagihan/*/bayar', 'penghuni/pembayaran/*']],
        'penghuni/riwayat' => ['Riwayat Pembayaran', 'bi-clock-history', ['penghuni/pembayaran/*/kwitansi']],
        'notifikasi'       => ['Notifikasi', 'bi-bell', []],
    ],
    'HUNIAN' => [
        'penghuni/sewa'       => ['Masa Sewa', 'bi-calendar-range', []],
        'penghuni/piket'      => ['Piket', 'bi-list-check', []],
        'penghuni/pengunjung' => ['Menginap', 'bi-door-open', []],
        'penghuni/kerusakan'  => ['Lapor Kerusakan', 'bi-wrench', []],
    ],
];

$sekarang = trim(uri_string(), '/');

// Menu yang aktif: URL persis/turunannya, atau cocok dengan pola tambahan (* = satu bagian URL).
$menuAktif = null;
foreach ($grupMenu as $menu) {
    foreach ($menu as $url => [, , $pola]) {
        foreach ($pola as $p) {
            $regex = '#^' . str_replace('\*', '[^/]+', preg_quote($p, '#')) . '$#';
            if ($menuAktif === null && preg_match($regex, $sekarang)) {
                $menuAktif = $url;
            }
        }
    }
}
if ($menuAktif === null) {
    foreach ($grupMenu as $menu) {
        foreach (array_keys($menu) as $url) {
            if ($sekarang === $url || str_starts_with($sekarang, $url . '/')) {
                $menuAktif = $url;
            }
        }
    }
}

$belumDibaca = model(\App\Models\NotifikasiModel::class)
    ->where('id_pengguna', (int) session('id_pengguna'))
    ->where('status_baca', 0)
    ->countAllResults();
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
                <?php $aktif = $menuAktif === $url; ?>
                <a href="<?= site_url($url) ?>" class="sidebar-item <?= $aktif ? 'aktif' : '' ?>" <?= $aktif ? 'aria-current="page"' : '' ?>>
                    <i class="bi <?= $ikon ?>" aria-hidden="true"></i>
                    <span class="flex-grow-1"><?= esc($label) ?></span>
                    <?php if ($url === 'notifikasi' && $belumDibaca > 0): ?>
                        <span class="sidebar-angka"><?= $belumDibaca > 99 ? '99+' : $belumDibaca ?></span>
                    <?php endif; ?>
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
