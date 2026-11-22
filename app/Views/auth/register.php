<?= $this->extend('layouts/auth') ?>

<?= $this->section('content') ?>
<?php $errors = (array) session('errors'); ?>
<h1>Daftar di Kos 15</h1>

<form action="<?= site_url('daftar') ?>" method="post" novalidate>
    <?= csrf_field() ?>

    <?php
    // nama kolom => [label, tipe, placeholder, autocomplete]
    $kolom = [
        'nama'                => ['Nama lengkap', 'text', 'Nama sesuai identitas', 'name'],
        'no_hp'               => ['Nomor WhatsApp', 'tel', '08xxxxxxxxxx', 'tel'],
        'username'            => ['Username', 'text', 'Huruf dan angka, minimal 4 karakter', 'username'],
        'password'            => ['Kata sandi', 'password', 'Minimal 8 karakter', 'new-password'],
        'konfirmasi_password' => ['Konfirmasi kata sandi', 'password', 'Ulangi kata sandi', 'new-password'],
    ];
    ?>
    <?php foreach ($kolom as $nama => [$label, $tipe, $placeholder, $auto]): ?>
        <?php $salah = isset($errors[$nama]); ?>
        <div class="mb-3">
            <label for="<?= $nama ?>" class="form-label"><?= $label ?></label>
            <?php if ($tipe === 'password'): ?>
                <div class="sandi-wrap">
                    <input type="password" id="<?= $nama ?>" name="<?= $nama ?>" placeholder="<?= $placeholder ?>"
                        autocomplete="<?= $auto ?>" class="form-control <?= $salah ? 'is-invalid' : '' ?>">
                    <button type="button" class="tombol-tampil">Tampilkan</button>
                </div>
            <?php else: ?>
                <input type="<?= $tipe ?>" id="<?= $nama ?>" name="<?= $nama ?>" placeholder="<?= $placeholder ?>"
                    autocomplete="<?= $auto ?>" value="<?= esc(old($nama)) ?>"
                    class="form-control <?= $salah ? 'is-invalid' : '' ?>">
            <?php endif; ?>
            <?php if ($salah): ?>
                <div class="invalid-feedback d-block"><?= esc($errors[$nama]) ?></div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <p class="catatan mb-4">Nomor WhatsApp dipakai pemilik untuk menghubungi kamu soal survei dan pemesanan.</p>

    <button type="submit" class="btn btn-primary w-100">Buat Akun</button>
</form>

<p class="tautan-bawah">Sudah punya akun? <a href="<?= site_url('login') ?>">Masuk</a></p>
<?= $this->endSection() ?>
