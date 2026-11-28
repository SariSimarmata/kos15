<?= $this->extend($layout) ?>

<?= $this->section('content') ?>
<?php
/*
 * Pengumuman dan peraturan kos, hanya baca (penghuni dan calon penghuni).
 * Data dari Pengumuman::index(): $pengumuman (terbaru di atas), $peraturan, $info, $waLink, $layout.
 */
?>
<div class="header-halaman">
    <h1 class="judul-halaman mb-1">Pengumuman dan Peraturan</h1>
    <p class="mb-0">Informasi terbaru dari pengelola Kos 15.</p>
</div>

<div class="d-flex flex-column gap-3">
    <section class="card kartu-panel" aria-labelledby="judulPengumuman">
        <h2 class="judul-panel mb-0" id="judulPengumuman">Pengumuman</h2>
        <?php if ($pengumuman === []): ?>
            <div class="kosong-panel">
                <i class="bi bi-megaphone" aria-hidden="true"></i>
                <p class="mb-0">Belum ada pengumuman.</p>
            </div>
        <?php else: ?>
            <?php foreach ($pengumuman as $p): ?>
                <article class="banner-info">
                    <div class="teks-kecil mb-1"><?= esc(tanggal_indonesia($p['tanggal_terbit'])) ?></div>
                    <div style="white-space: pre-line;"><?= esc($p['isi']) ?></div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="card kartu-panel" aria-labelledby="judulPeraturan">
        <h2 class="judul-panel mb-0" id="judulPeraturan">Peraturan Kos</h2>
        <?php if ($peraturan === []): ?>
            <div class="kosong-panel">
                <i class="bi bi-journal-text" aria-hidden="true"></i>
                <p class="mb-0">Belum ada peraturan yang diumumkan.</p>
            </div>
        <?php else: ?>
            <ol class="mb-0 ps-3">
                <?php foreach ($peraturan as $p): ?>
                    <li class="mb-2" style="white-space: pre-line;"><?= esc($p['isi']) ?></li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>

    <?php if ($info['lokasi'] !== '' || $waLink !== null): ?>
        <section class="card kartu-panel" aria-labelledby="judulKontak">
            <h2 class="judul-panel mb-0" id="judulKontak">Hubungi Pengelola</h2>
            <?php if ($info['lokasi'] !== ''): ?>
                <p class="mb-0"><i class="bi bi-geo-alt" aria-hidden="true"></i> <?= esc($info['lokasi']) ?></p>
            <?php endif; ?>
            <?php if ($waLink !== null): ?>
                <div>
                    <a href="<?= esc($waLink, 'attr') ?>" class="btn btn-outline-primary" target="_blank" rel="noopener">
                        <i class="bi bi-whatsapp" aria-hidden="true"></i> WhatsApp <?= esc($info['no_whatsapp']) ?>
                    </a>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
