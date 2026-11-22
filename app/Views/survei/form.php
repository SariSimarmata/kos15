<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$fotoUrl = media_kamar_url($foto);
$salah   = static fn (string $f): string => isset($errors[$f]) ? 'is-invalid' : '';
?>
<nav class="remah" aria-label="Remah roti">
    <a href="<?= site_url('katalog') ?>">Katalog</a><span class="pemisah">/</span>
    <a href="<?= site_url('katalog/' . $kamar['id_kamar']) ?>">Kamar <?= esc($kamar['nomor_kamar']) ?></a><span class="pemisah">/</span>Ajukan Survei
</nav>

<div class="row g-4">
    <div class="col-lg-4 order-2 order-lg-1 d-flex flex-column gap-3">
        <?= view('survei/_ringkasan_kamar', ['kamar' => $kamar, 'fotoUrl' => $fotoUrl]) ?>
        <?= view('survei/_alur') ?>
    </div>

    <div class="col-lg-8 order-1 order-lg-2">
        <div class="card p-4">
            <h1 class="judul-halaman mb-1">Ajukan Survei</h1>
            <p class="teks-kecil mb-4">Tanpa perlu login. Isi data di bawah untuk kamar yang ingin kamu lihat.</p>

            <?php if ($errors !== []): ?>
                <div class="kotak-merah mb-3" role="alert">Periksa lagi isian yang ditandai merah.</div>
            <?php endif; ?>

            <form action="<?= site_url('katalog/' . $kamar['id_kamar'] . '/survei') ?>" method="post" novalidate>
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label for="nama_calon" class="form-label">Nama lengkap</label>
                    <input type="text" id="nama_calon" name="nama_calon" maxlength="100" required autocomplete="name"
                           class="form-control <?= $salah('nama_calon') ?>" placeholder="Nama sesuai identitas"
                           value="<?= esc(old('nama_calon'), 'attr') ?>">
                    <?php if (isset($errors['nama_calon'])): ?><div class="invalid-feedback"><?= esc($errors['nama_calon']) ?></div><?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="no_hp" class="form-label">Nomor HP</label>
                    <input type="tel" id="no_hp" name="no_hp" maxlength="16" required inputmode="numeric" autocomplete="tel"
                           class="form-control <?= $salah('no_hp') ?>" placeholder="08xxxxxxxxxx"
                           value="<?= esc(old('no_hp'), 'attr') ?>">
                    <?php if (isset($errors['no_hp'])): ?><div class="invalid-feedback"><?= esc($errors['no_hp']) ?></div><?php endif; ?>
                    <div class="teks-kecil mt-1">Pemilik menghubungi nomor ini untuk mengonfirmasi jadwal.</div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-sm-6">
                        <label for="tanggal_survei" class="form-label">Tanggal survei</label>
                        <input type="date" id="tanggal_survei" name="tanggal_survei" required
                               min="<?= esc($tglMin, 'attr') ?>" max="<?= esc($tglMaks, 'attr') ?>"
                               class="form-control <?= $salah('tanggal_survei') ?>"
                               value="<?= esc(old('tanggal_survei'), 'attr') ?>">
                        <?php if (isset($errors['tanggal_survei'])): ?><div class="invalid-feedback"><?= esc($errors['tanggal_survei']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-sm-6">
                        <label for="waktu_survei" class="form-label">Jam survei</label>
                        <input type="time" id="waktu_survei" name="waktu_survei" required
                               class="form-control <?= $salah('waktu_survei') ?>"
                               value="<?= esc(old('waktu_survei'), 'attr') ?>">
                        <?php if (isset($errors['waktu_survei'])): ?><div class="invalid-feedback"><?= esc($errors['waktu_survei']) ?></div><?php endif; ?>
                    </div>
                </div>

                <div class="banner-info mb-4">Pengajuan tersimpan di sistem. Setelah itu pesan pengajuan survei dikirim ke pemilik lewat WhatsApp.</div>

                <div class="d-flex flex-column-reverse flex-sm-row gap-2">
                    <a href="<?= site_url('katalog/' . $kamar['id_kamar']) ?>" class="btn btn-outline-primary px-4">Batal</a>
                    <button type="submit" class="btn btn-primary flex-grow-1">Ajukan Survei</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
