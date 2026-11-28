<?= $this->extend('layouts/penghuni') ?>

<?= $this->section('content') ?>
<?php
/*
 * Halaman "Bayar Tagihan" sesuai desain Halaman Penghuni:
 * kiri  = 1. Pilih Tagihan (satu tagihan per pembayaran) + 2. Metode Pembayaran (QRIS)
 * kanan = Ringkasan Pembayaran + tombol Bayar dengan QRIS.
 * Data dari Penghuni\Pembayaran::pilih().
 */
use App\Models\TagihanModel;

$lunas   = $tagihan['status_tagihan'] === 'lunas';
$jenis   = TagihanModel::LABEL_JENIS[$tagihan['jenis_tagihan']] ?? $tagihan['jenis_tagihan'];
$hariIni = (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
?>


<div class="header-halaman">
    <h1 class="judul-halaman mb-1">Bayar Tagihan</h1>
    <p class="subjudul-halaman">Pilih tagihan yang ingin dibayar, lalu bayar dengan QRIS.</p>
</div>

<?php if ($lunas): ?>
    <div class="kartu-putih p-4 text-center">
        <span class="ikon-sukses mx-auto mb-3"><i class="bi bi-check-lg" aria-hidden="true"></i></span>
        <h2 class="judul-halaman mb-1">Tagihan sudah lunas</h2>
        <p class="teks-kecil mb-4">Tagihan <?= esc($jenis) ?> sebesar <?= rupiah($tagihan['jumlah_tagihan']) ?> tidak perlu dibayar lagi.</p>
        <div class="d-flex justify-content-center gap-2">
            <a href="<?= site_url('penghuni/tagihan') ?>" class="btn btn-outline-primary">Kembali ke Tagihan</a>
            <?php if ($belumBayar !== []): ?>
                <a href="<?= site_url('penghuni/bayar') ?>" class="btn btn-primary">Bayar tagihan lain</a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="kartu-putih mb-3">
                <div class="judul-langkah">1. Pilih Tagihan</div>
                <div class="d-flex flex-column gap-2">
                    <?php foreach ($belumBayar as $t): ?>
                        <?php
                        $dipilih   = (int) $t['id_tagihan'] === (int) $tagihan['id_tagihan'];
                        $terlambat = $t['tanggal_jatuh_tempo'] < $hariIni;
                        ?>
                        <a href="<?= site_url('penghuni/tagihan/' . $t['id_tagihan'] . '/bayar') ?>"
                            class="pilih-tagihan <?= $dipilih ? 'aktif' : '' ?>" <?= $dipilih ? 'aria-current="true"' : '' ?>>
                            <span class="centang"><i class="bi bi-check" aria-hidden="true"></i></span>
                            <span class="min-w-0 flex-grow-1">
                                <span class="nama"><?= esc(TagihanModel::LABEL_JENIS[$t['jenis_tagihan']] ?? $t['jenis_tagihan']) ?></span>
                                <span class="sub">
                                    <?= esc(TagihanModel::periode($t)) ?> ·
                                    <span class="<?= $terlambat ? 'teks-merah' : '' ?>">jatuh tempo <?= esc(tanggal_indonesia($t['tanggal_jatuh_tempo'])) ?></span>
                                </span>
                            </span>
                            <span class="fw-semibold text-nowrap"><?= rupiah($t['jumlah_tagihan']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <p class="teks-kecil mt-2 mb-0">Satu pembayaran QRIS untuk satu tagihan.</p>
            </div>

            <div class="kartu-putih">
                <div class="judul-langkah">2. Metode Pembayaran</div>
                <div class="kotak-metode d-flex align-items-center gap-3">
                    <span class="ikon-kotak mb-0 bg-white"><i class="bi bi-qr-code" aria-hidden="true"></i></span>
                    <span class="flex-grow-1">
                        <span class="d-block fw-semibold">QRIS</span>
                        <span class="teks-kecil">Scan dari aplikasi e-wallet atau m-banking apa saja. Status lunas diperbarui otomatis.</span>
                    </span>
                    <i class="bi bi-check-circle-fill text-primary fs-5" aria-hidden="true"></i>
                </div>
                <p class="teks-kecil mt-2 mb-0">Mau bayar tunai? Serahkan langsung ke pemilik kos, lalu pemilik mengonfirmasi pembayaranmu.</p>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="kartu-putih">
                <div class="judul-kartu mb-3">Ringkasan Pembayaran</div>
                <div class="baris-info"><span><?= esc($jenis) ?></span><span><?= rupiah($tagihan['jumlah_tagihan']) ?></span></div>
                <div class="baris-info"><span>Kamar</span><span><?= esc($tagihan['nomor_kamar']) ?></span></div>
                <div class="baris-info"><span>Biaya admin</span><span>Rp 0</span></div>
                <div class="baris-info border-top pt-2 mt-1">
                    <span class="fw-semibold">Total</span>
                    <span class="angka-dp"><?= rupiah($tagihan['jumlah_tagihan']) ?></span>
                </div>

                <div class="mt-3">
                    <?php if ($qrisAktif !== null): ?>
                        <div class="banner-info mb-2">Kode QRIS untuk tagihan ini masih berlaku.</div>
                        <a href="<?= site_url('penghuni/pembayaran/' . $qrisAktif['id_pembayaran']) ?>" class="btn btn-primary w-100">
                            <i class="bi bi-qr-code me-1" aria-hidden="true"></i> Lanjutkan Pembayaran QRIS
                        </a>
                    <?php elseif (! $gatewaySiap): ?>
                        <div class="kotak-merah">Pembayaran QRIS belum aktif. Hubungi pemilik kos.</div>
                    <?php else: ?>
                        <form action="<?= site_url('penghuni/tagihan/' . $tagihan['id_tagihan'] . '/qris') ?>" method="post" data-form-qris>
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-qr-code me-1" aria-hidden="true"></i> Bayar dengan QRIS
                            </button>
                        </form>
                    <?php endif; ?>
                    <p class="teks-kecil text-center mt-2 mb-0">Kode QRIS berlaku 15 menit.</p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
    // Cegah klik ganda: tombol dikunci saat kode QRIS sedang dibuat
    document.querySelectorAll('[data-form-qris]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var tombol = form.querySelector('button[type="submit"]');
            tombol.disabled = true;
            tombol.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Membuat kode QRIS...';
        });
    });
</script>
<?= $this->endSection() ?>
