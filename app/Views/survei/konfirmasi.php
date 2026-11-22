<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row g-4">
    <div class="col-lg-4 order-2 order-lg-1 d-flex flex-column gap-3">
        <?= view('survei/_ringkasan_kamar', ['kamar' => $kamar, 'fotoUrl' => media_kamar_url($foto)]) ?>
        <?= view('survei/_alur') ?>
    </div>

    <div class="col-lg-8 order-1 order-lg-2">
        <div class="card p-4 text-center">
            <span class="ikon-sukses mx-auto mb-3"><i class="bi bi-check-lg" aria-hidden="true"></i></span>
            <h1 class="judul-halaman mb-1">Pengajuan survei tersimpan</h1>
            <p class="teks-kecil mb-4">Pastikan pesan terkirim lewat WhatsApp agar jadwal surveimu dikonfirmasi.</p>

            <div class="kotak-ringkasan text-start mb-3">
                <div class="baris-info"><span>Kamar</span><span>Kamar <?= esc($kamar['nomor_kamar']) ?></span></div>
                <div class="baris-info"><span>Nama</span><span><?= esc($survei['nama_calon']) ?></span></div>
                <div class="baris-info"><span>Nomor HP</span><span><?= esc($survei['no_hp']) ?></span></div>
                <div class="baris-info"><span>Tanggal survei</span><span><?= esc(tanggal_panjang($survei['tanggal_survei'], true)) ?></span></div>
                <div class="baris-info"><span>Jam survei</span><span><?= esc(str_replace(':', '.', $jam)) ?> WIB</span></div>
            </div>

            <div class="teks-kecil text-start mb-1">Pratinjau pesan WhatsApp</div>
            <div class="banner-info text-start mb-3"><?= esc($pesan) ?></div>

            <?php if ($waLink): ?>
                <a href="<?= esc($waLink, 'attr') ?>" target="_blank" rel="noopener" class="btn btn-primary w-100">
                    <i class="bi bi-whatsapp me-1" aria-hidden="true"></i> Hubungi Pemilik via WhatsApp
                </a>
            <?php else: ?>
                <div class="kotak-merah">Nomor WhatsApp pemilik belum diatur di Info Kos.</div>
            <?php endif; ?>
            <a href="<?= site_url('katalog') ?>" class="btn btn-outline-primary w-100 mt-2">Kembali ke Katalog</a>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
