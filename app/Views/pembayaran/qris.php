<?= $this->extend('layouts/penghuni') ?>

<?= $this->section('content') ?>
<?php
$status = $bayar['status_pembayaran']; // menunggu | berhasil | kedaluwarsa | gagal
$jenis  = \App\Models\TagihanModel::LABEL_JENIS[$bayar['jenis_tagihan']] ?? $bayar['jenis_tagihan'];
$tagihan = [
    'jenis_tagihan'       => $bayar['jenis_tagihan'],
    'jumlah_tagihan'      => $bayar['jumlah_tagihan'],
    'tanggal_jatuh_tempo' => $bayar['tanggal_jatuh_tempo'],
    'status_tagihan'      => $bayar['status_tagihan'],
    'nomor_kamar'         => $bayar['nomor_kamar'],
];
$tampil = static fn (bool $ya): string => $ya ? '' : 'd-none';
?>


<div class="row g-4">
    <div class="col-lg-4 order-2 order-lg-1 d-flex flex-column gap-3">
        <?= view('pembayaran/_ringkasan_tagihan', ['t' => $tagihan]) ?>
        <?= view('pembayaran/_alur') ?>
    </div>

    <div class="col-lg-8 order-1 order-lg-2">
        <div class="card p-4 text-center" aria-live="polite">

            <!-- 1. Menunggu pembayaran -->
            <div data-tampil="menunggu" class="<?= $tampil($status === 'menunggu') ?>">
                <span class="lencana lencana-kuning mb-2">Menunggu pembayaran</span>
                <h1 class="judul-halaman mb-1">Pindai kode QRIS</h1>
                <p class="teks-kecil mb-3">Nominal sudah terisi otomatis. Kamu tidak perlu mengetik jumlah bayar.</p>

                <div class="kotak-putus mx-auto mb-3" style="max-width: 320px;">
                    <?php if (! empty($qrString)): ?>
                        <!-- Duitku: QR digambar dari qrString di browser -->
                        <img data-qr-string="<?= esc($qrString, 'attr') ?>" alt="Kode QRIS pembayaran <?= esc($jenis, 'attr') ?>"
                            class="img-fluid bg-white rounded" width="260" height="260">
                    <?php elseif (str_starts_with((string) $bayar['order_id'], 'KOSD-')): ?>
                        <!-- Duitku tanpa qrString (cache hilang): buka halaman pembayaran Duitku -->
                        <p class="teks-kecil mb-2">Gambar QR tidak tersedia di halaman ini.</p>
                        <a href="<?= esc($bayar['qris_url'], 'attr') ?>" target="_blank" rel="noopener" class="btn btn-outline-primary">
                            <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i> Buka Halaman QRIS Duitku
                        </a>
                    <?php else: ?>
                        <img src="<?= esc($bayar['qris_url'], 'attr') ?>" alt="Kode QRIS pembayaran <?= esc($jenis, 'attr') ?>"
                            class="img-fluid bg-white rounded" width="260" height="260">
                    <?php endif; ?>
                </div>

                <div class="nominal-besar mb-1"><?= rupiah($bayar['jumlah_bayar']) ?></div>
                <p class="mb-3">Berlaku <strong data-hitung-mundur>--:--</strong> lagi</p>

                <div class="banner-info text-start">
                    <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                    Status diperbarui otomatis. Tidak perlu memuat ulang halaman.
                </div>
            </div>

            <!-- 2. Berhasil -->
            <div data-tampil="berhasil" class="<?= $tampil($status === 'berhasil') ?>">
                <span class="ikon-sukses mx-auto mb-3"><i class="bi bi-check-lg" aria-hidden="true"></i></span>
                <h1 class="judul-halaman mb-1">Pembayaran berhasil</h1>
                <p class="teks-kecil mb-3">Tagihan <?= esc($jenis) ?> sudah <span class="lencana lencana-hijau">Lunas</span></p>
                <div class="kotak-ringkasan text-start mb-3">
                    <div class="baris-info"><span>Nominal</span><span><?= rupiah($bayar['jumlah_bayar']) ?></span></div>
                    <div class="baris-info"><span>Metode</span><span>QRIS</span></div>
                    <div class="baris-info"><span>Nomor order</span><span><?= esc($bayar['order_id']) ?></span></div>
                </div>
                <a href="<?= site_url('penghuni/tagihan') ?>" class="btn btn-primary w-100">Kembali ke Tagihan</a>
            </div>

            <!-- 3. Kedaluwarsa atau gagal -->
            <div data-tampil="kedaluwarsa gagal" class="<?= $tampil(in_array($status, ['kedaluwarsa', 'gagal'], true)) ?>">
                <span class="lencana lencana-merah mb-2" data-label-gagal><?= $status === 'gagal' ? 'Gagal' : 'Kedaluwarsa' ?></span>
                <h1 class="judul-halaman mb-1" data-judul-gagal>
                    <?= $status === 'gagal' ? 'Pembayaran gagal' : 'Kode QRIS kedaluwarsa' ?>
                </h1>
                <div class="kotak-merah mb-3" data-pesan-gagal>
                    <?= $status === 'gagal'
                        ? 'Pembayaran dibatalkan atau ditolak. Tagihanmu belum lunas.'
                        : 'Waktu pembayaran habis dan tagihanmu belum lunas. Buat kode QRIS baru untuk membayar.' ?>
                </div>
                <?php if ($bayar['status_tagihan'] !== 'lunas'): ?>
                    <form action="<?= site_url('penghuni/tagihan/' . $bayar['id_tagihan'] . '/qris') ?>" method="post">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i> Buat Kode QRIS Baru
                        </button>
                    </form>
                <?php endif; ?>
                <a href="<?= site_url('penghuni/tagihan') ?>" class="btn btn-outline-primary w-100 mt-2">Kembali ke Tagihan</a>
            </div>

            <ul class="garis-status text-start mb-0 mt-4">
                <li class="selesai">Kode QRIS dibuat</li>
                <li data-garis-bayar class="<?= $status === 'berhasil' ? 'selesai' : ($status === 'menunggu' ? 'sekarang' : 'gagal') ?>">
                    <span data-teks-garis><?= $status === 'berhasil' ? 'Pembayaran diterima' : ($status === 'menunggu' ? 'Menunggu pembayaran' : 'Pembayaran tidak selesai') ?></span>
                </li>
                <li data-garis-lunas class="<?= $status === 'berhasil' ? 'selesai' : '' ?>">Tagihan lunas</li>
            </ul>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('script') ?>
<?php if (! empty($qrString)): ?>
<script src="<?= base_url('js/qrcode-generator.js') ?>"></script>
<script>
    // Gambar QRIS dari qrString (Duitku). Koreksi error M sesuai standar QRIS.
    document.querySelectorAll('[data-qr-string]').forEach(function (img) {
        var qr = qrcode(0, 'M');
        qr.addData(img.getAttribute('data-qr-string'));
        qr.make();
        img.src = qr.createDataURL(8, 16);
    });
</script>
<?php endif; ?>
<script>
    // Polling status tiap 3 detik + hitung mundur, tanpa muat ulang halaman
    (function () {
        var urlStatus = <?= json_encode(site_url('penghuni/pembayaran/' . $bayar['id_pembayaran'] . '/status')) ?>;
        var status = <?= json_encode($status) ?>;
        var sisa = <?= (int) $sisaDetik ?>;
        var elHitung = document.querySelector('[data-hitung-mundur]');
        var pollTimer = null;
        var detikTimer = null;

        function tampilkan(st) {
            document.querySelectorAll('[data-tampil]').forEach(function (el) {
                el.classList.toggle('d-none', el.getAttribute('data-tampil').split(' ').indexOf(st) === -1);
            });

            var garis = document.querySelector('[data-garis-bayar]');
            var teks = document.querySelector('[data-teks-garis]');
            var lunas = document.querySelector('[data-garis-lunas]');
            var lencana = document.querySelector('[data-lencana-tagihan]');

            if (st === 'berhasil') {
                garis.className = 'selesai'; teks.textContent = 'Pembayaran diterima';
                lunas.className = 'selesai';
                if (lencana) { lencana.className = 'lencana lencana-hijau'; lencana.textContent = 'Lunas'; }
            } else if (st === 'kedaluwarsa' || st === 'gagal') {
                garis.className = 'gagal'; teks.textContent = 'Pembayaran tidak selesai';
                var gagal = st === 'gagal';
                document.querySelector('[data-label-gagal]').textContent = gagal ? 'Gagal' : 'Kedaluwarsa';
                document.querySelector('[data-judul-gagal]').textContent = gagal ? 'Pembayaran gagal' : 'Kode QRIS kedaluwarsa';
                document.querySelector('[data-pesan-gagal]').textContent = gagal
                    ? 'Pembayaran dibatalkan atau ditolak. Tagihanmu belum lunas.'
                    : 'Waktu pembayaran habis dan tagihanmu belum lunas. Buat kode QRIS baru untuk membayar.';
            }
        }

        function tulisSisa() {
            if (!elHitung) return;
            var m = Math.floor(sisa / 60), d = sisa % 60;
            elHitung.textContent = (m < 10 ? '0' : '') + m + ':' + (d < 10 ? '0' : '') + d;
        }

        function berhenti() {
            clearInterval(pollTimer);
            clearInterval(detikTimer);
        }

        function cekStatus() {
            fetch(urlStatus, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, cache: 'no-store' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (!data) return;
                    sisa = data.sisa_detik;
                    tulisSisa();
                    if (data.status_pembayaran !== status) {
                        status = data.status_pembayaran;
                        tampilkan(status);
                    }
                    if (status !== 'menunggu') berhenti();
                })
                .catch(function () { /* jaringan putus sesaat: coba lagi di putaran berikutnya */ });
        }

        if (status !== 'menunggu') return;

        tulisSisa();
        detikTimer = setInterval(function () {
            if (sisa > 0) { sisa--; tulisSisa(); }
            if (sisa === 0) cekStatus(); // waktu habis: minta server menandai kedaluwarsa
        }, 1000);
        pollTimer = setInterval(cekStatus, 3000);
    })();
</script>
<?= $this->endSection() ?>
