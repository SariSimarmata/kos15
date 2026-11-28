<?= $this->extend('layouts/penghuni') ?>

<?= $this->section('content') ?>
<?php
/*
 * Halaman "Tagihan Saya" (menu sidebar penghuni: Tagihan), sesuai desain Halaman Penghuni.
 * Data dari Penghuni\Tagihan::index().
 */
use App\Models\TagihanModel;

$terdekat = $total['terdekat'];
?>

<div class="header-halaman d-flex justify-content-between align-items-start flex-wrap gap-3">
    <div>
        <h1 class="judul-halaman mb-1">Tagihan Saya</h1>
        <p class="subjudul-halaman">Lihat dan bayar tagihan sewa kamarmu di Kos 15.</p>
    </div>
    <?php if ($total['jumlahBelum'] > 0): ?>
        <a href="<?= site_url('penghuni/bayar') ?>" class="btn btn-primary">
            <i class="bi bi-wallet2 me-1" aria-hidden="true"></i> Bayar Tagihan
        </a>
    <?php endif; ?>
</div>

<?php if ($terdekat !== null): ?>
    <div class="banner-info d-flex align-items-start gap-2 mb-4">
        <i class="bi bi-info-circle" aria-hidden="true"></i>
        <span>
            Tagihan <?= esc(TagihanModel::LABEL_JENIS[$terdekat['jenis_tagihan']] ?? $terdekat['jenis_tagihan']) ?>
            sebesar <strong><?= rupiah($terdekat['jumlah_tagihan']) ?></strong>
            <?= $terdekat['tanggal_jatuh_tempo'] < $hariIni ? 'sudah lewat jatuh tempo' : 'jatuh tempo' ?>
            <?= esc(tanggal_indonesia($terdekat['tanggal_jatuh_tempo'])) ?>.
            Bayar dengan QRIS, status lunas langsung diperbarui otomatis.
        </span>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="kartu-statistik">
            <span class="ikon-kotak"><i class="bi bi-receipt" aria-hidden="true"></i></span>
            <div class="label">Total Belum Dibayar</div>
            <div class="angka <?= $total['jumlahBelum'] > 0 ? 'teks-merah' : '' ?>"><?= rupiah($total['belum']) ?></div>
            <div class="catatan"><?= esc($total['jumlahBelum']) ?> tagihan belum dibayar</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="kartu-statistik">
            <span class="ikon-kotak"><i class="bi bi-calendar-event" aria-hidden="true"></i></span>
            <div class="label">Jatuh Tempo Terdekat</div>
            <div class="angka <?= $terdekat !== null && $terdekat['tanggal_jatuh_tempo'] < $hariIni ? 'teks-merah' : '' ?>">
                <?= $terdekat !== null ? esc(tanggal_indonesia($terdekat['tanggal_jatuh_tempo'])) : '–' ?>
            </div>
            <div class="catatan">
                <?php if ($total['terlambat'] > 0): ?>
                    <span class="teks-merah"><?= esc($total['terlambat']) ?> tagihan terlambat</span>
                <?php else: ?>
                    <?= $terdekat !== null ? 'Tidak ada tagihan terlambat' : 'Tidak ada tagihan aktif' ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="kartu-statistik">
            <span class="ikon-kotak"><i class="bi bi-check2-circle" aria-hidden="true"></i></span>
            <div class="label">Sudah Dibayar</div>
            <div class="angka teks-hijau"><?= rupiah($total['lunas']) ?></div>
            <div class="catatan"><?= esc($total['jumlahLunas']) ?> tagihan lunas</div>
        </div>
    </div>
</div>

<div class="kartu-putih">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <div>
            <div class="judul-kartu">Daftar Tagihan</div>
            <div class="teks-kecil">Semua tagihan sewa, listrik &amp; air, dan denda atas namamu.</div>
        </div>
        <a href="<?= site_url('penghuni/riwayat') ?>" class="btn-aksi"><i class="bi bi-clock-history" aria-hidden="true"></i> Riwayat Bayar</a>
    </div>

    <?php if ($tagihan === []): ?>
        <div class="banner-info my-3">Belum ada tagihan. Tagihan DP dan pelunasan muncul setelah pemesanan kamarmu disetujui pemilik.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="tabel-kos">
                <thead>
                    <tr>
                        <th>Tagihan</th>
                        <th>Periode</th>
                        <th>Jatuh Tempo</th>
                        <th>Jumlah</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
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
                        ?>
                        <tr>
                            <td>
                                <?= esc(TagihanModel::LABEL_JENIS[$t['jenis_tagihan']] ?? $t['jenis_tagihan']) ?>
                                <span class="sub">Kamar <?= esc($t['nomor_kamar']) ?></span>
                            </td>
                            <td><?= esc(TagihanModel::periode($t)) ?></td>
                            <td class="<?= $terlambat ? 'teks-merah' : '' ?>"><?= esc(tanggal_indonesia($t['tanggal_jatuh_tempo'])) ?></td>
                            <td class="nominal"><?= rupiah($t['jumlah_tagihan']) ?></td>
                            <td><span class="lencana <?= $kelas ?>"><?= $teks ?></span></td>
                            <td class="text-end">
                                <?php if ($lunas && ! empty($t['id_bayar_berhasil'])): ?>
                                    <a href="<?= site_url('penghuni/pembayaran/' . $t['id_bayar_berhasil'] . '/kwitansi') ?>" class="btn-aksi">
                                        <i class="bi bi-file-earmark-text" aria-hidden="true"></i> Kwitansi
                                    </a>
                                <?php elseif ($lunas): ?>
                                    <span class="teks-kecil">–</span>
                                <?php elseif ($menunggu): ?>
                                    <a href="<?= site_url('penghuni/pembayaran/' . $t['id_qris_aktif']) ?>" class="btn-aksi">
                                        <i class="bi bi-qr-code" aria-hidden="true"></i> Lihat QRIS
                                    </a>
                                <?php else: ?>
                                    <a href="<?= site_url('penghuni/tagihan/' . $t['id_tagihan'] . '/bayar') ?>" class="btn btn-primary btn-sm">Bayar</a>
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
