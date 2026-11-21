<?php
$ubah   = $kamar !== null;
$action = $ubah ? site_url('pemilik/kamar/' . $kamar['id_kamar'] . '/perbarui') : site_url('pemilik/kamar/simpan');
$nilai  = static fn (string $kolom) => (string) old($kolom, $kamar[$kolom] ?? '');
$harga  = $nilai('harga_sewa_tahunan');
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<a href="<?= site_url('pemilik/kamar') ?>" class="text-decoration-none">&larr; Kembali ke daftar kamar</a>
<h1 class="h3 mt-2 mb-3"><?= esc($judul) ?></h1>

<?= $this->include('pemilik/kamar/_errors') ?>

<!-- ===== Data kamar ===== -->
<div class="card mb-4">
    <div class="card-body">
        <form action="<?= $action ?>" method="post">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="nomor_kamar" class="form-label">Nomor Kamar</label>
                <input type="text" class="form-control" id="nomor_kamar" name="nomor_kamar" maxlength="10" required
                       value="<?= esc($nilai('nomor_kamar')) ?>" placeholder="contoh: K-06">
            </div>

            <div class="mb-3">
                <label for="fasilitas" class="form-label">Fasilitas</label>
                <textarea class="form-control" id="fasilitas" name="fasilitas" rows="3" required
                          placeholder="contoh: Kasur, lemari, meja belajar, kamar mandi dalam"><?= esc($nilai('fasilitas')) ?></textarea>
            </div>

            <div class="mb-3">
                <label for="harga_sewa_tahunan" class="form-label">Harga Sewa Tahunan (Rp)</label>
                <input type="number" class="form-control" id="harga_sewa_tahunan" name="harga_sewa_tahunan" min="1" step="1" required
                       value="<?= $harga !== '' ? esc((string) (int) $harga) : '' ?>" placeholder="contoh: 7200000">
                <div class="form-text">Tulis angka saja tanpa titik.</div>
            </div>

            <div class="mb-3">
                <label for="status_kamar" class="form-label">Status Kamar</label>
                <select class="form-select" id="status_kamar" name="status_kamar" required>
                    <?php foreach (['kosong' => 'Kosong', 'terisi' => 'Terisi'] as $val => $teks): ?>
                        <option value="<?= $val ?>" <?= $nilai('status_kamar') === $val ? 'selected' : '' ?>><?= $teks ?></option>
                    <?php endforeach ?>
                </select>
            </div>

            <button type="submit" class="btn btn-primary"><?= $ubah ? 'Simpan Perubahan' : 'Simpan Kamar' ?></button>
        </form>
    </div>
</div>

<?php if ($ubah): ?>
    <!-- ===== Foto & video ===== -->
    <div class="card">
        <div class="card-body">
            <h2 class="h5">Foto dan Video</h2>

            <form action="<?= site_url('pemilik/kamar/' . $kamar['id_kamar'] . '/media') ?>" method="post" enctype="multipart/form-data" class="mb-3">
                <?= csrf_field() ?>
                <div class="input-group">
                    <input type="file" class="form-control" name="media[]" multiple required
                           accept="image/jpeg,image/png,image/webp,video/mp4,video/webm">
                    <button type="submit" class="btn btn-primary">Unggah</button>
                </div>
                <div class="form-text">
                    Foto: <?= esc($aturan['foto']['label']) ?>. Video: <?= esc($aturan['video']['label']) ?>.
                    Bisa pilih beberapa file sekaligus. Media pertama tampil di katalog.
                </div>
            </form>

            <?php if (empty($media)): ?>
                <p class="text-muted fst-italic mb-0">Belum ada foto atau video.</p>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($media as $i => $m): ?>
                        <?php $url = base_url('uploads/kamar/' . $m['file']) ?>
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="card h-100">
                                <?php if ($m['jenis'] === 'foto'): ?>
                                    <img src="<?= esc($url) ?>" class="card-img-top" style="height:140px;object-fit:cover"
                                         alt="Foto kamar <?= esc($kamar['nomor_kamar']) ?>">
                                <?php else: ?>
                                    <video src="<?= esc($url) ?>" class="card-img-top bg-dark" style="height:140px;object-fit:cover"
                                           controls preload="metadata"></video>
                                <?php endif ?>
                                <div class="card-body p-2 d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <?= esc(ucfirst($m['jenis'])) ?>
                                        <?php if ($i === 0): ?><span class="badge text-bg-info">Katalog</span><?php endif ?>
                                    </small>
                                    <form action="<?= site_url('pemilik/media/' . $m['id_media'] . '/hapus') ?>" method="post" class="m-0"
                                          onsubmit="return confirm('Hapus <?= esc($m['jenis'], 'js') ?> ini?');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </div>
    </div>
<?php endif ?>
<?= $this->endSection() ?>
