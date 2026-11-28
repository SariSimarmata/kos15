<?= $this->extend('layouts/pemilik') ?>

<?= $this->section('content') ?>
<?php
$hariIni = date('Y-m-d');
$namaOrang = static fn($r) => $r->nama_lengkap ?? $r->nama ?? ('Penghuni #' . $r->id_pengguna);
?>
<div class="header-halaman">
    <h1 class="judul-halaman mb-1">Perpanjangan dan Pembatalan Sewa</h1>
    <p class="mb-0">Putuskan pengajuan perpanjangan, atau batalkan sewa yang pelunasannya belum dibayar.</p>
</div>

<div class="card kartu-panel mb-3">
    <h2 class="judul-panel">Pengajuan perpanjangan</h2>
    <?php if (empty($pengajuan)): ?>
        <div class="kosong-panel">
            <p class="mb-0">Tidak ada pengajuan yang menunggu keputusan.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="tabel-kos">
                <thead>
                    <tr><th>Penghuni</th><th>Kamar</th><th>Sewa berakhir</th><th>Perpanjangan mulai</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($pengajuan as $p): ?>
                        <tr>
                            <td><?= esc($namaOrang($p)) ?></td>
                            <td><?= esc($p->nomor_kamar) ?></td>
                            <td><?= esc(tanggal_indonesia($p->tanggal_berakhir)) ?></td>
                            <td><?= esc(tanggal_indonesia($p->tanggal_perpanjangan)) ?></td>
                            <td>
                                <div class="d-flex gap-2">
                                    <form method="post" action="<?= site_url('pemilik/perpanjangan/' . $p->id_perpanjangan . '/setujui') ?>"
                                          onsubmit="return confirm('Setujui perpanjangan satu tahun? Tagihan pelunasan akan dibuat.');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-success btn-sm">Setujui</button>
                                    </form>
                                    <form method="post" action="<?= site_url('pemilik/perpanjangan/' . $p->id_perpanjangan . '/tolak') ?>"
                                          onsubmit="return confirm('Tolak pengajuan perpanjangan ini?');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Tolak</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card kartu-panel">
    <h2 class="judul-panel">Sewa menunggu pembayaran</h2>
    <p class="teks-kecil">Sewa hanya bisa dibatalkan jika pelunasan belum dibayar dan tanggal mulai sudah lewat.</p>
    <?php if (empty($menunggu)): ?>
        <div class="kosong-panel">
            <p class="mb-0">Tidak ada sewa yang menunggu pembayaran.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="tabel-kos">
                <thead>
                    <tr><th>Penghuni</th><th>Kamar</th><th>Mulai</th><th>Berakhir</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($menunggu as $s): ?>
                        <?php $bisa = $hariIni >= $s->tanggal_mulai; ?>
                        <tr>
                            <td><?= esc($namaOrang($s)) ?></td>
                            <td><?= esc($s->nomor_kamar) ?></td>
                            <td><?= esc(tanggal_indonesia($s->tanggal_mulai)) ?></td>
                            <td><?= esc(tanggal_indonesia($s->tanggal_berakhir)) ?></td>
                            <td>
                                <?php if ($bisa): ?>
                                    <form method="post" action="<?= site_url('pemilik/sewa/' . $s->id_sewa . '/batalkan') ?>"
                                          onsubmit="return confirm('Batalkan sewa ini? Kamar akan kembali kosong.');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Batalkan sewa</button>
                                    </form>
                                <?php else: ?>
                                    <span class="teks-kecil">Bisa dibatalkan mulai <?= esc(tanggal_indonesia($s->tanggal_mulai)) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
