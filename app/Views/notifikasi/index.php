<?= $this->extend(match ($peran) { 'pemilik' => 'layouts/pemilik', 'penghuni' => 'layouts/penghuni', default => 'layouts/main' }) ?>

<?= $this->section('content') ?>
<?php
/*
 * Halaman Notifikasi semua peran, sesuai desain "Komponen: Badge Status dan Notifikasi":
 * chip Semua / Belum dibaca, titik biru untuk yang belum dibaca, lencana kategori, Tandai Semua Dibaca.
 * Data dari Notifikasi::index(). Klik baris = tandai dibaca lalu buka halaman terkait.
 */
$kategori = [
    'tagihan'      => ['Tagihan', 'lencana-kuning'],
    'denda'        => ['Denda', 'lencana-merah'],
    'pembayaran'   => ['Pembayaran', 'lencana-hijau'],
    'aduan'        => ['Kerusakan', 'lencana-merah'],
    'pemesanan'    => ['Pemesanan', 'lencana-biru'],
    'piket'        => ['Piket', 'lencana-biru'],
    'perpanjangan' => ['Perpanjangan', 'lencana-biru'],
    'meteran'      => ['Meteran', 'lencana-abu'],
];
$panelPemilik = in_array($peran, ['pemilik', 'penghuni'], true); // layout sidebar
?>

<div class="<?= $panelPemilik ? 'header-halaman' : '' ?> d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
    <div>
        <h1 class="judul-halaman mb-1">Notifikasi</h1>
        <p class="subjudul-halaman">
            <?= $belumDibaca > 0 ? esc($belumDibaca) . ' notifikasi belum dibaca' : 'Semua notifikasi sudah dibaca' ?>
        </p>
    </div>
    <?php if ($belumDibaca > 0): ?>
        <form action="<?= site_url('notifikasi/baca-semua') ?>" method="post">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline-primary">
                <i class="bi bi-check2-all me-1" aria-hidden="true"></i> Tandai Semua Dibaca
            </button>
        </form>
    <?php endif; ?>
</div>

<div class="kartu-putih">
    <div class="d-flex gap-2 mb-3">
        <a href="<?= site_url('notifikasi') ?>" class="chip-filter <?= $filter === 'semua' ? 'aktif' : '' ?>">
            Semua <span class="angka-filter"><?= esc($jumlahSemua) ?></span>
        </a>
        <a href="<?= site_url('notifikasi?filter=belum') ?>" class="chip-filter <?= $filter === 'belum' ? 'aktif' : '' ?>">
            Belum dibaca <span class="angka-filter"><?= esc($belumDibaca) ?></span>
        </a>
    </div>

    <?php if (empty($notifikasi)): ?>
        <div class="banner-info"><?= $filter === 'belum' ? 'Tidak ada notifikasi yang belum dibaca.' : 'Belum ada notifikasi.' ?></div>
    <?php endif; ?>

    <?php foreach ($notifikasi as $n): ?>
        <?php
        $baru = (int) $n['status_baca'] === 0;
        [$teksKategori, $kelasKategori] = $kategori[$n['jenis_notifikasi']] ?? [ucfirst($n['jenis_notifikasi']), 'lencana-abu'];
        ?>
        <a href="<?= site_url('notifikasi/' . $n['id_notifikasi'] . '/buka') ?>" class="notif-item <?= $baru ? 'baru' : '' ?>">
            <span class="notif-titik" aria-hidden="true"></span>
            <span class="min-w-0 flex-grow-1">
                <span class="judul d-block"><?= esc($n['pesan']) ?></span>
                <span class="meta">
                    <?= esc(tanggal_indonesia($n['waktu_kirim'])) ?>, <?= esc(date('H.i', strtotime($n['waktu_kirim']))) ?> WIB
                    <?php if ($baru): ?><span class="visually-hidden">(belum dibaca)</span><?php endif; ?>
                </span>
            </span>
            <span class="kanan">
                <span class="lencana <?= $kelasKategori ?>"><?= esc($teksKategori) ?></span>
            </span>
        </a>
    <?php endforeach; ?>

    <?php if ($pager !== null && $pager->getPageCount() > 1): ?>
        <div class="mt-3"><?= $pager->links() ?></div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
