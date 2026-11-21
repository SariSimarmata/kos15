<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $errors = (array) session('errors'); ?>

<nav class="remah">
    <a href="<?= site_url('katalog') ?>">Katalog</a><span class="pemisah">/</span>Pesan Kamar
</nav>

<h1 class="judul-halaman">Pesan Kamar</h1>

<?php if (!empty($infoKamarTerisi)): ?>
    <div class="kotak-merah mb-3"><?= esc($infoKamarTerisi) ?></div>
<?php endif; ?>

<?php if (empty($kamarKosong)): ?>
    <div class="banner-info">Saat ini belum ada kamar kosong. Silakan cek katalog lagi nanti.</div>
<?php else: ?>
    <div class="card p-4" style="max-width: 560px;">
        <form action="<?= site_url('pemesanan') ?>" method="post" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="id_kamar" class="form-label">Kamar</label>
                <select id="id_kamar" name="id_kamar" class="form-select <?= isset($errors['id_kamar']) ? 'is-invalid' : '' ?>">
                    <option value="">Pilih kamar kosong</option>
                    <?php foreach ($kamarKosong as $k): ?>
                        <option value="<?= (int) $k['id_kamar'] ?>" <?= (int) $k['id_kamar'] === (int) $terpilih ? 'selected' : '' ?>>
                            <?= esc($k['nomor_kamar']) ?> · Rp <?= number_format((float) $k['harga_sewa_tahunan'], 0, ',', '.') ?> / tahun
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['id_kamar'])): ?>
                    <div class="invalid-feedback d-block"><?= esc($errors['id_kamar']) ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="tanggal_mulai_sewa" class="form-label">Tanggal mulai sewa</label>
                <input type="date" id="tanggal_mulai_sewa" name="tanggal_mulai_sewa"
                    class="form-control <?= isset($errors['tanggal_mulai_sewa']) ? 'is-invalid' : '' ?>"
                    min="<?= esc($tanggalMinimal) ?>" value="<?= esc(old('tanggal_mulai_sewa')) ?>">
                <?php if (isset($errors['tanggal_mulai_sewa'])): ?>
                    <div class="invalid-feedback d-block"><?= esc($errors['tanggal_mulai_sewa']) ?></div>
                <?php endif; ?>
            </div>

            <p class="teks-kecil mb-4">Sewa berlaku satu tahun sejak tanggal mulai. Pemesanan baru berlaku setelah disetujui pemilik.</p>

            <button type="submit" class="btn btn-primary w-100">Kirim Pemesanan</button>
        </form>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>
