<?= $this->extend('layouts/pemilik') ?>

<?= $this->section('content') ?>
<?php
/*
 * Daftar pengumuman dan peraturan untuk pemilik.
 * Data dari Pemilik\Pengumuman::index(): $daftar, $jenis (filter aktif), $jumlah (per jenis).
 */
use App\Models\PengumumanModel;

$lencana = ['pengumuman' => 'lencana-biru', 'peraturan' => 'lencana-kuning'];
$total   = array_sum($jumlah);
?>
<div class="header-halaman d-flex flex-wrap justify-content-between align-items-end gap-3">
    <div>
        <h1 class="judul-halaman mb-1">Pengumuman dan Peraturan</h1>
        <p class="mb-0">Dibaca oleh penghuni dan calon penghuni. Peraturan juga tampil di halaman detail kamar.</p>
    </div>
    <a href="<?= site_url('pemilik/pengumuman/tambah' . ($jenis !== '' ? '?jenis=' . $jenis : '')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah
    </a>
</div>

<div class="card kartu-panel">
    <nav class="tab-status" aria-label="Filter jenis">
        <a href="<?= site_url('pemilik/pengumuman') ?>" class="<?= $jenis === '' ? 'aktif' : '' ?>" <?= $jenis === '' ? 'aria-current="page"' : '' ?>>
            Semua <span class="angka"><?= $total ?></span>
        </a>
        <?php foreach (PengumumanModel::JENIS as $kunci => $label): ?>
            <a href="<?= site_url('pemilik/pengumuman?jenis=' . $kunci) ?>" class="<?= $jenis === $kunci ? 'aktif' : '' ?>"
                <?= $jenis === $kunci ? 'aria-current="page"' : '' ?>>
                <?= esc($label) ?> <span class="angka"><?= (int) $jumlah[$kunci] ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($daftar === []): ?>
        <div class="kosong-panel">
            <i class="bi bi-megaphone" aria-hidden="true"></i>
            <p class="mb-0">
                <?= $jenis === ''
                    ? 'Belum ada pengumuman atau peraturan.'
                    : 'Belum ada ' . esc(mb_strtolower(PengumumanModel::JENIS[$jenis])) . '.' ?>
            </p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="tabel-kos">
                <thead>
                    <tr>
                        <th>Jenis</th>
                        <th>Isi</th>
                        <th>Terbit</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftar as $p): ?>
                        <tr>
                            <td><span class="lencana <?= $lencana[$p['jenis']] ?? 'lencana-abu' ?>"><?= esc(PengumumanModel::JENIS[$p['jenis']] ?? $p['jenis']) ?></span></td>
                            <td style="min-width: 260px; white-space: pre-line;"><?= esc($p['isi']) ?></td>
                            <td class="text-nowrap"><?= esc(tanggal_indonesia($p['tanggal_terbit'])) ?></td>
                            <td class="text-end text-nowrap">
                                <a href="<?= site_url('pemilik/pengumuman/' . $p['id_pengumuman'] . '/ubah') ?>"
                                    class="btn btn-sm btn-outline-primary">Ubah</a>
                                <form action="<?= site_url('pemilik/pengumuman/' . $p['id_pengumuman'] . '/hapus') ?>" method="post"
                                    class="d-inline"
                                    onsubmit="return confirm('Hapus <?= esc(mb_strtolower(PengumumanModel::JENIS[$p['jenis']] ?? 'data'), 'js') ?> ini? Tidak bisa dikembalikan.');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-tolak">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
