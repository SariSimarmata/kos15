<?= $this->extend('layouts/pemilik') ?>

<?= $this->section('content') ?>
<?php
/* Pemilik > Tagihan: pantau tagihan dan pembayaran penghuni. Data dari Pemilik\Tagihan::index(). */
use App\Models\TagihanModel;

$tab = ['' => 'Semua', 'belum_bayar' => 'Belum Bayar', 'lunas' => 'Lunas'];
$angkaTab = ['' => $ringkas['semua'], 'belum_bayar' => $ringkas['belum_bayar'], 'lunas' => $ringkas['lunas']];
?>

<div class="header-halaman mb-4">
    <h1 class="judul-halaman mb-1">Tagihan</h1>
    <p class="subjudul-halaman">Pantau tagihan penghuni. Pembayaran QRIS tercatat lunas otomatis.</p>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="kartu-statistik">
            <span class="ikon-kotak"><i class="bi bi-hourglass-split" aria-hidden="true"></i></span>
            <div class="label">Belum Dibayar</div>
            <div class="angka <?= $ringkas['belum_bayar'] > 0 ? 'teks-merah' : '' ?>"><?= rupiah($ringkas['nominalBelum']) ?></div>
            <div class="catatan"><?= esc($ringkas['belum_bayar']) ?> tagihan</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="kartu-statistik">
            <span class="ikon-kotak"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></span>
            <div class="label">Lewat Jatuh Tempo</div>
            <div class="angka <?= $ringkas['terlambat'] > 0 ? 'teks-merah' : '' ?>"><?= esc($ringkas['terlambat']) ?></div>
            <div class="catatan">tagihan terlambat</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="kartu-statistik">
            <span class="ikon-kotak"><i class="bi bi-check2-circle" aria-hidden="true"></i></span>
            <div class="label">Sudah Dibayar</div>
            <div class="angka teks-hijau"><?= rupiah($ringkas['nominalLunas']) ?></div>
            <div class="catatan"><?= esc($ringkas['lunas']) ?> tagihan lunas</div>
        </div>
    </div>
</div>

<div class="kartu-putih">
    <div class="d-flex gap-2 flex-wrap mb-3">
        <?php foreach ($tab as $nilai => $teks): ?>
            <a href="<?= site_url('pemilik/tagihan' . ($nilai !== '' ? '?status=' . $nilai : '')) ?>"
                class="chip-filter <?= $status === $nilai ? 'aktif' : '' ?>">
                <?= esc($teks) ?> <span class="angka-filter"><?= esc($angkaTab[$nilai]) ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($tagihan === []): ?>
        <div class="banner-info">Belum ada tagihan di tab ini.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="tabel-kos">
                <thead>
                    <tr>
                        <th>Penghuni</th>
                        <th>Tagihan</th>
                        <th>Periode</th>
                        <th>Jatuh Tempo</th>
                        <th>Jumlah</th>
                        <th>Status</th>
                        <th>Dibayar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tagihan as $t): ?>
                        <?php
                        $lunas     = $t['status_tagihan'] === 'lunas';
                        $terlambat = ! $lunas && $t['tanggal_jatuh_tempo'] < $hariIni;
                        $menunggu  = ! $lunas && ! empty($t['id_qris_aktif']);
                        [$kelas, $teks] = match (true) {
                            $lunas     => ['lencana-hijau', 'Lunas'],
                            $menunggu  => ['lencana-biru', 'Menunggu Pembayaran'],
                            $terlambat => ['lencana-merah', 'Terlambat'],
                            default    => ['lencana-kuning', 'Belum Bayar'],
                        };
                        $bayar = $t['bayar_berhasil'] ? explode('|', $t['bayar_berhasil'], 2) : null;
                        ?>
                        <tr>
                            <td><?= esc($t['nama']) ?><span class="sub">Kamar <?= esc($t['nomor_kamar']) ?></span></td>
                            <td><?= esc(TagihanModel::LABEL_JENIS[$t['jenis_tagihan']] ?? $t['jenis_tagihan']) ?></td>
                            <td><?= esc(TagihanModel::periode($t)) ?></td>
                            <td class="<?= $terlambat ? 'teks-merah' : '' ?>"><?= esc(tanggal_indonesia($t['tanggal_jatuh_tempo'])) ?></td>
                            <td class="nominal"><?= rupiah($t['jumlah_tagihan']) ?></td>
                            <td><span class="lencana <?= $kelas ?>"><?= $teks ?></span></td>
                            <td>
                                <?php if ($bayar !== null): ?>
                                    <?= $bayar[0] === 'qris' ? 'QRIS' : 'Tunai' ?>
                                    <?php if (! empty($bayar[1])): ?><span class="sub"><?= esc(tanggal_indonesia($bayar[1])) ?></span><?php endif; ?>
                                <?php else: ?>
                                    <span class="teks-kecil">–</span>
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
