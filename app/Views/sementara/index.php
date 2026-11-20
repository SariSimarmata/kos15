<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<!-- Halaman sementara (Kartu 5). Diganti oleh kartu pemilik halaman ini. -->
<h1 class="judul-halaman"><?= esc($judul) ?></h1>
<div class="banner-info mb-4">Halaman ini masih kosong dan akan dikerjakan di <?= esc($kartu) ?>.</div>
<div class="card p-4">
    <span class="lencana lencana-kuning align-self-start mb-2">Segera</span>
    <p class="mb-0">Isi halaman menyusul.</p>
</div>
<?= $this->endSection() ?>
