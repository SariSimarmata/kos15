<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk | Kos 15</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container py-5" style="max-width: 420px;">
        <h1 class="h4 mb-4 text-center">Masuk ke Kos 15</h1>

            <?php if (session('sukses')): ?>
            <div class="alert alert-success py-2">
                <?= esc(session('sukses')) ?>
                    </div>
            <?php endif; ?>
     <?php foreach ((array) session('errors') as $pesan): ?>
            <div class="alert alert-danger py-2">
                        <?= esc($pesan) ?>
                </div>
        <?php endforeach; ?>
    
        <form action="<?= site_url('login') ?>" method="post" class="card card-body shadow-sm">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" id="username" name="username" class="form-control"
                    value="<?= esc(old('username')) ?>" autofocus>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Kata sandi</label>
                <input type="password" id="password" name="password" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary w-100">Masuk</button>
        </form>

        <p class="text-center mt-3">Belum punya akun? <a href="<?= site_url('daftar') ?>">Daftar</a></p>
    </div>
</body>

</html>