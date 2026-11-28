<?= $this->extend('layouts/pemilik') ?>

<?= $this->section('content') ?>
<?php
/*
 * Daftar laporan kerusakan untuk pemilik (dengan nama pelapor dan kamar).
 * Data dari Pemilik\Kerusakan::index(): $laporan, $status (filter aktif), $jumlah (per status).
 */
use App\Models\LaporanKerusakanModel;

$total = array_sum($jumlah);
?>
<div class="header-halaman">
    <h1 class="judul-halaman mb-1">Status Kerusakan</h1>
    <p class="mb-0"><?= (int) $jumlah['dilaporkan'] ?> laporan baru, <?= (int) $jumlah['diproses'] ?> sedang diproses.</p>
</div>

<div class="card kartu-panel">
    <div class="d-flex flex-wrap gap-2" role="group" aria-label="Filter status">
        <a href="<?= site_url('pemilik/kerusakan') ?>" class="chip-filter <?= $status === '' ? 'aktif' : '' ?>" <?= $status === '' ? 'aria-current="true"' : '' ?>>
            Semua <span class="angka-filter"><?= $total ?></span>
        </a>
        <?php foreach (LaporanKerusakanModel::STATUS as $kunci => $label): ?>
            <a href="<?= site_url('pemilik/kerusakan?status=' . $kunci) ?>" class="chip-filter <?= $status === $kunci ? 'aktif' : '' ?>"
                <?= $status === $kunci ? 'aria-current="true"' : '' ?>>
                <?= esc($label) ?> <span class="angka-filter"><?= (int) $jumlah[$kunci] ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($laporan === []): ?>
        <div class="kosong-panel">
            <i class="bi bi-wrench" aria-hidden="true"></i>
            <p class="mb-0">Belum ada laporan<?= $status !== '' ? ' berstatus ' . esc(mb_strtolower(LaporanKerusakanModel::STATUS[$status])) : '' ?>.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="tabel-kos">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Pelapor</th>
                        <th>Kamar</th>
                        <th>Deskripsi</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($laporan as $l): ?>
                        <tr>
                            <td class="text-nowrap"><?= esc(tanggal_indonesia($l['tanggal_lapor'])) ?></td>
                            <td><?= esc($l['nama_pelapor']) ?></td>
                            <td><?= esc($l['nomor_kamar'] ?? '-') ?></td>
                            <td style="min-width: 220px;"><?= esc(mb_strimwidth($l['deskripsi'], 0, 90, '...')) ?></td>
                            <td>
                                <span class="lencana <?= LaporanKerusakanModel::LENCANA[$l['status_perbaikan']] ?? 'lencana-abu' ?>">
                                    <?= esc(LaporanKerusakanModel::STATUS[$l['status_perbaikan']] ?? $l['status_perbaikan']) ?>
                                </span>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="<?= site_url('pemilik/kerusakan/' . $l['id_laporan']) ?>" class="btn btn-sm btn-outline-primary">
                                    <?= $l['status_perbaikan'] === 'selesai' ? 'Lihat' : 'Kelola' ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
