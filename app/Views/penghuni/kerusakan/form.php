<?= $this->extend('layouts/penghuni') ?>

<?= $this->section('content') ?>
<?php
/*
 * Form laporan kerusakan. Data dari Penghuni\Kerusakan::baru(): $kamar (dari sewa aktif, atau null).
 * Kamar hanya ditampilkan, tidak ada isian kamar di form: server mengambilnya dari sewa aktif.
 */
use App\Models\LaporanKerusakanModel;

$errors = (array) session('errors');
?>
<div class="header-halaman">
    <a href="<?= site_url('penghuni/kerusakan') ?>" class="teks-kecil">&larr; Kembali ke laporan kerusakan</a>
    <h1 class="judul-halaman mt-2 mb-1">Buat Laporan Kerusakan</h1>
    <p class="mb-0">Jelaskan kerusakannya dan sertakan foto agar cepat ditangani.</p>
</div>

<?php if ($kamar === null): ?>
    <div class="banner-info">Laporan hanya bisa dibuat oleh penghuni dengan sewa aktif. Hubungi pemilik bila ini keliru.</div>
<?php else: ?>
    <form action="<?= site_url('penghuni/kerusakan/simpan') ?>" method="post" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <div class="card kartu-panel">
            <div>
                <label for="kamar" class="form-label">Kamar</label>
                <input type="text" id="kamar" class="form-control" value="Kamar <?= esc($kamar['nomor_kamar'], 'attr') ?>" disabled>
                <div class="form-text">Diambil otomatis dari sewa aktifmu.</div>
            </div>
            <div>
                <label for="deskripsi" class="form-label">Deskripsi kerusakan</label>
                <textarea id="deskripsi" name="deskripsi" rows="5" maxlength="<?= LaporanKerusakanModel::DESKRIPSI_MAKS ?>"
                    class="form-control <?= isset($errors['deskripsi']) ? 'is-invalid' : '' ?>"
                    placeholder="Contoh: Keran kamar mandi bocor terus sejak kemarin."><?= esc(old('deskripsi')) ?></textarea>
                <?php if (isset($errors['deskripsi'])): ?><div class="invalid-feedback d-block"><?= esc($errors['deskripsi']) ?></div><?php endif; ?>
                <div class="form-text">Minimal <?= LaporanKerusakanModel::DESKRIPSI_MIN ?> karakter.</div>
            </div>
            <div>
                <label for="foto" class="form-label">Foto kerusakan</label>
                <input type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp"
                    class="form-control <?= isset($errors['foto']) ? 'is-invalid' : '' ?>">
                <?php if (isset($errors['foto'])): ?><div class="invalid-feedback d-block"><?= esc($errors['foto']) ?></div><?php endif; ?>
                <div class="form-text"><?= esc(LaporanKerusakanModel::FOTO_LABEL) ?>. Pilih ulang fotonya bila formulir gagal dikirim.</div>
            </div>
        </div>
        <div class="d-flex gap-2 justify-content-end mt-3">
            <a href="<?= site_url('penghuni/kerusakan') ?>" class="btn btn-outline-primary">Batal</a>
            <button type="submit" class="btn btn-primary">Kirim Laporan</button>
        </div>
    </form>
<?php endif; ?>
<?= $this->endSection() ?>
