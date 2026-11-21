<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><?= esc($judul) ?></h1>
    <a href="<?= site_url('pemilik/kamar/tambah') ?>" class="btn btn-primary">+ Tambah Kamar</a>
</div>

<?= $this->include('pemilik/kamar/_errors') ?>

<div class="table-responsive">
    <table class="table table-bordered table-hover align-middle bg-white">
        <thead class="table-light">
            <tr>
                <th>No</th>
                <th>Nomor Kamar</th>
                <th>Status</th>
                <th>Harga Sewa / Tahun</th>
                <th>Media</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($kamar)): ?>
                <tr><td colspan="6" class="text-center text-muted">Belum ada kamar.</td></tr>
            <?php endif ?>

            <?php foreach ($kamar as $i => $k): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td class="fw-semibold"><?= esc($k['nomor_kamar']) ?></td>
                    <td>
                        <span class="badge <?= $k['status_kamar'] === 'kosong' ? 'text-bg-success' : 'text-bg-secondary' ?>">
                            <?= esc(ucfirst($k['status_kamar'])) ?>
                        </span>
                    </td>
                    <td>Rp <?= number_format((float) $k['harga_sewa_tahunan'], 0, ',', '.') ?></td>
                    <td><?= (int) $k['jumlah_media'] ?> file</td>
                    <td>
                        <div class="d-flex gap-2">
                            <a href="<?= site_url('pemilik/kamar/' . $k['id_kamar'] . '/ubah') ?>" class="btn btn-sm btn-outline-primary">Ubah</a>
                            <form action="<?= site_url('pemilik/kamar/' . $k['id_kamar'] . '/hapus') ?>" method="post" class="m-0"
                                  onsubmit="return confirm('Yakin hapus kamar <?= esc($k['nomor_kamar'], 'js') ?>? Semua foto dan videonya ikut terhapus.');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
<?= $this->endSection() ?>
