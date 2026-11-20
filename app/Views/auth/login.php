<?= $this->extend('layouts/auth') ?>

<?= $this->section('content') ?>
<h1>Masuk ke Kos 15</h1>

<form action="<?= site_url('login') ?>" method="post" novalidate>
    <?= csrf_field() ?>

    <div class="mb-3">
        <label for="username" class="form-label">Username</label>
        <input type="text" id="username" name="username" class="form-control" placeholder="Masukkan username"
            value="<?= esc(old('username')) ?>" autocomplete="username" autofocus>
    </div>

    <div class="mb-4">
        <label for="password" class="form-label">Kata sandi</label>
        <div class="sandi-wrap">
            <input type="password" id="password" name="password" class="form-control"
                placeholder="Masukkan kata sandi" autocomplete="current-password">
            <button type="button" class="tombol-tampil">Tampilkan</button>
        </div>
    </div>

    <button type="submit" class="btn btn-primary w-100">Masuk</button>
</form>

<p class="tautan-bawah">Belum punya akun? <a href="<?= site_url('daftar') ?>">Daftar</a></p>
<?= $this->endSection() ?>
