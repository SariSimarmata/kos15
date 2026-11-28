<?= $this->extend('layouts/pemilik') ?>

<?= $this->section('content') ?>
<?php
/*
 * Tagihan pemilik (Kartu 3).
 * Data dari Pemilik\Tagihan::index(): $tagihan (sesuai filter), $filter, $ringkasan, $hariIni (Y-m-d, WIB).
 */
use App\Models\TagihanModel;

$pesanKosong = [
    'semua'             => 'Belum ada tagihan.',
    'belum_lunas'       => 'Tidak ada tagihan yang belum lunas.',
    'lewat_jatuh_tempo' => 'Tidak ada tagihan yang lewat jatuh tempo.',
];
?>
<div class="header-halaman">
    <h1 class="judul-halaman mb-1">Tagihan</h1>
    <p class="mb-0">
        <?= (int) $ringkasan['belum_lunas']['jumlah'] ?> tagihan belum lunas, total <?= esc(rupiah($ringkasan['belum_lunas']['total'])) ?>.
        <?= (int) $ringkasan['lewat_jatuh_tempo']['jumlah'] ?> di antaranya lewat jatuh tempo (<?= esc(rupiah($ringkasan['lewat_jatuh_tempo']['total'])) ?>).
    </p>
</div>

<div class="card kartu-panel">
    <div class="d-flex flex-wrap gap-2" role="group" aria-label="Filter tagihan">
        <?php foreach (TagihanModel::FILTER as $kunci => $labelFilter): ?>
            <a href="<?= site_url('pemilik/tagihan') . ($kunci === 'semua' ? '' : '?filter=' . $kunci) ?>"
                class="chip-filter <?= $filter === $kunci ? 'aktif' : '' ?>" <?= $filter === $kunci ? 'aria-current="true"' : '' ?>>
                <?= esc($labelFilter) ?> <span class="angka-filter"><?= (int) $ringkasan[$kunci]['jumlah'] ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($tagihan === []): ?>
        <div class="kosong-panel">
            <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
            <p class="mb-0"><?= esc($pesanKosong[$filter]) ?></p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="tabel-kos">
                <thead>
                    <tr>
                        <th>Penghuni</th>
                        <th>Kamar</th>
                        <th>Jenis</th>
                        <th class="text-end">Jumlah</th>
                        <th>Jatuh tempo</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tagihan as $t): ?>
                        <?php [$kelas, $label] = TagihanModel::statusTampil($t, $hariIni); ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= esc($t['nama_penghuni']) ?></div>
                                <div class="teks-kecil">@<?= esc($t['username']) ?></div>
                            </td>
                            <td><?= esc($t['nomor_kamar']) ?></td>
                            <td><?= esc(TagihanModel::JENIS[$t['jenis_tagihan']] ?? $t['jenis_tagihan']) ?></td>
                            <td class="text-end text-nowrap"><?= esc(rupiah($t['jumlah_tagihan'])) ?></td>
                            <td class="text-nowrap"><?= esc(tanggal_indonesia($t['tanggal_jatuh_tempo'])) ?></td>
                            <td><span class="lencana <?= $kelas ?>"><?= esc($label) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
