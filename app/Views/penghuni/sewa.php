<?= $this->extend('layouts/penghuni') ?>

<?= $this->section('content') ?>
<?php
/*
 * Masa sewa penghuni (Kartu 4 Sprint 4, KF-29).
 * Data dari Penghuni\Sewa::index(): $utama (sewa yang ditampilkan besar), $riwayat (semua sewa).
 */
$lencanaSewa = [
    'aktif'               => ['lencana-hijau', 'Aktif'],
    'menunggu_pembayaran' => ['lencana-kuning', 'Menunggu pembayaran'],
    'selesai'             => ['lencana-abu', 'Selesai'],
    'batal'               => ['lencana-merah', 'Batal'],
];

// Semua tanggal memakai zona WIB supaya selisih hari tidak meleset karena beda zona waktu
$zona    = new DateTimeZone('Asia/Jakarta');
$hariIni = new DateTime('today', $zona);
?>
<div class="header-halaman">
    <h1 class="judul-halaman mb-1">Masa Sewa</h1>
    <p class="mb-0">Kamar, tanggal sewa, dan status sewa kamu.</p>
</div>

<?php if ($utama === null): ?>
    <div class="card kartu-panel">
        <div class="kosong-panel">
            <i class="bi bi-calendar-x" aria-hidden="true"></i>
            <p class="mb-0">Kamu belum punya data sewa. Hubungi pemilik kos jika ini keliru.</p>
        </div>
    </div>
<?php else: ?>
    <?php
    [$kelas, $label] = $lencanaSewa[$utama['status_sewa']] ?? ['lencana-abu', $utama['status_sewa']];
    $mulai    = new DateTime($utama['tanggal_mulai'], $zona);
    $berakhir = new DateTime($utama['tanggal_berakhir'], $zona);
    $totalHari  = max(1, (int) $mulai->diff($berakhir)->days);
    $belumMulai = $hariIni < $mulai;
    $hariMenujuMulai = $belumMulai ? (int) $hariIni->diff($mulai)->days : 0;
    $lewat      = $belumMulai ? 0 : min($totalHari, (int) $mulai->diff($hariIni)->days);
    $persen     = (int) round($lewat / $totalHari * 100);
    // Sebelum sewa dimulai, sisa masa sewa = durasi penuh. Setelah dimulai, dihitung dari hari ini.
    $sisaHari   = $belumMulai ? $totalHari : ($hariIni > $berakhir ? 0 : (int) $hariIni->diff($berakhir)->days);
    $berjalan   = in_array($utama['status_sewa'], ['aktif', 'menunggu_pembayaran'], true);
    ?>

    <?php if ($berjalan && $belumMulai): ?>
        <div class="banner-info mb-3">
            <i class="bi bi-info-circle me-2" aria-hidden="true"></i>Sewa kamu dimulai <?= esc(tanggal_indonesia($utama['tanggal_mulai'])) ?>,
            <?= $hariMenujuMulai === 1 ? 'besok' : $hariMenujuMulai . ' hari lagi' ?>.
        </div>
    <?php elseif ($berjalan && $sisaHari <= 30): ?>
        <div class="banner-info banner-peringatan mb-3">
            <i class="bi bi-exclamation-triangle me-2" aria-hidden="true"></i>Sewa kamu berakhir dalam <?= $sisaHari ?> hari.
            Ajukan perpanjangan sebelum <?= esc(tanggal_indonesia($utama['tanggal_berakhir'])) ?> jika ingin tetap tinggal.
        </div>
    <?php endif; ?>

    <div class="card kartu-panel">
        <div class="d-flex justify-content-between align-items-start gap-3">
            <div>
                <div class="teks-kecil">Kamar</div>
                <div class="nomor-kamar-besar"><?= esc($utama['nomor_kamar']) ?></div>
            </div>
            <span class="lencana <?= $kelas ?>"><?= esc($label) ?></span>
        </div>

        <div class="kotak-ringkasan">
            <div class="baris-info"><span>Tanggal mulai</span><span><?= esc(tanggal_indonesia($utama['tanggal_mulai'])) ?></span></div>
            <div class="baris-info"><span>Tanggal berakhir</span><span><?= esc(tanggal_indonesia($utama['tanggal_berakhir'])) ?></span></div>
            <?php if ($berjalan && $belumMulai): ?>
                <div class="baris-info"><span>Dimulai dalam</span><span><?= $hariMenujuMulai ?> hari</span></div>
                <div class="baris-info"><span>Lama sewa</span><span><?= $sisaHari ?> hari</span></div>
            <?php elseif ($berjalan): ?>
                <div class="baris-info"><span>Sisa masa sewa</span><span><?= $sisaHari ?> hari</span></div>
            <?php endif; ?>
        </div>

        <?php if ($berjalan): ?>
            <div>
                <div class="d-flex justify-content-between teks-kecil mb-1">
                    <span><?= $belumMulai ? 'Belum dimulai' : 'Berjalan ' . $persen . '%' ?></span>
                    <span><?= $lewat ?> dari <?= $totalHari ?> hari</span>
                </div>
                <div class="progress" role="progressbar" aria-label="Masa sewa berjalan" aria-valuenow="<?= $persen ?>" aria-valuemin="0" aria-valuemax="100" style="height: 8px;">
                    <div class="progress-bar" style="width: <?= $persen ?>%"></div>
                </div>
            </div>
        <?php endif; ?>
                <?php if ($utama['status_sewa'] === 'aktif' && ! empty($utama['id_sewa'])): ?>
            <form method="post" action="<?= site_url('penghuni/sewa/' . $utama['id_sewa'] . '/perpanjangan') ?>"
                  onsubmit="return confirm('Ajukan perpanjangan sewa selama satu tahun?');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary">Ajukan Perpanjangan</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (count($riwayat) > 1): ?>
        <div class="card kartu-panel mt-3">
            <h2 class="judul-panel mb-0">Riwayat sewa</h2>
            <div class="table-responsive">
                <table class="tabel-kos">
                    <thead>
                        <tr><th>Kamar</th><th>Mulai</th><th>Berakhir</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riwayat as $s): ?>
                            <?php [$k, $l] = $lencanaSewa[$s['status_sewa']] ?? ['lencana-abu', $s['status_sewa']]; ?>
                            <tr>
                                <td><?= esc($s['nomor_kamar']) ?></td>
                                <td><?= esc(tanggal_indonesia($s['tanggal_mulai'])) ?></td>
                                <td><?= esc(tanggal_indonesia($s['tanggal_berakhir'])) ?></td>
                                <td><span class="lencana <?= $k ?>"><?= esc($l) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?= $this->endSection() ?>
