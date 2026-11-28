<?= $this->extend('layouts/pemilik') ?>

<?= $this->section('content') ?>
<?php
/*
 * Input meteran bulanan (Kartu 9). Data dari Pemilik\Meteran::index():
 * $periode (YYYY-MM), $periodeMaks, $jatuhTempo, $info (InfoKosModel::ambil()), $baris (sewa aktif pada bulan itu).
 */
use App\Models\TagihanBulananModel;
use App\Models\TagihanModel;

$galatBaris = (array) session('galat_baris');
$rincian    = (array) session('rincian_meteran');
$namaPeriode = TagihanModel::periode(['periode' => $periode, 'jenis_tagihan' => 'listrik_air']);
$angka = static fn (float $n): string => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
$nilai = static fn (int $id, string $kolom, string $bawaan = ''): string => (string) old('baris.' . $id . '.' . $kolom, $bawaan);
?>
<div class="header-halaman">
    <h1 class="judul-halaman mb-1">Input Meteran Bulanan</h1>
    <p class="mb-0">Masukkan angka meteran listrik dan air serta biaya sampah tiap penghuni. Sistem menghitung tagihannya memakai tarif di Info Kos.</p>
</div>

<div class="card kartu-panel mb-3">
    <form action="<?= site_url('pemilik/meteran') ?>" method="get" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label for="periode" class="form-label">Bulan pemakaian</label>
            <input type="month" id="periode" name="periode" class="form-control"
                value="<?= esc($periode) ?>" max="<?= esc($periodeMaks) ?>" required>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-outline-primary">Tampilkan</button>
        </div>
        <div class="col-md-5">
            <div class="kotak-ringkasan">
                <div class="baris-info"><span>Tarif listrik</span><span class="fw-semibold"><?= esc(rupiah($info['tarif_listrik_per_kwh'])) ?> / kWh</span></div>
                <div class="baris-info"><span>Tarif air</span><span class="fw-semibold"><?= esc(rupiah($info['tarif_air_per_m3'])) ?> / m³</span></div>
            </div>
        </div>
    </form>
    <p class="teks-kecil mb-0">
        Tarif diubah di <a href="<?= site_url('pemilik/info-kos') ?>">Info Kos dan Tarif</a>.
        Tagihan <?= esc($namaPeriode) ?> jatuh tempo <?= esc(tanggal_indonesia($jatuhTempo)) ?>
        (tanggal <?= TagihanBulananModel::TANGGAL_JATUH_TEMPO ?> bulan berikutnya, paling cepat <?= TagihanBulananModel::HARI_MINIMAL ?> hari dari hari ini).
    </p>
</div>

<?php if ($rincian !== []): ?>
    <div class="card kartu-panel mb-3">
        <h2 class="judul-panel mb-0">Hasil perhitungan</h2>
        <?php foreach ($rincian as $r): ?>
            <div class="kotak-ringkasan">
                <div class="fw-semibold mb-1"><?= esc($r['nama']) ?> (kamar <?= esc($r['kamar']) ?>)</div>
                <div class="baris-info"><span>Listrik <?= esc($r['kwh']) ?> kWh × <?= esc(rupiah($r['tarif_listrik'])) ?></span><span><?= esc(rupiah($r['listrik'])) ?></span></div>
                <div class="baris-info"><span>Air <?= esc($r['m3']) ?> m³ × <?= esc(rupiah($r['tarif_air'])) ?></span><span><?= esc(rupiah($r['air'])) ?></span></div>
                <div class="baris-info"><span>Sampah</span><span><?= esc(rupiah($r['sampah'])) ?></span></div>
                <div class="baris-info fw-semibold"><span>Total, jatuh tempo <?= esc(tanggal_indonesia($r['jatuh_tempo'])) ?></span><span><?= esc(rupiah($r['total'])) ?></span></div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form action="<?= site_url('pemilik/meteran/simpan') ?>" method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="periode" value="<?= esc($periode) ?>">

    <div class="card kartu-panel">
        <h2 class="judul-panel mb-0">Penghuni, <?= esc($namaPeriode) ?></h2>

        <?php if ($baris === []): ?>
            <div class="kosong-panel">
                <i class="bi bi-speedometer2" aria-hidden="true"></i>
                <p class="mb-0">Tidak ada penghuni dengan sewa aktif pada bulan ini.</p>
            </div>
        <?php else: ?>
            <p class="teks-kecil mb-0">
                Isi meter awal dan meter akhir bulan ini (angka di meteran). Pemakaian = meter akhir dikurangi meter awal.
                Baris yang dikosongkan dilewati. Tagihan satu bulan hanya bisa dibuat sekali per penghuni.
            </p>
            <div class="table-responsive">
                <table class="tabel-kos">
                    <thead>
                        <tr>
                            <th>Penghuni</th>
                            <th>Listrik awal (kWh)</th>
                            <th>Listrik akhir (kWh)</th>
                            <th>Air awal (m³)</th>
                            <th>Air akhir (m³)</th>
                            <th>Sampah (Rp)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($baris as $b): ?>
                            <?php $id = (int) $b['id_sewa']; $sudah = $b['id_tagih_bulanan'] !== null; ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= esc($b['nama_penghuni']) ?></div>
                                    <div class="teks-kecil">Kamar <?= esc($b['nomor_kamar']) ?></div>
                                    <?php if (isset($galatBaris[$id])): ?>
                                        <div class="invalid-feedback d-block"><?= esc($galatBaris[$id]) ?></div>
                                    <?php endif; ?>
                                </td>
                                <?php if ($sudah): ?>
                                    <td colspan="5">
                                        <span class="lencana lencana-hijau">Tagihan sudah dibuat</span>
                                        <span class="teks-kecil ms-2">
                                            listrik <?= esc(rupiah($b['tagihan_listrik'])) ?>,
                                            air <?= esc(rupiah($b['tagihan_air'])) ?>,
                                            sampah <?= esc(rupiah($b['tagihan_sampah'])) ?>
                                        </span>
                                    </td>
                                <?php else: ?>
                                    <?php foreach (['listrik_awal', 'listrik_akhir', 'air_awal', 'air_akhir'] as $kolom): ?>
                                        <td>
                                            <input type="text" inputmode="decimal" name="baris[<?= $id ?>][<?= $kolom ?>]"
                                                class="form-control form-control-sm <?= isset($galatBaris[$id]) ? 'is-invalid' : '' ?>"
                                                value="<?= esc($nilai($id, $kolom)) ?>" aria-label="<?= esc($kolom) ?> <?= esc($b['nama_penghuni']) ?>">
                                        </td>
                                    <?php endforeach; ?>
                                    <td>
                                        <input type="text" inputmode="numeric" name="baris[<?= $id ?>][sampah]"
                                            class="form-control form-control-sm <?= isset($galatBaris[$id]) ? 'is-invalid' : '' ?>"
                                            value="<?= esc($nilai($id, 'sampah', $b['sampah_terakhir'] !== null ? (string) (int) $b['sampah_terakhir'] : '')) ?>"
                                            aria-label="Sampah <?= esc($b['nama_penghuni']) ?>">
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($baris !== []): ?>
        <div class="d-flex gap-2 justify-content-end mt-3">
            <button type="submit" class="btn btn-primary" onclick="return confirm('Tagihan yang sudah dibuat tidak bisa dibuat ulang untuk bulan yang sama. Lanjutkan?');">Hitung dan Buat Tagihan</button>
        </div>
    <?php endif; ?>
</form>
<?= $this->endSection() ?>
