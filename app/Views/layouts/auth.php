<!DOCTYPE html>
<html lang="id">

<head>
    <?= $this->include('partials/head') ?>
</head>

<body>
    <main class="auth">
        <!-- Panel kiri: identitas kos -->
        <section class="auth-kiri">
            <a href="<?= site_url('katalog') ?>" class="logo-kos besar">15</a>
            <h2 class="nama-kos">Kos 15</h2>
            <p class="slogan"><?= esc($slogan ?? 'Nyaman seperti di rumah sendiri') ?></p>
            <p class="sub-slogan mb-0"><?= esc($subSlogan ?? 'Kelola kos dengan tertib dan tenang') ?></p>
        </section>

        <!-- Panel kanan: form -->
        <section class="auth-kanan">
            <div class="auth-kartu">
                <?= $this->include('partials/flash') ?>
                <?= $this->renderSection('content') ?>
            </div>
        </section>
    </main>

    <script>
        // Tombol "Tampilkan" pada kolom kata sandi
        document.querySelectorAll('.tombol-tampil').forEach(function (tombol) {
            tombol.addEventListener('click', function () {
                var input = tombol.previousElementSibling;
                var tampil = input.type === 'password';
                input.type = tampil ? 'text' : 'password';
                tombol.textContent = tampil ? 'Sembunyikan' : 'Tampilkan';
            });
        });
    </script>
</body>

</html>
