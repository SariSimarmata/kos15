<?= $this->extend('layouts/penghuni') ?>

<?= $this->section('content') ?>
<?php
/*
 * Kwitansi pembayaran berhasil (Penghuni\Riwayat::kwitansi), QRIS maupun tunai.
 * Tombol Cetak memakai print browser; di jendela cetak pilih "Simpan sebagai PDF" untuk mengunduh.
 * Untuk tunai (Kartu 6) ikut tampil nama pemilik yang mengonfirmasi.
 */
use App\Models\TagihanModel;
?>


<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="kartu-putih p-4">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-4">
                <div>
                    <div class="judul-halaman mb-0">Kwitansi Pembayaran</div>
                    <div class="teks-kecil">Kos 15</div>
                </div>
                <span class="lencana lencana-hijau">Lunas</span>
            </div>

            <div class="kotak-ringkasan mb-4">
                <div class="teks-kecil">Jumlah dibayar</div>
                <div class="nominal-besar"><?= rupiah($bayar['jumlah_bayar']) ?></div>
            </div>

            <div class="baris-info"><span>Nama penghuni</span><span><?= esc(session('nama')) ?></span></div>
            <div class="baris-info"><span>Kamar</span><span><?= esc($bayar['nomor_kamar']) ?></span></div>
            <div class="baris-info"><span>Tagihan</span><span><?= esc(TagihanModel::LABEL_JENIS[$bayar['jenis_tagihan']] ?? $bayar['jenis_tagihan']) ?></span></div>
            <div class="baris-info"><span>Periode</span><span><?= esc(TagihanModel::periode($bayar)) ?></span></div>
            <div class="baris-info"><span>Metode</span><span><?= $bayar['metode'] === 'qris' ? 'QRIS' : 'Tunai' ?></span></div>
            <?php if ($bayar['metode'] === 'tunai' && ! empty($bayar['nama_konfirmator'])): ?>
                <div class="baris-info"><span>Dikonfirmasi oleh</span><span><?= esc($bayar['nama_konfirmator']) ?> (pemilik)</span></div>
            <?php endif; ?>
            <div class="baris-info"><span>No. transaksi</span><span><?= esc($bayar['order_id'] ?: '–') ?></span></div>
            <div class="baris-info">
                <span>Tanggal bayar</span>
                <span><?= esc(tanggal_indonesia($bayar['tanggal_bayar'])) ?>, <?= esc(date('H.i', strtotime($bayar['tanggal_bayar']))) ?> WIB</span>
            </div>

            <div class="d-flex gap-2 mt-4 jangan-cetak">
                <button type="button" class="btn btn-primary" onclick="window.print()">
                    <i class="bi bi-printer me-1" aria-hidden="true"></i> Cetak / Simpan PDF
                </button>
                <a href="<?= site_url('penghuni/riwayat') ?>" class="btn btn-outline-primary">Kembali</a>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
