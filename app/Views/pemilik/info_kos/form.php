<?= $this->extend('layouts/pemilik') ?>

<?= $this->section('content') ?>
<?php
/*
 * Form info kos dan tarif. Data dari Pemilik\InfoKos::index(): $info (InfoKosModel::ambil()).
 * Tabel info_kos hanya satu baris, jadi tidak ada tombol tambah atau hapus.
 */
use App\Models\InfoKosModel;

$errors = (array) session('errors');
$nilai  = static fn (string $kolom, $bawaan = '') => (string) old($kolom, $bawaan);
$salah  = static fn (string $kolom) => isset($errors[$kolom]) ? 'is-invalid' : '';
$pesan  = static fn (string $kolom) => isset($errors[$kolom])
    ? '<div class="invalid-feedback d-block">' . esc($errors[$kolom]) . '</div>' : '';

// Tarif ditampilkan tanpa ",00" bila bulat (1500.00 -> 1500)
$angka = static fn (float $n): string => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
?>
<div class="header-halaman">
    <h1 class="judul-halaman mb-1">Info Kos dan Tarif</h1>
    <p class="mb-0">Tarif di sini dipakai untuk tagihan listrik dan air, denda piket, dan biaya tamu menginap.</p>
</div>

<form action="<?= site_url('pemilik/info-kos/simpan') ?>" method="post" novalidate>
    <?= csrf_field() ?>

    <div class="card kartu-panel">
        <h2 class="judul-panel mb-0">Informasi umum</h2>
        <div class="row g-3">
            <div class="col-12">
                <label for="lokasi" class="form-label">Lokasi</label>
                <input type="text" id="lokasi" name="lokasi" maxlength="255" class="form-control <?= $salah('lokasi') ?>"
                    value="<?= esc($nilai('lokasi', $info['lokasi'])) ?>" placeholder="Alamat lengkap kos">
                <?= $pesan('lokasi') ?>
            </div>
            <div class="col-md-6">
                <label for="no_whatsapp" class="form-label">Nomor WhatsApp</label>
                <input type="tel" id="no_whatsapp" name="no_whatsapp" maxlength="20" inputmode="tel"
                    class="form-control <?= $salah('no_whatsapp') ?>"
                    value="<?= esc($nilai('no_whatsapp', $info['no_whatsapp'])) ?>" placeholder="081234567890">
                <?= $pesan('no_whatsapp') ?>
                <div class="form-text">Awali dengan 08 atau 628. Dipakai tombol WhatsApp di katalog dan survei.</div>
            </div>
            <div class="col-md-6">
                <label for="jadwal_pengingat" class="form-label">Jadwal pengingat (hari sebelum jatuh tempo)</label>
                <input type="text" id="jadwal_pengingat" name="jadwal_pengingat" maxlength="50"
                    class="form-control <?= $salah('jadwal_pengingat') ?>"
                    value="<?= esc($nilai('jadwal_pengingat', $info['jadwal_pengingat'])) ?>" placeholder="7,3,1">
                <?= $pesan('jadwal_pengingat') ?>
                <div class="form-text">Angka dipisah koma, mis. 7,3,1 berarti pengingat H-7, H-3, dan H-1.</div>
            </div>
        </div>
    </div>

    <div class="card kartu-panel mt-3">
        <h2 class="judul-panel mb-0">Tarif</h2>
        <div class="row g-3">
            <?php foreach (InfoKosModel::TARIF as $kolom => $label): ?>
                <?php
                $satuan = match ($kolom) {
                    'tarif_listrik_per_kwh' => 'Rupiah per kWh',
                    'tarif_air_per_m3'      => 'Rupiah per m³',
                    'denda_piket_per_hari'  => 'Rupiah per hari tidak piket',
                    default                 => 'Rupiah per hari per tamu',
                };
                ?>
                <div class="col-md-6">
                    <label for="<?= $kolom ?>" class="form-label"><?= esc($label) ?></label>
                    <div class="input-group has-validation">
                        <span class="input-group-text">Rp</span>
                        <input type="number" id="<?= $kolom ?>" name="<?= $kolom ?>" min="0" step="any" inputmode="decimal"
                            class="form-control <?= $salah($kolom) ?>"
                            value="<?= esc($nilai($kolom, $angka($info[$kolom]))) ?>">
                    </div>
                    <?= $pesan($kolom) ?>
                    <div class="form-text"><?= esc($satuan) ?>. Tidak boleh negatif.</div>
                </div>
            <?php endforeach; ?>
            <div class="col-md-6">
                <label for="batas_menginap_per_minggu" class="form-label">Batas menginap per minggu</label>
                <div class="input-group has-validation">
                    <input type="number" id="batas_menginap_per_minggu" name="batas_menginap_per_minggu" min="1" max="7" step="1"
                        class="form-control <?= $salah('batas_menginap_per_minggu') ?>"
                        value="<?= esc($nilai('batas_menginap_per_minggu', (string) $info['batas_menginap_per_minggu'])) ?>">
                    <span class="input-group-text">hari</span>
                </div>
                <?= $pesan('batas_menginap_per_minggu') ?>
                <div class="form-text">Jumlah malam tamu boleh menginap dalam satu minggu (1 sampai 7).</div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 justify-content-end mt-3">
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
    </div>
</form>
<?= $this->endSection() ?>
