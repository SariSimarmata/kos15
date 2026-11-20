<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar | Kos 15</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container py-5" style="max-width: 460px;">
        <h1 class="h4 mb-4 text-center">Daftar Calon Penghuni</h1>

        <?php foreach ((array) session('errors') as $pesan): ?>
            <div class="alert alert-danger py-2"><?= esc($pesan) ?></div>
        <?php endforeach; ?>

        <form action="<?= site_url('daftar') ?>" method="post" class="card card-body shadow-sm">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="nama" class="form-label">Nama lengkap</label>
                <input type="text" id="nama" name="nama" class="form-control" value="<?= esc(old('nama')) ?>" autofocus>
            </div>
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" id="username" name="username" class="form-control"
                    value="<?= esc(old('username')) ?>">
            </div>
            <div class="mb-3">
                <label for="no_hp" class="form-label">Nomor HP</label>
                <input type="text" id="no_hp" name="no_hp" class="form-control" placeholder="08xxxxxxxxxx"
                    value="<?= esc(old('no_hp')) ?>">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Kata sandi</label>
                <input type="password" id="password" name="password" class="form-control">
            </div>
            <div class="mb-3">
                <label for="konfirmasi_password" class="form-label">Konfirmasi kata sandi</label>
                <input type="password" id="konfirmasi_password" name="konfirmasi_password" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary w-100">Daftar</button>
        </form>

        <p class="text-center mt-3">Sudah punya akun? <a href="<?= site_url('login') ?>">Masuk</a></p>
    </div>
</body>

</html>