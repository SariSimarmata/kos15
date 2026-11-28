<?php
/*
 * Sidebar pemilik sesuai Figma "Kos 15 – Halaman Pemilik (Revisi)".
 * Format: 'url relatif' => [label, ikon Bootstrap Icons]
 * Menu yang halamannya belum dibuat tetap tampil seperti di Figma;
 * alamatnya disamakan dengan Config/Menu.php bila sudah ada di sana.
 */
$grupMenu = [
    'UTAMA' => [
        'pemilik/dashboard'        => ['Dashboard', 'bi-house'],
        'pemilik/penghuni'         => ['Data Penghuni', 'bi-people'],
        'pemilik/pembayaran'       => ['Pantau Pembayaran', 'bi-list-ul'],
        'pemilik/konfirmasi-tunai' => ['Konfirmasi Tunai', 'bi-cash'],
    ],
    'PENGAJUAN' => [
        'pemilik/pemesanan'        => ['Pemesanan Kamar', 'bi-key'],
        'pemilik/perpanjangan'     => ['Perpanjangan Sewa', 'bi-arrow-repeat'],
        'pemilik/survei'           => ['Pengajuan Survei', 'bi-eye'],
    ],
    'OPERASIONAL' => [
        'pemilik/tagihan'          => ['Tagihan', 'bi-file-earmark-text'],
        'pemilik/meteran'          => ['Input Meteran', 'bi-speedometer2'],
        'pemilik/piket'            => ['Piket', 'bi-clipboard-check'],
    ],
    'LAPORAN' => [
        'pemilik/pemasukan'        => ['Pemasukan', 'bi-graph-up-arrow'],
        'pemilik/tunggakan'        => ['Tunggakan', 'bi-exclamation-triangle'],
        'pemilik/okupansi'         => ['Okupansi', 'bi-door-closed'],
    ],
    'KELOLA' => [
        'pemilik/kamar'            => ['Katalog Kamar', 'bi-grid'],
        'pemilik/info-kos'         => ['Info Kos dan Tarif', 'bi-info-circle'],
        'pemilik/pengumuman'       => ['Pengumuman', 'bi-megaphone'],
        'pemilik/kerusakan'        => ['Status Kerusakan', 'bi-wrench'],
    ],
];

// Angka di samping menu: jumlah yang menunggu tindakan pemilik.
// Baru Pemesanan Kamar yang datanya sudah ada (Kartu 8).
$angkaMenu = [];
try {
    $angkaMenu['pemilik/pemesanan'] = db_connect()->table('pemesanan')
        ->where('status_pengajuan', 'pending')
        ->countAllResults();
} catch (\Throwable $e) {
    // Sidebar tidak boleh membuat halaman gagal tampil
    log_message('error', 'Sidebar pemilik: ' . $e->getMessage());
}

$sekarang = trim(uri_string(), '/');
?>
<aside class="sidebar-pemilik" id="sidebarPemilik" aria-label="Menu pemilik">
    <a class="sidebar-logo" href="<?= site_url('pemilik/dashboard') ?>">
        <span class="logo-tanda">15</span>
        <span>
            <span class="nama">Kos 15</span>
            <span class="sub">Panel Pemilik</span>
        </span>
    </a>

    <nav class="sidebar-menu">
        <?php foreach ($grupMenu as $grup => $menu): ?>
            <div class="sidebar-grup"><?= esc($grup) ?></div>
            <?php foreach ($menu as $url => [$label, $ikon]): ?>
                <?php
                $aktif = $sekarang === $url || str_starts_with($sekarang, $url . '/');
                $angka = (int) ($angkaMenu[$url] ?? 0);
                ?>
                <a href="<?= site_url($url) ?>" class="sidebar-item <?= $aktif ? 'aktif' : '' ?>" <?= $aktif ? 'aria-current="page"' : '' ?>>
                    <i class="bi <?= $ikon ?>" aria-hidden="true"></i>
                    <span class="flex-grow-1"><?= esc($label) ?></span>
                    <?php if ($angka > 0): ?>
                        <span class="sidebar-angka"><?= $angka > 99 ? '99+' : $angka ?></span>
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
