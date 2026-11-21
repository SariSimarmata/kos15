<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card mx-auto text-center p-5" style="max-width: 520px;">
    <span class="lencana lencana-merah align-self-center mb-3">403</span>
    <h1 class="h4">Akses ditolak</h1>
    <p class="mb-4">Halaman ini bukan untuk peran akun kamu.</p>
    <a href="<?= site_url('login') ?>" class="btn btn-primary align-self-center">Kembali ke beranda saya</a>
</div>
<?= $this->endSection() ?>
