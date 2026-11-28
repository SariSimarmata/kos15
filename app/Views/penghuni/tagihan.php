<?= $this->extend('layouts/penghuni') ?>

<?= $this->section('content') ?>
<?php
/*
 * Tagihan penghuni (Kartu 3).
 * Data dari Penghuni\Tagihan::index(): $perluSkema (sewa yang belum memilih skema, lengkap dengan
 * pratinjau nominal), $tagihan (milik penghuni yang login), $ringkasan, $hariIni (Y-m-d, WIB).
 */
use App\Models\TagihanModel;

$bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli',
    'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$namaPeriode = static function (?string $periode) use ($bulan): string {
    if ($periode === null || ! preg_match('/^(\d{4})-(\d{2})$/', $periode, $m)) {
        return '-';
    }

    return ($bulan[(int) $m[2]] ?? $m[2]) . ' ' . $m[1];
};
?>
<div class="header-halaman">
    <h1 class="judul-halaman mb-1">Tagihan</h1>
    <p class="mb-0">Tagihan sewa, listrik dan air, denda, serta biaya tamu menginap.</p>
</div>

<?php foreach ($perluSkema as $s): ?>
    <div class="card kartu-panel mb-3">
        <div>
            <h2 class="judul-panel mb-1">Pilih skema pembayaran sewa kamar <?= esc($s['nomor_kamar']) ?></h2>
            <p class="mb-0 teks-kecil">
                Masa sewa <?= esc(tanggal_indonesia($s['tanggal_mulai'])) ?> sampai <?= esc(tanggal_indonesia($s['tanggal_berakhir'])) ?>,
                harga sewa <?= esc(rupiah($s['harga'])) ?>. Pelunasan wajib dibayar sebelum tanggal mulai sewa.
            </p>
        </div>

        <form action="<?= site_url('penghuni/tagihan/skema') ?>" method="post"
            onsubmit="return confirm('Skema pembayaran tidak bisa diubah setelah dipilih. Lanjutkan?');">
            <?= csrf_field() ?>
            <input type="hidden" name="id_sewa" value="<?= (int) $s['id_sewa'] ?>">

            <div class="row g-3 mb-3">
                <?php foreach (TagihanModel::SKEMA as $kunci => $labelSkema): ?>
                    <?php $idRadio = 'skema_' . (int) $s['id_sewa'] . '_' . $kunci; ?>
                    <div class="col-md-6">
                        <label class="kotak-ringkasan d-block h-100" for="<?= $idRadio ?>" style="cursor: pointer;">
                            <span class="d-flex align-items-center gap-2 mb-2">
                                <input type="radio" name="skema" id="<?= $idRadio ?>" value="<?= $kunci ?>" required>
                                <span class="fw-semibold"><?= esc($labelSkema) ?></span>
                            </span>
                            <?php foreach ($s['rencana'][$kunci] as $baris): ?>
                                <span class="baris-info">
                                    <span><?= esc(TagihanModel::JENIS[$baris['jenis_tagihan']]) ?>, jatuh tempo <?= esc(tanggal_indonesia($baris['tanggal_jatuh_tempo'])) ?></span>
                                    <span class="fw-semibold"><?= esc(rupiah($baris['jumlah_tagihan'])) ?></span>
                                </span>
                            <?php endforeach; ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="btn btn-primary">Pilih skema dan buat tagihan</button>
        </form>
    </div>
<?php endforeach; ?>

<?php if ($tagihan !== []): ?>
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="kotak-ringkasan h-100">
                <div class="baris-info"><span>Belum dibayar</span><span class="fw-semibold"><?= esc(rupiah($ringkasan['belum_lunas']['total'])) ?></span></div>
                <div class="baris-info"><span>Jumlah tagihan belum lunas</span><span><?= (int) $ringkasan['belum_lunas']['jumlah'] ?></span></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="kotak-ringkasan h-100">
                <div class="baris-info"><span>Lewat jatuh tempo</span><span class="fw-semibold"><?= esc(rupiah($ringkasan['lewat_jatuh_tempo']['total'])) ?></span></div>
                <div class="baris-info"><span>Jumlah tagihan terlambat</span><span><?= (int) $ringkasan['lewat_jatuh_tempo']['jumlah'] ?></span></div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="card kartu-panel">
    <h2 class="judul-panel mb-0">Daftar tagihan</h2>

    <?php if ($tagihan === []): ?>
        <div class="kosong-panel">
            <i class="bi bi-receipt" aria-hidden="true"></i>
            <p class="mb-0">
                <?= $perluSkema !== [] ? 'Tagihan sewa akan muncul di sini setelah kamu memilih skema pembayaran.' : 'Belum ada tagihan.' ?>
            </p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="tabel-kos">
                <thead>
                    <tr>
                        <th>Jenis</th>
                        <th>Kamar</th>
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
                                <div class="fw-semibold"><?= esc(TagihanModel::JENIS[$t['jenis_tagihan']] ?? $t['jenis_tagihan']) ?></div>
                                <?php if ($t['periode'] !== null): ?>
                                    <details class="teks-kecil mt-1">
                                        <summary>Rincian <?= esc($namaPeriode($t['periode'])) ?></summary>
                                        <div class="kotak-ringkasan mt-2">
                                            <div class="baris-info"><span>Listrik</span><span><?= esc(rupiah($t['tagihan_listrik'])) ?></span></div>
                                            <div class="baris-info"><span>Air</span><span><?= esc(rupiah($t['tagihan_air'])) ?></span></div>
                                            <div class="baris-info"><span>Sampah</span><span><?= esc(rupiah($t['tagihan_sampah'])) ?></span></div>
                                            <div class="baris-info"><span>Tamu menginap</span><span><?= esc(rupiah($t['tagihan_pengunjung'])) ?></span></div>
                                            <div class="baris-info fw-semibold"><span>Total</span><span><?= esc(rupiah($t['jumlah_tagihan'])) ?></span></div>
                                        </div>
                                    </details>
                                <?php endif; ?>
                            </td>
                            <td><?= esc($t['nomor_kamar']) ?></td>
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
