<?= $this->extend('layouts/main') ?>

<?= $this->section('pita') ?>
<div class="pita-judul">
    <div class="container">
        <h1>Temukan kamar yang nyaman</h1>
        <p><?= esc($jumlah['tersedia']) ?> kamar tersedia saat ini</p>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="d-flex flex-wrap gap-2 mb-4" aria-label="Filter status kamar">
    <?php foreach (['semua' => 'Semua', 'tersedia' => 'Tersedia', 'terisi' => 'Terisi'] as $kunci => $label): ?>
        <a href="<?= site_url('katalog?status=' . $kunci) ?>"
           class="chip-filter <?= $filter === $kunci ? 'aktif' : '' ?>"
           <?= $filter === $kunci ? 'aria-current="true"' : '' ?>>
            <?= $label ?> <span class="angka-filter"><?= esc($jumlah[$kunci]) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($kamar === []): ?>
    <div class="banner-info">
        Belum ada kamar dengan status ini. <a href="<?= site_url('katalog') ?>">Lihat semua kamar</a>.
    </div>
<?php else: ?>
    <div class="row g-3 g-lg-4">
        <?php foreach ($kamar as $k):
            $tersedia = $k['status_kamar'] === 'kosong';
            $media    = media_kamar_url($k['media_pertama'] ?? null);
            $isVideo  = ($k['jenis_media_pertama'] ?? '') === 'video';
            $urlKamar = site_url('katalog/' . $k['id_kamar']);
        ?>
            <div class="col-6 col-md-4 col-xl-3">
                <div class="card h-100 kartu-katalog <?= $tersedia ? '' : 'terisi' ?>">
                    <a href="<?= $urlKamar ?>" tabindex="-1" aria-hidden="true">
                        <?php if ($media && $isVideo): ?>
                            <video src="<?= esc($media, 'attr') ?>#t=0.5" class="foto-kamar" muted playsinline preload="metadata"></video>
                        <?php elseif ($media): ?>
                            <img src="<?= esc($media, 'attr') ?>" class="foto-kamar" alt="" loading="lazy">
                        <?php else: ?>
                            <div class="foto-kamar">Foto kamar <?= esc($k['nomor_kamar']) ?></div>
                        <?php endif; ?>
                    </a>
                    <div class="card-body d-flex flex-column gap-2 p-3">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-1">
                            <h2 class="h6 mb-0 text-nowrap">Kamar <?= esc($k['nomor_kamar']) ?></h2>
                            <span class="lencana <?= $tersedia ? 'lencana-hijau' : 'lencana-abu' ?>"><?= $tersedia ? 'Tersedia' : 'Terisi' ?></span>
                        </div>
                        <p class="teks-kecil mb-0 d-none d-sm-block"><?= esc(implode(' · ', array_slice(daftar_fasilitas($k['fasilitas']), 0, 3))) ?></p>
                        <span class="lencana lencana-krem align-self-start"><?= rupiah($k['harga_sewa_tahunan']) ?> / tahun</span>
                        <a href="<?= $urlKamar ?>" class="btn btn-primary w-100 mt-auto">Lihat Detail</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>
