<?php
/**
 * Kartu ringkas tagihan di halaman pembayaran (kiri).
 * Butuh: $t (jenis_tagihan, jumlah_tagihan, tanggal_jatuh_tempo, status_tagihan, nomor_kamar)
 */
$lunas = $t['status_tagihan'] === 'lunas';
?>
<div class="card p-3">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
        <h2 class="h6 mb-0">Rincian Tagihan</h2>
        <span class="lencana <?= $lunas ? 'lencana-hijau' : 'lencana-kuning' ?>" data-lencana-tagihan>
            <?= $lunas ? 'Lunas' : 'Belum bayar' ?>
        </span>
    </div>
    <div class="baris-info"><span>Jenis</span><span><?= esc(\App\Models\TagihanModel::LABEL_JENIS[$t['jenis_tagihan']] ?? $t['jenis_tagihan']) ?></span></div>
    <div class="baris-info"><span>Kamar</span><span><?= esc($t['nomor_kamar']) ?></span></div>
    <div class="baris-info"><span>Jatuh tempo</span><span><?= esc(tanggal_indonesia($t['tanggal_jatuh_tempo'])) ?></span></div>
    <div class="baris-info"><span>Total</span><span class="angka-dp"><?= rupiah($t['jumlah_tagihan']) ?></span></div>
</div>
