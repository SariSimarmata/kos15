<?php $this->setVar('judul', 'Pemesanan Kamar'); // judul remah dan tab browser sesuai Figma ?>
<?= $this->extend('layouts/pemilik') ?>

<?= $this->section('content') ?>
<?php
helper('tanggal');

/*
 * Tampilan sesuai Figma "Kos 15 – Halaman Pemilik (Revisi)" > Pemesanan Kamar.
 * Hanya file tampilan ini yang diubah; controller tetap Pemesanan::daftar().
 * Data tambahan untuk tab dan panel detail (harga, username, tab Disetujui/Ditolak)
 * dibaca di sini dengan query baca saja.
 *
 * ?status=pending|approved|rejected memilih tab, ?id=... memilih pemesanan di panel detail.
 */
$zona    = new DateTimeZone('Asia/Jakarta');
$hariIni = (new DateTime('today', $zona))->format('Y-m-d');
$kemarin = (new DateTime('yesterday', $zona))->format('Y-m-d');

$status = (string) service('request')->getGet('status');
if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
    $status = 'pending';
}

// Daftar pemesanan untuk tab aktif (pending: paling lama menunggu di atas)
$arah = $status === 'pending' ? 'ASC' : 'DESC';
$pemesanan = db_connect()->table('pemesanan')
    ->select('pemesanan.*, pengguna.nama AS nama_calon, pengguna.username, pengguna.no_hp, '
        . 'kamar.nomor_kamar, kamar.status_kamar, kamar.harga_sewa_tahunan')
    ->join('pengguna', 'pengguna.id_pengguna = pemesanan.id_pengguna')
    ->join('kamar', 'kamar.id_kamar = pemesanan.id_kamar')
    ->where('pemesanan.status_pengajuan', $status)
    ->orderBy('pemesanan.tanggal_pengajuan', $arah)
    ->orderBy('pemesanan.id_pemesanan', $arah)
    ->get()->getResultArray();

// Angka di tiap tab
$jumlah = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
foreach (db_connect()->table('pemesanan')->select('status_pengajuan, COUNT(*) AS n')
    ->groupBy('status_pengajuan')->get()->getResultArray() as $b) {
    $jumlah[$b['status_pengajuan']] = (int) $b['n'];
}

// Pemesanan yang dibuka di panel detail; bawaan: baris pertama
$idDipilih = (int) service('request')->getGet('id');
$dipilih = $pemesanan[0] ?? null;
foreach ($pemesanan as $p) {
    if ((int) $p['id_pemesanan'] === $idDipilih) {
        $dipilih = $p;
        break;
    }
}

// Sama dengan perhitungan di Pemesanan::setujui(): tanggal mulai + 1 tahun
$tanggalBerakhir = $dipilih
    ? (new DateTime($dipilih['tanggal_mulai_sewa']))->modify('+1 year')->format('Y-m-d')
    : null;

// "hari ini", "kemarin", atau "14 Nov 2026"
$tanggalRelatif = static function (string $waktu) use ($hariIni, $kemarin): string {
    $hari = substr($waktu, 0, 10);

    return $hari === $hariIni ? 'hari ini' : ($hari === $kemarin ? 'kemarin' : tanggal_indonesia($waktu));
};

// "14 Nov 2026, 09.15"
$tanggalWaktu = static fn (string $waktu): string => tanggal_indonesia($waktu) . ', ' . (new DateTime($waktu))->format('H.i');

$tab = [
    'pending'  => 'Menunggu',
    'approved' => 'Disetujui',
    'rejected' => 'Ditolak',
];
$lencana = [
    'pending'  => ['lencana-kuning', 'Menunggu'],
    'approved' => ['lencana-hijau', 'Disetujui'],
    'rejected' => ['lencana-merah', 'Ditolak'],
];
$kosong = [
    'pending'  => 'Tidak ada pemesanan yang menunggu keputusan.',
    'approved' => 'Belum ada pemesanan yang disetujui.',
    'rejected' => 'Belum ada pemesanan yang ditolak.',
];

$rupiah = static fn ($n): string => 'Rp ' . number_format((float) $n, 0, ',', '.');

// "Bayu Mahendra" menjadi "BM"
$inisial = static function (string $nama): string {
    $kata = preg_split('/\s+/', trim($nama)) ?: [];
    $huruf = '';
    foreach (array_slice($kata, 0, 2) as $k) {
        $huruf .= mb_strtoupper(mb_substr($k, 0, 1));
    }

    return $huruf !== '' ? $huruf : '?';
};

$urlDetail = static fn (int $id): string => site_url('pemilik/pemesanan') . '?status=' . $status . '&id=' . $id . '#detail';
?>

<div class="header-halaman">
    <h1 class="judul-halaman mb-1">Pemesanan Kamar</h1>
    <p class="mb-0">Periksa data calon penghuni, lalu setujui atau tolak pemesanan</p>
</div>

<div class="pemesanan-grid">
    <!-- Daftar pemesanan -->
    <section class="card kartu-panel" aria-label="Daftar pemesanan">
        <nav class="tab-status" aria-label="Status pemesanan">
            <?php foreach ($tab as $kunci => $label): ?>
                <a href="<?= site_url('pemilik/pemesanan') . '?status=' . $kunci ?>"
                    class="<?= $kunci === $status ? 'aktif' : '' ?>" <?= $kunci === $status ? 'aria-current="page"' : '' ?>>
                    <?= esc($label) ?>
                    <span class="angka"><?= (int) ($jumlah[$kunci] ?? 0) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if (empty($pemesanan)): ?>
            <div class="banner-info"><?= esc($kosong[$status]) ?></div>
        <?php endif; ?>

        <?php foreach ($pemesanan as $p): ?>
            <?php
            $aktif = $dipilih !== null && (int) $p['id_pemesanan'] === (int) $dipilih['id_pemesanan'];
            $kamarTerisi = $status === 'pending' && $p['status_kamar'] !== 'kosong';
            [$kelas, $labelStatus] = $kamarTerisi ? ['lencana-merah', 'Kamar terisi'] : $lencana[$status];
            ?>
            <a href="<?= $urlDetail((int) $p['id_pemesanan']) ?>" class="baris-pemesanan <?= $aktif ? 'aktif' : '' ?>"
                <?= $aktif ? 'aria-current="true"' : '' ?>>
                <span class="avatar-inisial"><?= esc($inisial($p['nama_calon'])) ?></span>
                <span class="flex-grow-1 min-w-0">
                    <span class="nama"><?= esc($p['nama_calon']) ?></span>
                    <span class="sub">Kamar <?= esc($p['nomor_kamar']) ?> · Mulai sewa <?= esc(tanggal_indonesia($p['tanggal_mulai_sewa'])) ?></span>
                </span>
                <span class="text-end d-none d-sm-block">
                    <span class="nama"><?= esc($rupiah($p['harga_sewa_tahunan'])) ?> / tahun</span>
                    <span class="sub">Diajukan <?= esc($tanggalRelatif($p['tanggal_pengajuan'])) ?></span>
                </span>
                <span class="lencana <?= $kelas ?>"><?= esc($labelStatus) ?></span>
            </a>
        <?php endforeach; ?>
    </section>

    <!-- Detail pemesanan -->
    <?php if ($dipilih !== null): ?>
        <?php
        $d = $dipilih;
        $nomor = \App\Models\PemesananModel::nomor((int) $d['id_pemesanan']);
        $pending = $d['status_pengajuan'] === 'pending';
        $kamarTerisi = $pending && $d['status_kamar'] !== 'kosong';
        $namaDepan = explode(' ', trim($d['nama_calon']))[0];
        [$kelas, $labelStatus] = $lencana[$d['status_pengajuan']];
        ?>
        <section id="detail" class="card kartu-panel" aria-label="Detail pemesanan">
            <div class="d-flex justify-content-between align-items-center gap-2">
                <h2 class="judul-panel mb-0">Detail Pemesanan</h2>
                <?php if (!$pending): ?>
                    <span class="lencana <?= $kelas ?>"><?= esc($labelStatus) ?></span>
                <?php endif; ?>
            </div>

            <div class="d-flex align-items-center gap-3">
                <span class="avatar-inisial besar"><?= esc($inisial($d['nama_calon'])) ?></span>
                <div>
                    <div class="nama-detail"><?= esc($d['nama_calon']) ?></div>
                    <div class="teks-kecil"><?= esc($d['no_hp']) ?> · <?= esc($d['username']) ?></div>
                </div>
            </div>

            <div class="kotak-ringkasan">
                <div class="baris-info"><span>Nomor pemesanan</span><span><?= esc($nomor) ?></span></div>
                <div class="baris-info"><span>Kamar dipesan</span><span>Kamar <?= esc($d['nomor_kamar']) ?></span></div>
                <div class="baris-info"><span>Harga sewa per tahun</span><span><?= esc($rupiah($d['harga_sewa_tahunan'])) ?></span></div>
                <div class="baris-info"><span>Tanggal mulai sewa</span><span><?= esc(tanggal_indonesia($d['tanggal_mulai_sewa'])) ?></span></div>
                <?php if ($pending): ?>
                    <div class="baris-info"><span>Berakhir otomatis</span><span><?= esc(tanggal_indonesia($tanggalBerakhir)) ?></span></div>
                <?php endif; ?>
                <div class="baris-info"><span>Diajukan</span><span><?= esc($tanggalWaktu($d['tanggal_pengajuan'])) ?></span></div>
            </div>

            <?php if ($kamarTerisi): ?>
                <div class="kotak-merah">
                    Kamar <?= esc($d['nomor_kamar']) ?> sudah terisi, sehingga pemesanan ini tidak bisa disetujui.
                    Tolak pemesanan ini atau minta calon memilih kamar lain.
                </div>
            <?php elseif ($pending): ?>
                <div class="kotak-otomatis">
                    <div class="judul-otomatis">Saat disetujui, sistem otomatis:</div>
                    <ul>
                        <li><i class="bi bi-check2" aria-hidden="true"></i>Membuat sewa 1 tahun: <?= esc(tanggal_indonesia($d['tanggal_mulai_sewa'])) ?> – <?= esc(tanggal_indonesia($tanggalBerakhir)) ?>, berstatus menunggu pembayaran</li>
                        <li><i class="bi bi-check2" aria-hidden="true"></i>Mengubah Kamar <?= esc($d['nomor_kamar']) ?> menjadi terisi</li>
                        <li><i class="bi bi-check2" aria-hidden="true"></i>Mengubah peran <?= esc($namaDepan) ?> menjadi penghuni</li>
                        <li><i class="bi bi-check2" aria-hidden="true"></i>Mengirim notifikasi ke <?= esc($namaDepan) ?></li>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($pending): ?>
                <p class="teks-kecil mb-0">Pelunasan wajib dibayar sebelum tanggal mulai sewa. Jika tidak, sewa dapat dibatalkan.</p>

                <div class="d-flex gap-3">
                    <form action="<?= site_url('pemilik/pemesanan/' . (int) $d['id_pemesanan'] . '/tolak') ?>" method="post" class="flex-fill"
                        onsubmit="return confirm('Tolak pemesanan <?= esc($nomor, 'js') ?>?');">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-tolak w-100"><i class="bi bi-x-lg me-2" aria-hidden="true"></i>Tolak</button>
                    </form>
                    <form action="<?= site_url('pemilik/pemesanan/' . (int) $d['id_pemesanan'] . '/setujui') ?>" method="post" class="flex-fill">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-primary btn-aksi w-100"><i class="bi bi-check2 me-2" aria-hidden="true"></i>Setujui</button>
                    </form>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
