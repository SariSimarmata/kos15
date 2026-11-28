<?php $penghuni = session('role') === 'penghuni'; ?>
<?= $this->extend($penghuni ? 'layouts/penghuni' : 'layouts/main') ?>

<?= $this->section('content') ?>
<?php
helper('tanggal');
$lencana = [
    'pending'  => ['lencana-kuning', 'Pending'],
    'approved' => ['lencana-hijau', 'Approved'],
    'rejected' => ['lencana-merah', 'Rejected'],
];
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h1 class="judul-halaman mb-0">Status Pengajuan</h1>
    <?php if (! $penghuni): ?>
        <a href="<?= site_url('pemesanan/baru') ?>" class="btn btn-outline-primary">Pesan Kamar</a>
    <?php endif; ?>
</div>

<?php if (empty($pemesanan)): ?>
    <div class="banner-info">Kamu belum mengajukan pemesanan. Pilih kamar di <a href="<?= site_url('katalog') ?>">katalog</a> lalu ajukan pemesanan.</div>
<?php endif; ?>

<?php foreach ($pemesanan as $p): ?>
    <?php
    $status = $p['status_pengajuan'];
    [$kelas, $label] = $lencana[$status] ?? ['lencana-biru', $status];
    ?>
    <div class="card p-4 mb-3">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <?php if ($status === 'approved'): ?>
                    <div class="teks-kecil">Nomor pemesanan</div>
                    <div class="h5 mb-0"><?= esc(\App\Models\PemesananModel::nomor((int) $p['id_pemesanan'])) ?></div>
                <?php else: ?>
                    <div class="h5 mb-0">Kamar <?= esc($p['nomor_kamar']) ?></div>
                    <?php if ($status === 'pending'): ?>
                        <div class="teks-kecil">Nomor pemesanan terbit setelah disetujui pemilik</div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <span class="lencana <?= $kelas ?>"><?= esc($label) ?></span>
        </div>

        <div class="kotak-ringkasan mt-3">
            <div class="baris-info"><span>Kamar</span><span><?= esc($p['nomor_kamar']) ?></span></div>
            <div class="baris-info"><span>Tanggal mulai sewa</span><span><?= esc(tanggal_indonesia($p['tanggal_mulai_sewa'])) ?></span></div>
            <div class="baris-info"><span>Diajukan pada</span><span><?= esc(tanggal_indonesia($p['tanggal_pengajuan'])) ?></span></div>
        </div>

        <ul class="garis-status mb-0">
            <li class="selesai">Pengajuan terkirim</li>
            <?php if ($status === 'pending'): ?>
                <li class="sekarang">Menunggu keputusan pemilik</li>
            <?php elseif ($status === 'approved'): ?>
                <li class="selesai">Disetujui pemilik</li>
            <?php else: ?>
                <li class="gagal">Ditolak pemilik</li>
            <?php endif; ?>
        </ul>

        <?php if ($status === 'rejected'): ?>
            <div class="kotak-merah mt-2">Pemesanan ini ditolak. Kamu bisa memesan kamar lain yang masih kosong.</div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
<?= $this->endSection() ?>
