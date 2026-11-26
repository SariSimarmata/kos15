<?= $this->extend('layouts/pemilik') ?>

<?= $this->section('content') ?>
<?php
/*
 * Form tambah dan ubah penghuni (Kartu 4 Sprint 4, KF-03).
 * Tambah dipakai untuk mendaftarkan penghuni lama: akun, kamar, dan masa sewa sekaligus.
 * Data dari Pemilik\Penghuni: $penghuni (null saat tambah), $kamar (kamar yang boleh dipilih).
 */
use App\Models\SewaModel;

$ubah    = $penghuni !== null;
$adaSewa = $ubah && $penghuni['id_sewa'] !== null;
$action  = $ubah
    ? site_url('pemilik/penghuni/' . $penghuni['id_pengguna'] . '/perbarui')
    : site_url('pemilik/penghuni/simpan');
$errors  = (array) session('errors');
$nilai   = static fn (string $kolom, $bawaan = '') => (string) old($kolom, $penghuni[$kolom] ?? $bawaan);
$salah   = static fn (string $kolom) => isset($errors[$kolom]) ? 'is-invalid' : '';
$pesan   = static fn (string $kolom) => isset($errors[$kolom])
    ? '<div class="invalid-feedback d-block">' . esc($errors[$kolom]) . '</div>' : '';

$statusPilihan = $ubah ? SewaModel::STATUS : array_intersect_key(SewaModel::STATUS, array_flip(SewaModel::STATUS_BERJALAN));
?>
<div class="header-halaman">
    <a href="<?= site_url('pemilik/penghuni') ?>" class="teks-kecil">&larr; Kembali ke data penghuni</a>
    <h1 class="judul-halaman mt-2 mb-1"><?= esc($judul) ?></h1>
    <p class="mb-0">
        <?= $ubah
            ? 'Ubah akun, kamar, atau masa sewa ' . esc($penghuni['nama']) . '.'
            : 'Daftarkan penghuni lama yang sudah tinggal sebelum sistem dipakai.' ?>
    </p>
</div>

<form action="<?= $action ?>" method="post" novalidate class="form-penghuni">
    <?= csrf_field() ?>

    <div class="card kartu-panel">
        <h2 class="judul-panel mb-0">Akun penghuni</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <label for="nama" class="form-label">Nama lengkap</label>
                <input type="text" id="nama" name="nama" class="form-control <?= $salah('nama') ?>"
                    value="<?= esc($nilai('nama')) ?>" placeholder="Nama sesuai identitas">
                <?= $pesan('nama') ?>
            </div>
            <div class="col-md-6">
                <label for="no_hp" class="form-label">Nomor HP</label>
                <input type="tel" id="no_hp" name="no_hp" class="form-control <?= $salah('no_hp') ?>"
                    value="<?= esc($nilai('no_hp')) ?>" placeholder="08xxxxxxxxxx">
                <?= $pesan('no_hp') ?>
            </div>
            <div class="col-md-6">
                <label for="username" class="form-label">Username</label>
                <input type="text" id="username" name="username" class="form-control <?= $salah('username') ?>"
                    value="<?= esc($nilai('username')) ?>" placeholder="Huruf dan angka, minimal 4 karakter" autocomplete="off">
                <?= $pesan('username') ?>
            </div>
            <div class="col-md-6">
                <label for="password" class="form-label"><?= $ubah ? 'Kata sandi baru' : 'Kata sandi awal' ?></label>
                <input type="password" id="password" name="password" class="form-control <?= $salah('password') ?>"
                    placeholder="<?= $ubah ? 'Kosongkan jika tidak diganti' : 'Minimal 8 karakter' ?>" autocomplete="new-password">
                <?= $pesan('password') ?>
                <?php if (! $ubah): ?>
                    <div class="form-text">Berikan kata sandi ini ke penghuni agar bisa login.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($ubah && ! $adaSewa): ?>
        <div class="banner-info mt-3">Penghuni ini belum punya data sewa, jadi hanya akunnya yang bisa diubah.</div>
    <?php else: ?>
        <div class="card kartu-panel mt-3">
            <h2 class="judul-panel mb-0">Kamar dan masa sewa</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="id_kamar" class="form-label">Kamar</label>
                    <select id="id_kamar" name="id_kamar" class="form-select <?= $salah('id_kamar') ?>">
                        <option value="">Pilih kamar</option>
                        <?php foreach ($kamar as $k): ?>
                            <option value="<?= $k['id_kamar'] ?>" <?= $nilai('id_kamar') === (string) $k['id_kamar'] ? 'selected' : '' ?>>
                                <?= esc($k['nomor_kamar']) ?> · Rp <?= number_format((float) $k['harga_sewa_tahunan'], 0, ',', '.') ?>/tahun
                                <?= $k['status_kamar'] === 'terisi' ? '(kamar saat ini)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= $pesan('id_kamar') ?>
                    <?php if ($kamar === []): ?>
                        <div class="form-text text-danger">Tidak ada kamar kosong.</div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label for="status_sewa" class="form-label">Status sewa</label>
                    <select id="status_sewa" name="status_sewa" class="form-select <?= $salah('status_sewa') ?>">
                        <?php foreach ($statusPilihan as $nilaiStatus => $label): ?>
                            <option value="<?= $nilaiStatus ?>" <?= $nilai('status_sewa', 'aktif') === $nilaiStatus ? 'selected' : '' ?>>
                                <?= esc($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= $pesan('status_sewa') ?>
                    <div class="form-text">
                        <?= $ubah
                            ? 'Status selesai atau batal mengosongkan kamar.'
                            : 'Pilih aktif jika penghuni lama sudah membayar sewa.' ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="tanggal_mulai" class="form-label">Tanggal mulai sewa</label>
                    <input type="date" id="tanggal_mulai" name="tanggal_mulai" class="form-control <?= $salah('tanggal_mulai') ?>"
                        value="<?= esc($nilai('tanggal_mulai')) ?>">
                    <?= $pesan('tanggal_mulai') ?>
                </div>
                <div class="col-md-6">
                    <label for="tanggal_berakhir" class="form-label">Tanggal berakhir sewa</label>
                    <?php if ($ubah): ?>
                        <input type="date" id="tanggal_berakhir" name="tanggal_berakhir"
                            class="form-control <?= $salah('tanggal_berakhir') ?>" value="<?= esc($nilai('tanggal_berakhir')) ?>">
                        <?= $pesan('tanggal_berakhir') ?>
                    <?php else: ?>
                        <input type="text" id="tanggal_berakhir" class="form-control" value="Otomatis satu tahun setelah tanggal mulai" disabled>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="d-flex gap-2 justify-content-end mt-3">
        <a href="<?= site_url('pemilik/penghuni') ?>" class="btn btn-outline-primary">Batal</a>
        <button type="submit" class="btn btn-primary"><?= $ubah ? 'Simpan Perubahan' : 'Tambah Penghuni' ?></button>
    </div>
</form>
<?= $this->endSection() ?>
