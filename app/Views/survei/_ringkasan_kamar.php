<?php
/** Kartu ringkas kamar di halaman survei. Butuh: $kamar, $fotoUrl */
$tersedia = $kamar['status_kamar'] === 'kosong';
?>
<div class="card p-3">
    <?php if ($fotoUrl): ?>
        <img src="<?= esc($fotoUrl, 'attr') ?>" class="foto-kamar mb-3" alt="Foto Kamar <?= esc($kamar['nomor_kamar'], 'attr') ?>">
    <?php else: ?>
        <div class="foto-kamar mb-3">Foto kamar <?= esc($kamar['nomor_kamar']) ?></div>
    <?php endif; ?>
    <div class="d-flex justify-content-between align-items-center gap-2">
        <h2 class="h6 mb-0">Kamar <?= esc($kamar['nomor_kamar']) ?></h2>
        <span class="lencana <?= $tersedia ? 'lencana-hijau' : 'lencana-abu' ?>"><?= $tersedia ? 'Tersedia' : 'Terisi' ?></span>
    </div>
    <p class="teks-kecil my-2"><?= esc(implode(' · ', array_slice(daftar_fasilitas($kamar['fasilitas']), 0, 3))) ?></p>
    <span class="lencana lencana-krem align-self-start"><?= rupiah($kamar['harga_sewa_tahunan']) ?> / tahun</span>
    <a href="<?= site_url('katalog/' . $kamar['id_kamar']) ?>" class="small mt-3">Lihat detail kamar</a>
</div>
