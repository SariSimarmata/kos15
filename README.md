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
| `tagihan.jenis_tagihan`              | `dp`, `pelunasan`, `tagihan_bulanan`, `denda_piket`          |
| `tagihan.status_tagihan`             | `belum_bayar`, `lunas`, `dibatalkan`                         |
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
