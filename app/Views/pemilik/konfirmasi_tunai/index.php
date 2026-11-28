<?= $this->extend('layouts/pemilik') ?>

<?= $this->section('content') ?>
<?php
/*
 * Konfirmasi Tunai pemilik (Kartu 6).
 * Data dari Pemilik\KonfirmasiTunai::index(): $daftar = pengajuan bayar tunai berstatus menunggu.
 * Konfirmasi dan tolak memanggil PembayaranService::konfirmasiTunai() / tolakTunai().
 */
use App\Models\TagihanModel;

$total = 0;
foreach ($daftar as $d) {
    $total += (int) round((float) $d['jumlah_bayar']);
}
?>
<div class="header-halaman">
    <h1 class="judul-halaman mb-1">Konfirmasi Tunai</h1>
    <p class="mb-0">
        <?php if ($daftar === []): ?>
            Tidak ada pengajuan bayar tunai yang menunggu.
        <?php else: ?>
            <?= count($daftar) ?> pengajuan menunggu, total <?= esc(rupiah($total)) ?>.
            Konfirmasi jika uang tunai sudah kamu terima, atau tolak jika belum.
        <?php endif; ?>
    </p>
</div>

<div class="card kartu-panel">
    <?php if ($daftar === []): ?>
        <div class="kosong-panel">
            <i class="bi bi-cash" aria-hidden="true"></i>
            <p class="mb-0">Semua sudah beres. Pengajuan baru dari penghuni akan muncul di sini.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="tabel-kos">
                <thead>
                    <tr>
                        <th>Diajukan</th>
                        <th>Penghuni</th>
                        <th>Tagihan</th>
                        <th class="text-end">Jumlah</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftar as $d): ?>
                        <tr>
                            <td class="text-nowrap">
                                <?= esc(tanggal_indonesia($d['tanggal_bayar'])) ?>
                                <span class="sub"><?= esc(date('H.i', strtotime($d['tanggal_bayar']))) ?> WIB</span>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= esc($d['nama_penghuni']) ?></div>
                                <div class="teks-kecil">Kamar <?= esc($d['nomor_kamar']) ?> · @<?= esc($d['username']) ?></div>
                            </td>
                            <td>
                                <?= esc(TagihanModel::JENIS[$d['jenis_tagihan']] ?? $d['jenis_tagihan']) ?>
                                <span class="sub"><?= esc(TagihanModel::periode($d)) ?> · jatuh tempo <?= esc(tanggal_indonesia($d['tanggal_jatuh_tempo'])) ?></span>
                            </td>
                            <td class="text-end nominal"><?= esc(rupiah($d['jumlah_bayar'])) ?></td>
                            <td>
                                <div class="d-flex flex-wrap justify-content-end align-items-start gap-2">
                                    <form action="<?= site_url('pemilik/konfirmasi-tunai/' . $d['id_pembayaran'] . '/konfirmasi') ?>" method="post"
                                        onsubmit="return confirm('Konfirmasi <?= esc(rupiah($d['jumlah_bayar']), 'js') ?> dari <?= esc($d['nama_penghuni'], 'js') ?>? Pastikan uang tunai sudah kamu terima. Tagihan akan menjadi lunas.');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="bi bi-check-lg me-1" aria-hidden="true"></i> Konfirmasi
                                        </button>
                                    </form>
                                    <form action="<?= site_url('pemilik/konfirmasi-tunai/' . $d['id_pembayaran'] . '/tolak') ?>" method="post"
                                        class="d-flex gap-2"
                                        onsubmit="return confirm('Tolak pengajuan dari <?= esc($d['nama_penghuni'], 'js') ?>? Tagihan tetap belum dibayar.');">
                                        <?= csrf_field() ?>
                                        <input type="text" name="alasan" maxlength="200" class="form-control form-control-sm"
                                            placeholder="Alasan (opsional)" aria-label="Alasan menolak pengajuan dari <?= esc($d['nama_penghuni'], 'attr') ?>">
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
<?= $this->endSection() ?>
