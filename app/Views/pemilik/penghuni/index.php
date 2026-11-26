<?= $this->extend('layouts/pemilik') ?>

<?= $this->section('content') ?>
<?php
/*
 * Data penghuni (Kartu 4 Sprint 4, KF-03).
 * Data dari Pemilik\Penghuni::index(): $penghuni, $cari.
 */
$lencanaSewa = [
    'aktif'               => ['lencana-hijau', 'Aktif'],
    'menunggu_pembayaran' => ['lencana-kuning', 'Menunggu pembayaran'],
    'selesai'             => ['lencana-abu', 'Selesai'],
    'batal'               => ['lencana-merah', 'Batal'],
];
?>
<div class="header-halaman d-flex flex-wrap justify-content-between align-items-end gap-3">
    <div>
        <h1 class="judul-halaman mb-1">Data Penghuni</h1>
        <p class="mb-0" id="jumlahPenghuni"><?= count($penghuni) ?> penghuni<?= $cari !== '' ? ' cocok dengan "' . esc($cari) . '"' : '' ?></p>
    </div>
    <a href="<?= site_url('pemilik/penghuni/tambah') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah Penghuni
    </a>
</div>

<div class="card kartu-panel">
    <form action="<?= site_url('pemilik/penghuni') ?>" method="get" class="form-cari" role="search">
        <i class="bi bi-search" aria-hidden="true"></i>
        <input type="search" name="cari" id="cariPenghuni" class="form-control" value="<?= esc($cari) ?>" autocomplete="off"
            placeholder="Cari nama, username, nomor HP, atau kamar" aria-label="Cari penghuni">
        <button type="submit" class="btn btn-outline-primary">Cari</button>
        <?php if ($cari !== ''): ?>
            <a href="<?= site_url('pemilik/penghuni') ?>" class="btn btn-link">Reset</a>
        <?php endif; ?>
    </form>

    <?php if ($penghuni === []): ?>
        <div class="kosong-panel">
            <i class="bi bi-people" aria-hidden="true"></i>
            <p class="mb-0"><?= $cari !== '' ? 'Tidak ada penghuni yang cocok dengan pencarian.' : 'Belum ada penghuni.' ?></p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="tabel-kos">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Nomor HP</th>
                        <th>Kamar</th>
                        <th>Masa sewa</th>
                        <th>Status sewa</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($penghuni as $p): ?>
                        <?php [$kelas, $label] = $lencanaSewa[$p['status_sewa']] ?? ['lencana-abu', 'Belum ada sewa']; ?>
                        <tr data-cari="<?= esc(mb_strtolower($p['nama'] . ' ' . $p['username'] . ' ' . $p['no_hp'] . ' ' . ($p['nomor_kamar'] ?? ''))) ?>">
                            <td>
                                <div class="fw-semibold"><?= esc($p['nama']) ?></div>
                                <div class="teks-kecil">@<?= esc($p['username']) ?></div>
                            </td>
                            <td><?= esc($p['no_hp']) ?></td>
                            <td><?= esc($p['nomor_kamar'] ?? '-') ?></td>
                            <td class="text-nowrap">
                                <?php if ($p['tanggal_mulai']): ?>
                                    <?= esc(tanggal_indonesia($p['tanggal_mulai'])) ?>
                                    <div class="teks-kecil">s.d. <?= esc(tanggal_indonesia($p['tanggal_berakhir'])) ?></div>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><span class="lencana <?= $kelas ?>"><?= esc($label) ?></span></td>
                            <td class="text-end text-nowrap">
                                <a href="<?= site_url('pemilik/penghuni/' . $p['id_pengguna'] . '/ubah') ?>"
                                    class="btn btn-sm btn-outline-primary">Ubah</a>
                                <form action="<?= site_url('pemilik/penghuni/' . $p['id_pengguna'] . '/hapus') ?>" method="post"
                                    class="d-inline"
                                    onsubmit="return confirm('Hapus <?= esc($p['nama'], 'js') ?>? Akun beserta seluruh riwayat sewa dan pembayarannya ikut terhapus dan tidak bisa dikembalikan.');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-tolak">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="kosong-panel d-none" id="cariKosong">
                <i class="bi bi-search" aria-hidden="true"></i>
                <p class="mb-0">Tidak ada penghuni yang cocok dengan pencarian.</p>
            </div>
        </div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>

<?= $this->section('script') ?>
<script>
    // Pencarian langsung: tabel tersaring setiap kali huruf diketik.
    // Tombol Cari dan tombol Enter tetap memakai pencarian di server.
    (function () {
        var input = document.getElementById('cariPenghuni');
        var baris = document.querySelectorAll('.tabel-kos tbody tr[data-cari]');
        var jumlah = document.getElementById('jumlahPenghuni');
        var kosong = document.getElementById('cariKosong');
        var tabel = document.querySelector('.tabel-kos');
        if (!input || baris.length === 0) return;

        function saring() {
            var kata = input.value.trim().toLowerCase();
            var tampil = 0;
            baris.forEach(function (tr) {
                var cocok = kata === '' || tr.dataset.cari.indexOf(kata) !== -1;
                tr.hidden = !cocok;
                if (cocok) tampil++;
            });
            jumlah.textContent = kata === ''
                ? baris.length + ' penghuni'
                : tampil + ' dari ' + baris.length + ' penghuni cocok dengan "' + input.value.trim() + '"';
            kosong.classList.toggle('d-none', tampil !== 0);
            tabel.classList.toggle('d-none', tampil === 0);
        }

        input.addEventListener('input', saring);
    })();
</script>
<?= $this->endSection() ?>
