<?= $this->extend('layouts/pemilik') ?>

<?= $this->section('content') ?>
<?php
/*
 * Form tambah dan ubah pengumuman / peraturan.
 * Data dari Pemilik\Pengumuman: $item (jenis, isi), $ubah (true saat ubah).
 */
use App\Models\PengumumanModel;

$action = $ubah
    ? site_url('pemilik/pengumuman/' . $item['id_pengumuman'] . '/perbarui')
    : site_url('pemilik/pengumuman/simpan');
$errors = (array) session('errors');
$jenis  = (string) old('jenis', $item['jenis']);
$isi    = (string) old('isi', $item['isi']);
?>
<div class="header-halaman">
    <a href="<?= site_url('pemilik/pengumuman') ?>" class="teks-kecil">&larr; Kembali ke daftar</a>
    <h1 class="judul-halaman mt-2 mb-1"><?= esc($judul) ?></h1>
    <p class="mb-0"><?= $ubah ? 'Tanggal terbit tidak berubah.' : 'Akan langsung tampil bagi penghuni dan calon penghuni.' ?></p>
</div>

<form action="<?= $action ?>" method="post" novalidate>
    <?= csrf_field() ?>
    <div class="card kartu-panel">
        <div>
            <label for="jenis" class="form-label">Jenis</label>
            <select id="jenis" name="jenis" class="form-select <?= isset($errors['jenis']) ? 'is-invalid' : '' ?>">
                <?php foreach (PengumumanModel::JENIS as $kunci => $label): ?>
                    <option value="<?= $kunci ?>" <?= $jenis === $kunci ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['jenis'])): ?><div class="invalid-feedback d-block"><?= esc($errors['jenis']) ?></div><?php endif; ?>
            <div class="form-text">Peraturan tampil juga di halaman detail kamar.</div>
        </div>
        <div>
            <label for="isi" class="form-label">Isi</label>
            <textarea id="isi" name="isi" rows="6" maxlength="<?= PengumumanModel::ISI_MAKS ?>"
                class="form-control <?= isset($errors['isi']) ? 'is-invalid' : '' ?>"
                placeholder="Tulis pengumuman atau peraturan"><?= esc($isi) ?></textarea>
            <?php if (isset($errors['isi'])): ?><div class="invalid-feedback d-block"><?= esc($errors['isi']) ?></div><?php endif; ?>
            <div class="form-text">Minimal <?= PengumumanModel::ISI_MIN ?> dan maksimal <?= PengumumanModel::ISI_MAKS ?> karakter.</div>
        </div>
    </div>
    <div class="d-flex gap-2 justify-content-end mt-3">
        <a href="<?= site_url('pemilik/pengumuman') ?>" class="btn btn-outline-primary">Batal</a>
        <button type="submit" class="btn btn-primary"><?= $ubah ? 'Simpan Perubahan' : 'Terbitkan' ?></button>
    </div>
</form>
<?= $this->endSection() ?>
