# Kos 15 – Sistem Informasi Manajemen Kos dan Pembayaran Otomatis

Aplikasi web responsif untuk mengelola kos dengan 19 kamar: katalog kamar, pemesanan, tagihan, pembayaran QRIS/tunai, piket, pengunjung menginap, dan laporan kerusakan.

**Tech stack:** CodeIgniter 4 · PHP 8.2 · MySQL · Git/GitHub

## Anggota Kelompok

| Nama     | Peran             |
| -------- | ----------------- |
| Sari Y.  |                   |
| Bibahh   |                   |
| Piaa     |                   |
| Piqaa    |                   |
| Enjel    |                   |

---

## 1. Cara Menjalankan Proyek

Syarat: PHP 8.2 (ekstensi `intl`, `mbstring`, `mysqlnd`, `curl`), Composer, Git, MySQL/MariaDB.

```bash
git clone https://github.com/SariSimarmata/kos15.git
cd kos15
composer install
copy .env.example .env      # Windows (Mac/Linux: cp)
```

1. Buat database kosong bernama `kos15` di phpMyAdmin.
2. Buka `.env`, sesuaikan username dan password MySQL di laptop masing-masing.
3. Jalankan server:

```bash
php spark serve
```

4. Buka http://localhost:8080. Jika halaman selamat datang CI4 tampil, proyek berjalan.

> `.env` berisi pengaturan pribadi dan **tidak boleh di-commit**.

---

## 2. Aturan Git

1. **Satu kartu = satu cabang.** Format: `fitur/<nama-kartu>`
   Contoh: `fitur/katalog-kamar`, `fitur/login-register`
2. Jangan commit langsung ke `main`. Semua perubahan lewat **Pull Request**.
3. **Commit kecil dan sering**, dengan pesan jelas:
   - `feat:` fitur baru → `feat: tambah halaman katalog kamar`
   - `fix:` perbaikan bug → `fix: perbaiki validasi nomor HP`
   - `docs:` dokumentasi, `chore:` konfigurasi
4. **Pull sebelum push:** `git pull origin main`
5. **`.env` dilarang di-commit.** Pakai `.env.example`.
6. Dilarang `git push --force`. Kalau ada konflik, tanya di grup.
7. Pull Request di-review minimal satu anggota lain sebelum merge.

Alur kerja harian:

```bash
git checkout main
git pull origin main
git checkout -b fitur/nama-kartu
# ...kerja, lalu...
git add .
git commit -m "feat: deskripsi singkat"
git pull origin main
git push -u origin fitur/nama-kartu
# buka Pull Request di GitHub
```

---

## 3. Aturan Penamaan

| Hal        | Aturan                                          | Contoh                                |
| ---------- | ----------------------------------------------- | ------------------------------------- |
| Tabel      | Sama persis dengan ERD, huruf kecil, snake_case | `pengguna`, `tagihan_bulanan`         |
| Kolom      | Sama persis dengan kamus data                   | `id_sewa`, `status_tagihan`           |
| Controller | PascalCase                                      | `KatalogKamar`, `Pemesanan`           |
| Model      | PascalCase + `Model`                            | `KamarModel`, `TagihanModel`          |
| Route      | Huruf kecil, tanda hubung                       | `katalog-kamar`, `riwayat-pembayaran` |
| View       | Huruf kecil, satu folder per fitur              | `katalog/index.php`                   |
| Cabang Git | `fitur/...` atau `perbaikan/...`                | `fitur/katalog-kamar`                 |

> Jangan mengganti nama tabel/kolom di tengah jalan tanpa kesepakatan kelompok, karena memicu konflik dan pengerjaan ulang.

---

## 4. Nilai ENUM (sesuai Kamus Data)

| Tabel.kolom                          | Nilai                                                        |
| ------------------------------------ | ------------------------------------------------------------ |
| `pengguna.role`                      | `calon_penghuni`, `penghuni`, `pemilik`                      |
| `kamar.status_kamar`                 | `kosong`, `terisi`                                           |
| `media_kamar.jenis`                  | `foto`, `video`                                              |
| `pemesanan.status_pengajuan`         | `pending`, `approved`, `rejected`                            |
| `sewa.status_sewa`                   | `menunggu_pembayaran`, `aktif`, `selesai`, `dibatalkan`      |
| `pengajuan_perpanjangan.status`      | `menunggu`, `disetujui`, `ditolak`                           |
| `tagihan.jenis_tagihan`              | `dp`, `pelunasan`, `listrik_air`, `denda_piket`, `menginap_pengunjung` |
| `tagihan.status_tagihan`             | `belum_bayar`, `lunas`                                       |
| `pembayaran.metode`                  | `qris`, `tunai`                                              |
| `pembayaran.status_pembayaran`       | `menunggu`, `berhasil`, `kedaluwarsa`, `gagal`, `ditolak`    |
| `piket.status_piket`                 | `terjadwal`, `menunggu_verifikasi`, `selesai`, `tidak_piket` |
| `laporan_kerusakan.status_perbaikan` | `dilaporkan`, `diproses`, `selesai`                          |
| `pengumuman.jenis`                   | `pengumuman`, `peraturan`                                    |

Catatan: `notifikasi.jenis_notifikasi` bertipe VARCHAR(50), bukan ENUM.

---

## 5. Definition of Done

Sebuah kartu dianggap selesai jika:

- [ ] Berjalan di laptop anggota lain (bukan hanya laptop pembuat)
- [ ] Tampilan sesuai desain Figma
- [ ] Nama tabel, kolom, dan nilai ENUM sesuai kamus data
- [ ] Sudah masuk ke `main` lewat Pull Request

---

## 6. Jadwal Cek Bersama

| Hari        | Jam     | Fokus                                                    |
| ----------- | ------- | -------------------------------------------------------- |
| Rabu malam  | <20.00> | Semua laptop menjalankan halaman awal, aturan disepakati |
| Kamis malam | <20.00> | Latihan alur Git: cabang → push → PR → merge             |
| Jumat malam | <20.00> | Review checklist akhir dan perbaikan                     |

---

## 7. Dokumen Pendukung

- Dokumentasi basis data dan ERD: `<tautan atau lokasi file>`
- Desain Figma: `<tautan Figma>`

---

## 8. Memakai Layout dan Hak Akses (Kartu 5)

**View halaman baru** cukup memakai layout utama:

```php
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
    <h1 class="h4">Judul halaman</h1>
    ...isi halaman...
<?= $this->endSection() ?>
```

Kirim `judul` dari controller agar judul tab ikut berubah: `view('kamar/index', ['judul' => 'Kamar'])`.

**Route** dimasukkan ke grup peran yang sesuai di `app/Config/Routes.php`. Filter `peran` otomatis mengarahkan tamu ke login dan menampilkan 403 untuk peran lain.

```php
$routes->group('pemilik', ['filter' => 'peran:pemilik'], static function ($routes) {
    $routes->get('kamar', 'KatalogKamar::index');
});
```

**Menu navbar** diatur di `app/Config/Menu.php`. Tambah atau ubah baris di sana, jangan mengedit layout.

**Pesan flash** cukup `->with('sukses', '...')` atau `->with('gagal', '...')`. Layout menampilkannya otomatis.

**Data session** yang tersedia: `session('id_pengguna')`, `session('nama')`, `session('role')`, `session('logged_in')`.

**Warna dan komponen** sudah mengikuti prototipe Figma dan ada di `public/css/kos15.css`. Jangan menulis warna sendiri, pakai class berikut:

| Bagian di prototipe | Class |
| --- | --- |
| Judul halaman ("Status Pengajuan") | `h1.judul-halaman` |
| Pita biru "Temukan kamar yang nyaman" | `div.pita-judul` (di luar `.container`) |
| Katalog / Kamar 01 / Ajukan Survei | `nav.remah`, pemisah `span.pemisah` |
| Tombol bulat Semua / Tersedia | `a.chip-filter`, yang aktif `.chip-filter.aktif` |
| Kartu putih | `div.card.p-4` |
| Tempat foto kamar | `div.foto-kamar` atau `img.foto-kamar`, kecil `.thumb-kamar` |
| Label Tersedia / Diproses / Ditolak | `span.lencana.lencana-hijau` / `lencana-kuning` / `lencana-merah` |
| Label harga krem | `span.lencana.lencana-krem` |
| Label fasilitas | `span.chip-fasilitas` |
| Banner info biru muda | `div.banner-info` |
| Rincian biaya, ringkasan pesanan | `div.baris-info` berisi dua `span`, bungkus `div.kotak-ringkasan` |
| Rp [NOMINAL DP] | `div.nominal-besar` |
| Alur Survei bernomor | `ol.langkah` |
| Diterima, Diproses, Disetujui | `ul.garis-status` dengan `li.selesai` / `li.sekarang` / `li.gagal` |
| Kotak alasan merah | `div.kotak-merah` |
| Centang hijau | `span.ikon-sukses` |
| Kotak QRIS bergaris putus | `div.kotak-putus` |
| Tombol utama / garis | `btn btn-primary` / `btn btn-outline-primary` |
| Teks kecil abu | `.teks-kecil` |

**Notifikasi** dikirim lewat `model(NotifikasiModel::class)->kirim($idPengguna, 'pemesanan', 'Pesan')`, lalu lonceng di navbar menghitungnya otomatis.

**Akun uji** (kata sandi `password123`): `pemilik`, `budi` (penghuni), `rina` (calon penghuni).

---

## 9. Tagihan Sewa: Skema dan Jatuh Tempo (Kartu 3)

Tagihan sewa dibuat **hanya** lewat satu method, supaya Kartu 5, 6, dan 11 memakai aturan yang sama:

```php
$dibuat = model(TagihanModel::class)->buatTagihanSewa($idSewa, 'dp');    // atau 'lunas'
```

Jangan `insert` tagihan `dp` / `pelunasan` dengan cara sendiri. Method ini aman dipanggil di dalam transaksi pemanggil (jika gagal ia melempar exception, lalu pemanggil yang `transRollback()`), menolak sewa yang sudah punya tagihan sewa, dan hanya menerima sewa berstatus `menunggu_pembayaran`. Harga selalu diambil dari `kamar.harga_sewa_tahunan`, bukan dari input pengguna.

**Alur:** pemilik menyetujui pemesanan (Kartu 8), sewa berstatus `menunggu_pembayaran`, lalu penghuni membuka **Tagihan** dan memilih skema. Skema tidak bisa diubah setelah dipilih.

### Nominal

Semua nominal angka bulat rupiah, dan jumlah semua tagihan sewa selalu sama dengan harga sewa.

| Skema                     | Tagihan yang dibuat                                                        |
| ------------------------- | -------------------------------------------------------------------------- |
| `dp` (DP 50% + pelunasan) | `dp` = harga ÷ 2 (dibulatkan ke bawah), `pelunasan` = sisanya              |
| `lunas` (100%)            | satu tagihan `pelunasan` sebesar harga penuh                               |

Contoh: harga Rp9.600.000 menjadi DP Rp4.800.000 + pelunasan Rp4.800.000. Harga ganjil Rp7.200.001 menjadi DP Rp3.600.000 + pelunasan Rp3.600.001.

### Aturan jatuh tempo

Aturan SRS: **pelunasan wajib dibayar sebelum tanggal mulai sewa.** Maka batas akhir semua tagihan sewa adalah **H-1 tanggal mulai sewa**.

| Tagihan                                   | Jatuh tempo                                                              |
| ----------------------------------------- | ------------------------------------------------------------------------ |
| DP (skema `dp`)                           | 3 hari setelah skema dipilih, tetapi tidak lewat H-1 tanggal mulai sewa  |
| Pelunasan 100% (skema `lunas`)            | sama dengan DP: 3 hari setelah skema dipilih, paling lambat H-1          |
| Pelunasan 50% (skema `dp`)                | H-1 tanggal mulai sewa                                                   |

Aturan tambahan:

- Jatuh tempo tidak pernah sebelum hari ini. Jika tanggal mulai sewa hari ini atau besok, H-1 sudah lewat atau jatuh hari ini, sehingga tagihan jatuh tempo **hari ini** (harus dibayar langsung).
- Tanggal dihitung dengan zona waktu Asia/Jakarta (server memakai UTC).
- Tagihan **lewat jatuh tempo** = belum lunas dan tanggal jatuh tempo **sebelum** hari ini. Di hari jatuh tempo sendiri status masih "Belum dibayar".

Contoh: skema `dp` dipilih 28 Nov 2026, sewa mulai 1 Feb 2027 menjadi DP jatuh tempo 1 Des 2026 dan pelunasan jatuh tempo 31 Jan 2027. Jika sewa mulai 1 Des 2026, DP dan pelunasan sama-sama jatuh tempo 30 Nov 2026.

Pengaturan angka ada di konstanta `TagihanModel::PERSEN_DP` dan `TagihanModel::HARI_JATUH_TEMPO_DP`.

## 10. Pembayaran QRIS (Midtrans Sandbox)

Penghuni membayar tagihan dengan **QRIS dinamis**: nominal diambil otomatis dari tabel `tagihan`, setiap klik "Bayar dengan QRIS" membuat `order_id` unik yang berlaku 15 menit, dan status tagihan berubah sendiri lewat callback Midtrans. Sandbox tidak memakai uang sungguhan.

**Alur:** `penghuni/tagihan/{id}/bayar` → tombol *Bayar dengan QRIS* → `penghuni/pembayaran/{id}` (kode QR + hitung mundur, status dicek tiap 3 detik) → Midtrans memanggil `POST /pembayaran/callback` → pembayaran `berhasil`, tagihan `lunas`, notifikasi ke penghuni dan pemilik.

> Untuk kartu halaman tagihan: tombol "Bayar" cukup diarahkan ke `site_url('penghuni/tagihan/' . $id_tagihan . '/bayar')`.

### Langkah uji lokal

1. **Daftar akun sandbox** di https://dashboard.sandbox.midtrans.com (gratis). Buka *Settings → Access Keys*, salin **Server Key** (diawali `SB-Mid-server-`).
2. **Isi `.env`** (bukan `.env.example`, dan jangan di-commit):
   ```
   MIDTRANS_SERVER_KEY=SB-Mid-server-xxxxxxxxxxxx
   MIDTRANS_IS_PRODUCTION=false
   ```
3. **Siapkan database.** Import `kos15.sql` di phpMyAdmin. Tabel `pembayaran` dan `notifikasi` sudah ada di file itu, jadi tidak perlu migrate. Pastikan `database.default.database` di `.env` sama dengan nama database hasil import (`db_kos`).
4. **Jalankan server:** `php spark serve` (http://localhost:8080).
5. **Buka ngrok** supaya Midtrans bisa memanggil laptopmu:
   ```bash
   ngrok http 8080
   ```
   Salin alamat `https://xxxx.ngrok-free.app` yang muncul.
6. **Atur Payment Notification URL** di dashboard sandbox: *Settings → Payment → Notification URL* →
   `https://xxxx.ngrok-free.app/index.php/pembayaran/callback` (tanpa `index.php/` jika `indexPage` sudah dikosongkan).
7. **Coba bayar.** Login `sari` / `password123`, buka http://localhost:8080/penghuni/tagihan/5/bayar, klik *Bayar dengan QRIS*.
8. **Bayar di simulator.** Buka https://simulator.sandbox.midtrans.com/v2/qris/index, tempel URL gambar QR (klik kanan gambar QR → *Copy image address*), lalu bayar. Dalam beberapa detik halaman berubah sendiri menjadi **Pembayaran berhasil**.

### Uji tanpa ngrok (callback manual)

Hitung signature lalu kirim callback palsu dari terminal:

```bash
php -r "echo hash('sha512', 'ORDER_ID' . '200' . '127500.00' . 'SERVER_KEY');"
curl -X POST http://localhost:8080/index.php/pembayaran/callback -H "Content-Type: application/json" \
  -d '{"order_id":"ORDER_ID","status_code":"200","gross_amount":"127500.00","transaction_status":"settlement","signature_key":"HASIL_HASH"}'
```

Ganti `ORDER_ID` dengan isi kolom `pembayaran.order_id`. Signature salah dibalas **403** tanpa mengubah data. Callback yang sama dikirim dua kali tetap dibalas **200**, dan yang kedua tidak mengubah apa pun.

### Log

Callback yang gagal (signature salah, order tidak dikenal, nominal berbeda, JSON rusak, error database) dicatat di `writable/logs/log-YYYY-MM-DD.log` dengan awalan `Callback QRIS gagal`.

### Pemetaan status Midtrans

| `transaction_status` | `pembayaran.status_pembayaran` | `tagihan.status_tagihan` |
| --- | --- | --- |
| `settlement` / `capture` | `berhasil` | `lunas` |
| `expire` | `kedaluwarsa` | tetap `belum_bayar` |
| `cancel` / `deny` / `failure` | `gagal` | tetap `belum_bayar` |
| `pending` | tidak berubah | tidak berubah |

### Memakai Duitku (pilihan kedua)

Kode mendukung **Midtrans** dan **Duitku**. Gateway dipilih di `.env`, kodenya tidak perlu diubah:

```
PAYMENT_GATEWAY=duitku
DUITKU_MERCHANT_CODE=DSxxxxx        # dashboard Duitku > Project
DUITKU_API_KEY=xxxxxxxx             # JANGAN di-commit / dibagikan
DUITKU_IS_PRODUCTION=false          # true = uang sungguhan (passport.duitku.com)
DUITKU_METODE_QRIS=SP               # SP ShopeePay QRIS, NQ Nobu QRIS, LQ LinkAja QRIS (yang aktif di project)
DUITKU_CALLBACK_URL=                # opsional, mis. https://xxxx.ngrok-free.dev/index.php/pembayaran/callback-duitku
```

- Duitku mengirim isi QR (`qrString`), lalu halaman menggambarnya dengan `public/js/qrcode-generator.js` (MIT).
- Callback Duitku masuk ke `POST /pembayaran/callback-duitku`, dengan signature `MD5(merchantCode + amount + merchantOrderId + apiKey)`.
- `order_id` Duitku diawali `KOSD-` dan Midtrans `KOS-`, sehingga pembayaran lama tetap dicek ke gateway yang benar walau `.env` diganti.

### Tanpa ngrok: cek status otomatis

Selama halaman QR terbuka dan status masih *menunggu*, aplikasi **menanyakan status langsung ke gateway** paling sering sekali per 10 detik (Midtrans `GET /v2/{order_id}/status`, Duitku `transactionStatus`). Jadi tagihan tetap berubah menjadi lunas walau callback tidak sampai ke laptop. Callback tetap jalur utama, dan keduanya diproses oleh `PembayaranService` yang sama (idempoten, notifikasi hanya sekali).

---

## 11. Notifikasi dan Pengingat Harian

### Perintah pengingat

```bash
php spark pengingat:kirim                         # tanggal hari ini (WIB)
php spark pengingat:kirim --tanggal=2026-11-22    # uji seolah-olah hari ini 22 Nov 2026
php spark pengingat:kirim --tanggal=2026-11-22 --coba   # hanya tampilkan, tidak menyimpan
```

- Mengambil tagihan **belum_bayar** yang jatuh tempo tepat H-7, H-3, dan H-1. Angka harinya dibaca dari `info_kos.jadwal_pengingat` (bawaan `7,3,1`).
- **Tidak ganda:** tagihan yang sudah punya pengingat `(H-n)` yang sama, atau sudah diberi pengingat pada tanggal itu, dilewati. Menjalankan perintah dua kali sehari aman.
- **Berhenti saat lunas:** tagihan `lunas` tidak pernah diambil lagi.
- Hasil setiap putaran tercatat di `writable/logs` (`pengingat:kirim ...: n dikirim, n dilewati`).

Contoh uji dengan data `kos15.sql` (tagihan 5 milik `sari`, jatuh tempo 25 Nov 2026):

| Tanggal uji | Hasil |
| --- | --- |
| `--tanggal=2026-11-18` | H-7 dilewati, karena data contoh sudah punya pengingat H-7 |
| `--tanggal=2026-11-22` | H-3 dikirim ke Sari. Dijalankan ulang, dilewati |
| `--tanggal=2026-11-24` | H-1 dikirim, atau tidak ada jika tagihan sudah dilunasi |

### Notifikasi untuk pemilik (dipakai kartu lain)

```php
$notif = new \App\Libraries\NotifikasiService();
$notif->pemesananBaru('PSN-00003', 'Rina Wulandari', 'K-05', '2026-12-01');
$notif->pembayaranTunai($idTagihan, 'Budi Santoso', 'K-01', 20000);
$notif->perpanjanganBaru('Budi Santoso', 'K-01', '2027-08-01');
$notif->laporanKerusakan($idLaporan, 'Sari Lestari', 'K-02', 'Lampu kamar mati');
```

Setiap fungsi mengirim ke semua akun pemilik. Klik notifikasi (lonceng atau halaman `/notifikasi`) akan menandainya **sudah dibaca** lalu membuka halaman terkait. Jika halaman itu belum dibuat, pengguna kembali ke `/notifikasi`.

### Menjadwalkan di Task Scheduler Windows

1. Tekan **Win**, ketik **Task Scheduler**, buka.
2. Di panel kanan klik **Create Basic Task…**
   - Name: `Kos15 Pengingat Tagihan`, lalu **Next**.
3. Trigger: **Daily**, lalu **Next**. Start: jam `07:00:00`, Recur every `1` days, lalu **Next**.
4. Action: **Start a program**, lalu **Next**:
   - Program/script: `C:\xampp\php\php.exe`
   - Add arguments: `spark pengingat:kirim`
   - Start in: `C:\xampp\htdocs\kos15`  ← wajib, folder yang berisi file `spark`
5. **Finish**. Pastikan MySQL XAMPP menyala pada jam itu.
6. Uji sekarang: klik kanan task → **Run**, lalu cek `writable/logs` atau lonceng notifikasi.

Atau lewat Command Prompt (Run as administrator), satu baris:

```bat
schtasks /Create /SC DAILY /ST 07:00 /TN "Kos15 Pengingat Tagihan" /TR "cmd /c cd /d C:\xampp\htdocs\kos15 && C:\xampp\php\php.exe spark pengingat:kirim"
```

Hapus jadwal: `schtasks /Delete /TN "Kos15 Pengingat Tagihan" /F`

## 12. Halaman Pembayaran dan Notifikasi

Halaman Tagihan dan pilihan skema DP/lunas ada di Kartu 3 (bagian 9). Kartu ini menambah halaman pembayarannya.
URL di bawah sama dengan menu di `partials/sidebar_penghuni.php`, jadi klik menu langsung membuka halamannya.
Semua halaman penghuni memakai `layouts/penghuni`.

| URL | Halaman | File |
| --- | --- | --- |
| `penghuni/bayar` | Menu **Bayar Tagihan**: buka tagihan belum bayar terdekat | `Penghuni/Pembayaran::mulai` |
| `penghuni/tagihan/{id}/bayar` | Bayar Tagihan (pilih tagihan, QRIS, ringkasan) | `Penghuni/Pembayaran::pilih`, `Views/pembayaran/pilih.php` |
| `penghuni/pembayaran/{id}` | Kode QRIS + status otomatis | `Views/pembayaran/qris.php` |
| `penghuni/riwayat` | Riwayat Pembayaran, cari + filter status | `Controllers/Penghuni/Riwayat.php`, `Views/penghuni/riwayat.php` |
| `penghuni/riwayat/unduh` | Unduh Rekap (CSV, bisa dibuka di Excel) | `Penghuni/Riwayat::unduhRekap` |
| `penghuni/pembayaran/{id}/kwitansi` | Kwitansi siap cetak (hanya pembayaran berhasil) | `Views/penghuni/kwitansi.php` |
| `notifikasi` | Notifikasi semua peran, chip Semua / Belum dibaca | `Views/notifikasi/index.php`, lonceng `Cells/lonceng.php` |

Setelah tagihan DP dan pelunasan lunas, status sewa berubah dari `menunggu_pembayaran` menjadi `aktif` (`PembayaranService`).
Gaya halaman ini ada di bagian akhir `public/css/kos15.css` (kelas baru saja, gaya lama tidak diubah).
