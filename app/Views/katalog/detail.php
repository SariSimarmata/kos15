<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
// Hanya media yang filenya benar-benar ada
$galeri = [];
foreach ($media as $m) {
    $url = media_kamar_url($m['file']);
    if ($url !== null) {
        $galeri[] = ['jenis' => $m['jenis'], 'url' => $url];
    }
}
$ringkas = implode(' · ', array_slice($fasilitas, 0, 3));
?>

<nav class="remah" aria-label="Remah roti">
    <a href="<?= site_url('katalog') ?>">Katalog</a><span class="pemisah">/</span>Kamar <?= esc($kamar['nomor_kamar']) ?>
</nav>

<div class="detail-kamar">
    <!-- Galeri -->
    <section class="detail-galeri">
        <div class="foto-kamar galeri-utama" id="galeriUtama">
            <?php if ($galeri === []): ?>
                Foto kamar <?= esc($kamar['nomor_kamar']) ?> belum diunggah
            <?php elseif ($galeri[0]['jenis'] === 'video'): ?>
                <video src="<?= esc($galeri[0]['url'], 'attr') ?>" controls playsinline></video>
            <?php else: ?>
                <img src="<?= esc($galeri[0]['url'], 'attr') ?>" alt="Foto Kamar <?= esc($kamar['nomor_kamar'], 'attr') ?>">
            <?php endif; ?>
        </div>

        <?php if (count($galeri) > 1): ?>
            <div class="row g-2 mt-1">
                <?php $noFoto = 0; foreach ($galeri as $i => $g): ?>
                    <div class="col-3">
                        <button type="button" class="foto-kamar thumb-kamar w-100 p-0 <?= $i === 0 ? 'aktif' : '' ?>"
                                data-jenis="<?= esc($g['jenis'], 'attr') ?>" data-src="<?= esc($g['url'], 'attr') ?>">
                            <?php if ($g['jenis'] === 'video'): ?>
                                <span><i class="bi bi-play-fill" aria-hidden="true"></i> Video</span>
                            <?php else: $noFoto++; ?>
                                <img src="<?= esc($g['url'], 'attr') ?>" alt="Foto <?= $noFoto ?>" loading="lazy">
                            <?php endif; ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Harga dan tombol -->
    <aside class="detail-aside">
        <div class="card p-4 panel-lengket">
            <span class="lencana <?= $tersedia ? 'lencana-hijau' : 'lencana-abu' ?> align-self-start"><?= $tersedia ? 'Tersedia' : 'Terisi' ?></span>
            <h1 class="h3 mt-2 mb-1">Kamar <?= esc($kamar['nomor_kamar']) ?></h1>
            <p class="teks-kecil mb-0"><?= esc($ringkas) ?></p>

            <hr class="my-3">
            <h2 class="h6 mb-2">Rincian Biaya</h2>
            <div class="baris-info"><span>Sewa per tahun</span><span><?= rupiah($harga) ?></span></div>
            <div class="baris-info"><span>Pelunasan saat mulai tinggal</span><span><?= rupiah($sisa) ?></span></div>
            <hr class="my-2">
            <div class="baris-info align-items-center"><span class="fw-medium">Perkiraan DP (50%)</span><span class="angka-dp"><?= rupiah($dp) ?></span></div>
            <p class="teks-kecil mb-3">DP dibayar saat memesan dan diverifikasi pemilik. Masa sewa dihitung sejak kamu mulai menempati kamar.</p>

            <div class="banner-info mb-3">
                <strong class="d-block mb-1">Tagihan bulanan</strong>
                Listrik, air, dan sampah ditagih setiap bulan di luar sewa. Totalnya dikurangi uang menginap tamu, lalu dibagi rata ke semua penghuni.
                <?php if ($info): ?>
                    Tamu menginap <?= rupiah($info['tarif_menginap_per_hari']) ?> per hari, maksimal <?= esc($info['batas_menginap_per_minggu']) ?> hari.
                <?php endif; ?>
            </div>

            <?php if (! $tersedia): ?>
                <button type="button" class="btn btn-primary w-100" disabled>Pesan Kamar</button>
                <button type="button" class="btn btn-outline-primary w-100 mt-2" disabled>Ajukan Survei</button>
                <p class="teks-kecil text-center mt-2 mb-0">Kamar ini sedang terisi. Lihat <a href="<?= site_url('katalog?status=tersedia') ?>">kamar yang tersedia</a>.</p>
            <?php else: ?>
                <?php if ($bisaPesan): ?>
                    <a href="<?= site_url('pemesanan/baru/' . $kamar['id_kamar']) ?>" class="btn btn-primary w-100">Pesan Kamar</a>
                    <?php if (! session('logged_in')): ?>
                        <p class="teks-kecil text-center mt-2 mb-0">Kamu akan diminta masuk dulu sebelum memesan.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="teks-kecil mb-0">Pemesanan kamar hanya untuk akun calon penghuni.</p>
                <?php endif; ?>
                <a href="<?= site_url('katalog/' . $kamar['id_kamar'] . '/survei') ?>" class="btn btn-outline-primary w-100 mt-2">Ajukan Survei</a>
            <?php endif; ?>
        </div>
    </aside>

    <!-- Fasilitas dan peraturan -->
    <div class="detail-info d-flex flex-column gap-3">
        <section class="card p-4">
            <h2 class="h6 mb-3">Fasilitas</h2>
            <?php if ($fasilitas === []): ?>
                <p class="teks-kecil mb-0">Fasilitas belum diisi pemilik.</p>
            <?php else: ?>
                <div>
                    <?php foreach ($fasilitas as $f): ?>
                        <span class="chip-fasilitas"><?= esc($f) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="card p-4">
            <h2 class="h6 mb-3">Peraturan Kos</h2>
            <?php if ($peraturan === []): ?>
                <p class="teks-kecil mb-0">Belum ada peraturan yang diumumkan.</p>
            <?php else: ?>
                <ul class="mb-0 ps-3">
                    <?php foreach ($peraturan as $p): ?>
                        <li class="mb-1"><?= esc($p['isi']) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
document.querySelectorAll('.thumb-kamar[data-src]').forEach(function (tombol) {
    tombol.addEventListener('click', function () {
        var utama = document.getElementById('galeriUtama');
        var video = tombol.dataset.jenis === 'video';
        var el = document.createElement(video ? 'video' : 'img');
        el.src = tombol.dataset.src;
        if (video) { el.controls = true; el.playsInline = true; } else { el.alt = 'Foto kamar'; }
        utama.replaceChildren(el);
        document.querySelectorAll('.thumb-kamar').forEach(function (t) { t.classList.remove('aktif'); });
        tombol.classList.add('aktif');
    });
});
</script>
<?= $this->endSection() ?>
