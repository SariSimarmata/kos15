<?= $this->extend('layouts/penghuni') ?>

<?= $this->section('content') ?>
<?php
/*
 * Laporan kerusakan untuk penghuni (hanya baca).
 * Data dari Penghuni\Kerusakan: $laporan, $mode ('bersama' = semua laporan, 'saya' = laporan sendiri).
 * Halaman ini tidak pernah menerima nama atau id pelapor, jadi tidak ada yang bisa bocor lewat tampilan.
 */
use App\Models\LaporanKerusakanModel;

$bersama = $mode === 'bersama';
?>
<div class="header-halaman d-flex flex-wrap justify-content-between align-items-end gap-3">
    <div>
        <h1 class="judul-halaman mb-1">Laporan Kerusakan</h1>
        <p class="mb-0">
            <?= $bersama
                ? 'Semua laporan kerusakan di kos, supaya kamu tahu apa yang sedang diperbaiki. Nama pelapor tidak ditampilkan.'
                : 'Laporan yang kamu kirim dan perkembangan perbaikannya.' ?>
        </p>
    </div>
    <a href="<?= site_url('penghuni/kerusakan/baru') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg" aria-hidden="true"></i> Buat Laporan
    </a>
</div>

<div class="card kartu-panel">
    <div class="d-flex flex-wrap gap-2" role="group" aria-label="Pilih daftar">
        <a href="<?= site_url('penghuni/kerusakan') ?>" class="chip-filter <?= $bersama ? 'aktif' : '' ?>" <?= $bersama ? 'aria-current="true"' : '' ?>>Semua laporan</a>
        <a href="<?= site_url('penghuni/kerusakan/saya') ?>" class="chip-filter <?= ! $bersama ? 'aktif' : '' ?>" <?= ! $bersama ? 'aria-current="true"' : '' ?>>Laporan saya</a>
    </div>

    <?php if ($laporan === []): ?>
        <div class="kosong-panel">
            <i class="bi bi-wrench" aria-hidden="true"></i>
            <p class="mb-0"><?= $bersama ? 'Belum ada laporan kerusakan.' : 'Kamu belum membuat laporan kerusakan.' ?></p>
        </div>
    <?php else: ?>
        <div class="d-flex flex-column gap-3">
            <?php foreach ($laporan as $l): ?>
                <?php
                $foto        = LaporanKerusakanModel::urlFoto($l['foto']);
                $fotoPerbaik = LaporanKerusakanModel::urlFoto($l['foto_perbaikan']);
                $status      = $l['status_perbaikan'];
                ?>
                <article class="banner-info">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <span class="teks-kecil"><?= esc(tanggal_indonesia($l['tanggal_lapor'])) ?></span>
                        <span class="lencana <?= LaporanKerusakanModel::LENCANA[$status] ?? 'lencana-abu' ?>">
                            <?= esc(LaporanKerusakanModel::STATUS[$status] ?? $status) ?>
                        </span>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <?php if ($foto !== null): ?>
                                <img src="<?= esc($foto, 'attr') ?>" alt="Foto kerusakan" class="img-fluid rounded" loading="lazy"
                                    style="width: 100%; aspect-ratio: 4 / 3; object-fit: cover;">
                            <?php else: ?>
                                <div class="rounded d-flex align-items-center justify-content-center teks-kecil"
                                    style="width: 100%; aspect-ratio: 4 / 3; background: #EEF1F4;">Foto belum tersedia</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-9">
                            <div style="white-space: pre-line;"><?= esc($l['deskripsi']) ?></div>

                            <?php if ($status === 'selesai' && ($l['catatan_perbaikan'] || $fotoPerbaik)): ?>
                                <hr class="my-2">
                                <div class="teks-kecil mb-1">
                                    Selesai <?= esc(tanggal_indonesia($l['tanggal_selesai'])) ?>
                                </div>
                            <?php elseif ($l['catatan_perbaikan']): ?>
                                <hr class="my-2">
                            <?php endif; ?>
                            <?php if ($l['catatan_perbaikan']): ?>
                                <div><strong>Catatan perbaikan:</strong> <span style="white-space: pre-line;"><?= esc($l['catatan_perbaikan']) ?></span></div>
                            <?php endif; ?>
                            <?php if ($fotoPerbaik !== null): ?>
                                <a href="<?= esc($fotoPerbaik, 'attr') ?>" target="_blank" rel="noopener" class="d-inline-block mt-2">
                                    <img src="<?= esc($fotoPerbaik, 'attr') ?>" alt="Foto perbaikan" class="rounded" loading="lazy"
                                        style="height: 80px; width: auto;">
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
