<?= $this->extend('layouts/penghuni') ?>

<?= $this->section('content') ?>
<?php
/*
 * Halaman "Riwayat Pembayaran" (menu sidebar penghuni: Riwayat Pembayaran), sesuai desain Halaman Penghuni.
 * Data dari Penghuni\Tagihan::riwayat(). Filter lewat ?cari= dan ?status=.
 */
use App\Models\TagihanModel;

$lencana = [
    'berhasil'    => ['lencana-hijau', 'Berhasil'],
    'menunggu'    => ['lencana-biru', 'Menunggu'],
    'kedaluwarsa' => ['lencana-abu', 'Kedaluwarsa'],
    'gagal'       => ['lencana-merah', 'Gagal'],
];
$pilihanStatus = ['' => 'Semua status', 'berhasil' => 'Berhasil', 'menunggu' => 'Menunggu', 'kedaluwarsa' => 'Kedaluwarsa', 'gagal' => 'Gagal'];
$sekarang = (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d H:i:s');
?>

<div class="header-halaman d-flex justify-content-between align-items-start flex-wrap gap-3">
    <div>
        <h1 class="judul-halaman mb-1">Riwayat Pembayaran</h1>
        <p class="subjudul-halaman">Semua pembayaran yang pernah kamu lakukan.</p>
    </div>
    <a href="<?= site_url('penghuni/riwayat/unduh') ?>" class="btn btn-outline-primary">
        <i class="bi bi-download me-1" aria-hidden="true"></i> Unduh Rekap
    </a>
</div>

<div class="kartu-putih">
    <form method="get" action="<?= site_url('penghuni/riwayat') ?>" class="row g-2 mb-2">
        <div class="col-md-7">
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search" aria-hidden="true"></i></span>
                <input type="search" name="cari" value="<?= esc($cari, 'attr') ?>" class="form-control border-start-0"
                    placeholder="Cari tagihan, metode, atau nomor transaksi">
            </div>
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select" onchange="this.form.submit()">
                <?php foreach ($pilihanStatus as $nilai => $teks): ?>
                    <option value="<?= esc($nilai, 'attr') ?>" <?= $status === $nilai ? 'selected' : '' ?>><?= esc($teks) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-primary">Cari</button>
        </div>
    </form>

    <?php if ($riwayat === []): ?>
        <div class="banner-info my-3">
            <?= ($cari !== '' || $status !== '') ? 'Tidak ada pembayaran yang cocok dengan pencarian.' : 'Belum ada pembayaran. Pembayaranmu akan muncul di sini.' ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="tabel-kos">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Tagihan</th>
                        <th>Metode</th>
                        <th>No. Transaksi</th>
                        <th>Jumlah</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($riwayat as $b): ?>
                        <?php
                        [$kelas, $teks] = $lencana[$b['status_pembayaran']] ?? ['lencana-abu', $b['status_pembayaran']];
                        $waktu = $b['tanggal_bayar'] ?: $b['waktu_kedaluwarsa'];
                        ?>
                        <tr>
                            <td>
                                <?= $waktu ? esc(tanggal_indonesia($waktu)) : '–' ?>
                                <?php if ($waktu): ?>
                                    <span class="sub"><?= $b['tanggal_bayar'] ? '' : 'batas bayar ' ?><?= esc(date('H.i', strtotime($waktu))) ?> WIB</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= esc(TagihanModel::LABEL_JENIS[$b['jenis_tagihan']] ?? $b['jenis_tagihan']) ?>
                                <span class="sub">Kamar <?= esc($b['nomor_kamar']) ?></span>
                            </td>
                            <td><?= $b['metode'] === 'qris' ? 'QRIS' : 'Tunai' ?></td>
                            <td class="teks-kecil"><?= esc($b['order_id'] ?: '–') ?></td>
                            <td class="nominal"><?= rupiah($b['jumlah_bayar']) ?></td>
                            <td><span class="lencana <?= $kelas ?>"><?= esc($teks) ?></span></td>
                            <td class="text-end">
                                <?php if ($b['status_pembayaran'] === 'berhasil'): ?>
                                    <a href="<?= site_url('penghuni/pembayaran/' . $b['id_pembayaran'] . '/kwitansi') ?>" class="btn-aksi">
                                        <i class="bi bi-file-earmark-text" aria-hidden="true"></i> Kwitansi
                                    </a>
                                <?php elseif ($b['status_pembayaran'] === 'menunggu' && $b['metode'] === 'qris' && $b['waktu_kedaluwarsa'] > $sekarang): ?>
                                    <a href="<?= site_url('penghuni/pembayaran/' . $b['id_pembayaran']) ?>" class="btn-aksi">
                                        <i class="bi bi-qr-code" aria-hidden="true"></i> Lihat QRIS
                                    </a>
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
