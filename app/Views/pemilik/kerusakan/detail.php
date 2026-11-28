<?= $this->extend('layouts/pemilik') ?>

<?= $this->section('content') ?>
<?php
/*
 * Detail laporan dan form ubah status untuk pemilik.
 * Data dari Pemilik\Kerusakan::detail(): $laporan (dengan nama_pelapor, nomor_kamar), $berikut (status berikutnya atau null).
 */
use App\Models\LaporanKerusakanModel;

$errors      = (array) session('errors');
$status      = $laporan['status_perbaikan'];
$foto        = LaporanKerusakanModel::urlFoto($laporan['foto']);
$fotoPerbaik = LaporanKerusakanModel::urlFoto($laporan['foto_perbaikan']);
?>
<div class="header-halaman">
    <a href="<?= site_url('pemilik/kerusakan') ?>" class="teks-kecil">&larr; Kembali ke daftar kerusakan</a>
    <h1 class="judul-halaman mt-2 mb-1">Detail Kerusakan</h1>
    <p class="mb-0">
        Dilaporkan <?= esc(tanggal_indonesia($laporan['tanggal_lapor'])) ?> oleh <?= esc($laporan['nama_pelapor']) ?>,
        kamar <?= esc($laporan['nomor_kamar'] ?? '-') ?>.
    </p>
</div>

<div class="card kartu-panel mb-3">
    <div class="d-flex justify-content-between align-items-center">
        <h2 class="judul-panel mb-0">Laporan</h2>
        <span class="lencana <?= LaporanKerusakanModel::LENCANA[$status] ?? 'lencana-abu' ?>"><?= esc(LaporanKerusakanModel::STATUS[$status] ?? $status) ?></span>
    </div>
    <div class="row g-3">
        <div class="col-md-4">
            <?php if ($foto !== null): ?>
                <a href="<?= esc($foto, 'attr') ?>" target="_blank" rel="noopener">
                    <img src="<?= esc($foto, 'attr') ?>" alt="Foto kerusakan" class="img-fluid rounded"
                        style="width: 100%; aspect-ratio: 4 / 3; object-fit: cover;">
                </a>
            <?php else: ?>
                <div class="rounded d-flex align-items-center justify-content-center teks-kecil"
                    style="width: 100%; aspect-ratio: 4 / 3; background: #EEF1F4;">Foto belum tersedia</div>
            <?php endif; ?>
        </div>
        <div class="col-md-8" style="white-space: pre-line;"><?= esc($laporan['deskripsi']) ?></div>
    </div>

    <?php if ($laporan['catatan_perbaikan'] || $fotoPerbaik !== null || $laporan['tanggal_selesai']): ?>
        <hr class="my-1">
        <h3 class="h6 mb-0">Perbaikan</h3>
        <?php if ($laporan['catatan_perbaikan']): ?>
            <div style="white-space: pre-line;"><?= esc($laporan['catatan_perbaikan']) ?></div>
        <?php endif; ?>
        <?php if ($fotoPerbaik !== null): ?>
            <a href="<?= esc($fotoPerbaik, 'attr') ?>" target="_blank" rel="noopener">
                <img src="<?= esc($fotoPerbaik, 'attr') ?>" alt="Foto perbaikan" class="rounded" style="height: 110px; width: auto;">
            </a>
        <?php endif; ?>
        <?php if ($laporan['tanggal_selesai']): ?>
            <div class="teks-kecil">Selesai <?= esc(tanggal_indonesia($laporan['tanggal_selesai'])) ?></div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($berikut === null): ?>
    <div class="banner-info">Laporan ini sudah selesai dan tidak bisa diubah lagi.</div>
<?php else: ?>
    <form action="<?= site_url('pemilik/kerusakan/' . (int) $laporan['id_laporan'] . '/status') ?>" method="post"
        enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="status" value="<?= esc($berikut, 'attr') ?>">
        <div class="card kartu-panel">
            <h2 class="judul-panel mb-0">Ubah status menjadi "<?= esc(LaporanKerusakanModel::STATUS[$berikut]) ?>"</h2>
            <div>
                <label for="catatan_perbaikan" class="form-label">
                    Catatan perbaikan <?= $berikut === 'selesai' ? '' : '(opsional)' ?>
                </label>
                <textarea id="catatan_perbaikan" name="catatan_perbaikan" rows="4" maxlength="<?= LaporanKerusakanModel::CATATAN_MAKS ?>"
                    class="form-control <?= isset($errors['catatan_perbaikan']) ? 'is-invalid' : '' ?>"
                    placeholder="<?= $berikut === 'selesai' ? 'Apa yang sudah diperbaiki' : 'Mis. menunggu sparepart, teknisi datang besok' ?>"><?= esc(old('catatan_perbaikan', (string) $laporan['catatan_perbaikan'])) ?></textarea>
                <?php if (isset($errors['catatan_perbaikan'])): ?><div class="invalid-feedback d-block"><?= esc($errors['catatan_perbaikan']) ?></div><?php endif; ?>
            </div>
            <div>
                <label for="foto_perbaikan" class="form-label">Foto perbaikan (opsional)</label>
                <input type="file" id="foto_perbaikan" name="foto_perbaikan" accept="image/jpeg,image/png,image/webp"
                    class="form-control <?= isset($errors['foto_perbaikan']) ? 'is-invalid' : '' ?>">
                <?php if (isset($errors['foto_perbaikan'])): ?><div class="invalid-feedback d-block"><?= esc($errors['foto_perbaikan']) ?></div><?php endif; ?>
                <div class="form-text"><?= esc(LaporanKerusakanModel::FOTO_LABEL) ?>.</div>
            </div>
            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary"
                    <?= $berikut === 'selesai' ? 'onclick="return confirm(\'Tandai laporan ini selesai? Status tidak bisa diubah lagi.\');"' : '' ?>>
                    <?= $berikut === 'selesai' ? 'Tandai Selesai' : 'Mulai Proses' ?>
                </button>
            </div>
        </div>
    </form>
<?php endif; ?>
<?= $this->endSection() ?>
