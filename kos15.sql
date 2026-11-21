-- =====================================================================
--  kos15.sql
--  Database: Sistem Informasi Manajemen Kos dan Pembayaran Otomatis
--  DBMS    : MySQL / MariaDB (XAMPP)
--  Acuan   : ERD (Crow's Foot) versi 6 - 17 tabel
--
--  Cara pakai di XAMPP:
--    1. Jalankan Apache dan MySQL di XAMPP Control Panel
--    2. Buka http://localhost/phpmyadmin
--    3. Tab Import -> pilih kos15.sql -> Go
--       (database db_kos dibuat otomatis oleh file ini)
--
--  Semua akun contoh memakai password: password123 (hash bcrypt)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS db_kos
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_kos;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS notifikasi;
DROP TABLE IF EXISTS tagihan_bulanan;
DROP TABLE IF EXISTS kunjungan_menginap;
DROP TABLE IF EXISTS piket;
DROP TABLE IF EXISTS laporan_kerusakan;
DROP TABLE IF EXISTS pengajuan_perpanjangan;
DROP TABLE IF EXISTS pembayaran;
DROP TABLE IF EXISTS tagihan;
DROP TABLE IF EXISTS sewa;
DROP TABLE IF EXISTS pemesanan;
DROP TABLE IF EXISTS survei_kamar;
DROP TABLE IF EXISTS media_kamar;
DROP TABLE IF EXISTS pengunjung;
DROP TABLE IF EXISTS pengumuman;
DROP TABLE IF EXISTS info_kos;
DROP TABLE IF EXISTS kamar;
DROP TABLE IF EXISTS pengguna;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
--  STRUKTUR TABEL (urut mengikuti foreign key)
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. pengguna : akun pemilik, penghuni, dan calon penghuni
-- ---------------------------------------------------------------------
CREATE TABLE pengguna (
  id_pengguna INT          NOT NULL AUTO_INCREMENT,
  nama        VARCHAR(100) NOT NULL,
  username    VARCHAR(50)  NOT NULL,
  `password`  VARCHAR(255) NOT NULL COMMENT 'Hash bcrypt (password_hash)',
  no_hp       VARCHAR(15)  NOT NULL,
  `role`      ENUM('pemilik','penghuni','calon_penghuni') NOT NULL DEFAULT 'calon_penghuni',
  PRIMARY KEY (id_pengguna),
  UNIQUE KEY uq_pengguna_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. kamar : katalog kamar
-- ---------------------------------------------------------------------
CREATE TABLE kamar (
  id_kamar           INT           NOT NULL AUTO_INCREMENT,
  nomor_kamar        VARCHAR(10)   NOT NULL,
  fasilitas          TEXT          NOT NULL,
  harga_sewa_tahunan DECIMAL(12,2) NOT NULL,
  status_kamar       ENUM('kosong','terisi') NOT NULL DEFAULT 'kosong',
  PRIMARY KEY (id_kamar),
  CONSTRAINT chk_kamar_harga CHECK (harga_sewa_tahunan > 0),
  UNIQUE KEY uq_kamar_nomor (nomor_kamar)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. media_kamar : foto/video kamar  (kamar 1 : N media_kamar)
-- ---------------------------------------------------------------------
CREATE TABLE media_kamar (
  id_media INT          NOT NULL AUTO_INCREMENT,
  id_kamar INT          NOT NULL,
  jenis    ENUM('foto','video') NOT NULL,
  `file`   VARCHAR(255) NOT NULL,
  PRIMARY KEY (id_media),
  KEY idx_media_kamar (id_kamar),
  CONSTRAINT fk_media_kamar FOREIGN KEY (id_kamar)
    REFERENCES kamar (id_kamar) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. info_kos : informasi umum dan tarif (hanya 1 baris)
-- ---------------------------------------------------------------------
CREATE TABLE info_kos (
  id_info                   INT           NOT NULL AUTO_INCREMENT,
  lokasi                    VARCHAR(255)  NOT NULL,
  jadwal_pengingat          VARCHAR(50)   NOT NULL COMMENT 'Hari sebelum jatuh tempo, mis. 7,3,1',
  no_whatsapp               VARCHAR(15)   NOT NULL,
  tarif_listrik_per_kwh     DECIMAL(12,2) NOT NULL,
  tarif_air_per_m3          DECIMAL(12,2) NOT NULL,
  denda_piket_per_hari      DECIMAL(12,2) NOT NULL,
  tarif_menginap_per_hari   DECIMAL(12,2) NOT NULL,
  batas_menginap_per_minggu INT           NOT NULL DEFAULT 3,
  PRIMARY KEY (id_info),
  CONSTRAINT chk_info_batas CHECK (batas_menginap_per_minggu > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. survei_kamar : pengajuan survei tanpa login  (kamar 1 : N survei)
-- ---------------------------------------------------------------------
CREATE TABLE survei_kamar (
  id_survei         INT          NOT NULL AUTO_INCREMENT,
  id_kamar          INT          NOT NULL,
  nama_calon        VARCHAR(100) NOT NULL,
  no_hp             VARCHAR(15)  NOT NULL,
  tanggal_pengajuan DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  tanggal_survei    DATE         NOT NULL,
  waktu_survei      TIME         NOT NULL,
  PRIMARY KEY (id_survei),
  KEY idx_survei_kamar (id_kamar),
  CONSTRAINT fk_survei_kamar FOREIGN KEY (id_kamar)
    REFERENCES kamar (id_kamar) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. pemesanan : pengajuan pesan kamar
--    (pengguna 1 : N pemesanan, kamar 1 : N pemesanan)
-- ---------------------------------------------------------------------
CREATE TABLE pemesanan (
  id_pemesanan       INT      NOT NULL AUTO_INCREMENT,
  id_pengguna        INT      NOT NULL,
  id_kamar           INT      NOT NULL,
  tanggal_mulai_sewa DATE     NOT NULL,
  tanggal_pengajuan  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status_pengajuan   ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (id_pemesanan),
  KEY idx_pemesanan_pengguna (id_pengguna),
  KEY idx_pemesanan_kamar (id_kamar),
  CONSTRAINT fk_pemesanan_pengguna FOREIGN KEY (id_pengguna)
    REFERENCES pengguna (id_pengguna) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_pemesanan_kamar FOREIGN KEY (id_kamar)
    REFERENCES kamar (id_kamar) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. sewa : masa sewa satu tahun  (pemesanan 1 : N sewa)
--    Perpanjangan disetujui = baris sewa baru dengan id_pemesanan yang sama
-- ---------------------------------------------------------------------
CREATE TABLE sewa (
  id_sewa          INT  NOT NULL AUTO_INCREMENT,
  id_pemesanan     INT  NOT NULL,
  tanggal_mulai    DATE NOT NULL,
  tanggal_berakhir DATE NOT NULL,
  status_sewa      ENUM('menunggu_pembayaran','aktif','selesai','batal') NOT NULL DEFAULT 'menunggu_pembayaran',
  PRIMARY KEY (id_sewa),
  CONSTRAINT chk_sewa_tanggal CHECK (tanggal_berakhir > tanggal_mulai),
  KEY idx_sewa_pemesanan (id_pemesanan),
  CONSTRAINT fk_sewa_pemesanan FOREIGN KEY (id_pemesanan)
    REFERENCES pemesanan (id_pemesanan) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. tagihan : semua jenis tagihan  (sewa 1 : N tagihan)
-- ---------------------------------------------------------------------
CREATE TABLE tagihan (
  id_tagihan          INT           NOT NULL AUTO_INCREMENT,
  id_sewa             INT           NOT NULL,
  jenis_tagihan       ENUM('dp','pelunasan','listrik_air','denda_piket','menginap_pengunjung') NOT NULL,
  jumlah_tagihan      DECIMAL(12,2) NOT NULL,
  tanggal_jatuh_tempo DATE          NOT NULL,
  status_tagihan      ENUM('belum_bayar','lunas') NOT NULL DEFAULT 'belum_bayar',
  PRIMARY KEY (id_tagihan),
  CONSTRAINT chk_tagihan_jumlah CHECK (jumlah_tagihan > 0),
  KEY idx_tagihan_sewa (id_sewa),
  KEY idx_tagihan_jatuh_tempo (tanggal_jatuh_tempo, status_tagihan),
  CONSTRAINT fk_tagihan_sewa FOREIGN KEY (id_sewa)
    REFERENCES sewa (id_sewa) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. pembayaran : pembayaran QRIS (gateway) atau tunai
--    (tagihan 1 : N pembayaran, pengguna 0..1 : N pembayaran sebagai konfirmator)
--    order_id, id_transaksi_gateway, qris_url, waktu_kedaluwarsa hanya untuk QRIS
--    id_konfirmator hanya untuk tunai (pemilik yang mengonfirmasi)
-- ---------------------------------------------------------------------
CREATE TABLE pembayaran (
  id_pembayaran        INT           NOT NULL AUTO_INCREMENT,
  id_tagihan           INT           NOT NULL,
  id_konfirmator       INT           NULL,
  metode               ENUM('qris','tunai') NOT NULL,
  order_id             VARCHAR(64)   NULL,
  id_transaksi_gateway VARCHAR(100)  NULL,
  qris_url             VARCHAR(255)  NULL,
  waktu_kedaluwarsa    DATETIME      NULL,
  tanggal_bayar        DATETIME      NULL,
  jumlah_bayar         DECIMAL(12,2) NOT NULL,
  status_pembayaran    ENUM('menunggu','berhasil','gagal','kedaluwarsa') NOT NULL DEFAULT 'menunggu',
  PRIMARY KEY (id_pembayaran),
  CONSTRAINT chk_pembayaran_jumlah CHECK (jumlah_bayar > 0),
  UNIQUE KEY uq_pembayaran_order (order_id),
  KEY idx_pembayaran_tagihan (id_tagihan),
  KEY idx_pembayaran_konfirmator (id_konfirmator),
  CONSTRAINT fk_pembayaran_tagihan FOREIGN KEY (id_tagihan)
    REFERENCES tagihan (id_tagihan) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_pembayaran_konfirmator FOREIGN KEY (id_konfirmator)
    REFERENCES pengguna (id_pengguna) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10. pengumuman : pengumuman & peraturan (berdiri sendiri)
-- ---------------------------------------------------------------------
CREATE TABLE pengumuman (
  id_pengumuman  INT      NOT NULL AUTO_INCREMENT,
  jenis          ENUM('pengumuman','peraturan') NOT NULL,
  isi            TEXT     NOT NULL,
  tanggal_terbit DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_pengumuman)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11. pengunjung : data tamu yang menginap
-- ---------------------------------------------------------------------
CREATE TABLE pengunjung (
  id_pengunjung   INT          NOT NULL AUTO_INCREMENT,
  nama_pengunjung VARCHAR(100) NOT NULL,
  no_identitas    VARCHAR(30)  NOT NULL,
  no_hp           VARCHAR(15)  NOT NULL,
  PRIMARY KEY (id_pengunjung)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 12. pengajuan_perpanjangan  (sewa 1 : N pengajuan_perpanjangan)
-- ---------------------------------------------------------------------
CREATE TABLE pengajuan_perpanjangan (
  id_perpanjangan      INT  NOT NULL AUTO_INCREMENT,
  id_sewa              INT  NOT NULL,
  tanggal_perpanjangan DATE NOT NULL,
  `status`             ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (id_perpanjangan),
  KEY idx_perpanjangan_sewa (id_sewa),
  CONSTRAINT fk_perpanjangan_sewa FOREIGN KEY (id_sewa)
    REFERENCES sewa (id_sewa) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 13. laporan_kerusakan  (pengguna 1 : N laporan_kerusakan)
-- ---------------------------------------------------------------------
CREATE TABLE laporan_kerusakan (
  id_laporan        INT          NOT NULL AUTO_INCREMENT,
  id_pengguna       INT          NOT NULL,
  deskripsi         TEXT         NOT NULL,
  foto              VARCHAR(255) NOT NULL,
  tanggal_lapor     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status_perbaikan  ENUM('dilaporkan','diproses','selesai') NOT NULL DEFAULT 'dilaporkan',
  catatan_perbaikan TEXT         NULL,
  foto_perbaikan    VARCHAR(255) NULL,
  tanggal_selesai   DATETIME     NULL,
  PRIMARY KEY (id_laporan),
  KEY idx_laporan_pengguna (id_pengguna),
  CONSTRAINT fk_laporan_pengguna FOREIGN KEY (id_pengguna)
    REFERENCES pengguna (id_pengguna) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 14. piket  (sewa 1 : N piket, piket 0..1 : 0..1 tagihan denda)
--     id_tagihan terisi hanya jika penghuni didenda karena tidak piket
-- ---------------------------------------------------------------------
CREATE TABLE piket (
  id_piket      INT          NOT NULL AUTO_INCREMENT,
  id_sewa       INT          NOT NULL,
  id_tagihan    INT          NULL,
  tanggal_piket DATE         NOT NULL,
  foto_piket    VARCHAR(255) NULL,
  waktu_piket   DATETIME     NULL COMMENT 'Waktu penghuni mengunggah bukti',
  status_piket  ENUM('terjadwal','menunggu_konfirmasi','selesai','tidak_piket') NOT NULL DEFAULT 'terjadwal',
  catatan_admin TEXT         NULL,
  PRIMARY KEY (id_piket),
  UNIQUE KEY uq_piket_tagihan (id_tagihan),
  KEY idx_piket_sewa (id_sewa),
  CONSTRAINT fk_piket_sewa FOREIGN KEY (id_sewa)
    REFERENCES sewa (id_sewa) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_piket_tagihan FOREIGN KEY (id_tagihan)
    REFERENCES tagihan (id_tagihan) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 15. kunjungan_menginap
--     (pengunjung 1 : N kunjungan, sewa 1 : N kunjungan)
-- ---------------------------------------------------------------------
CREATE TABLE kunjungan_menginap (
  id_kunjungan   INT  NOT NULL AUTO_INCREMENT,
  id_pengunjung  INT  NOT NULL,
  id_sewa        INT  NOT NULL,
  tanggal_masuk  DATE NOT NULL,
  tanggal_keluar DATE NOT NULL,
  PRIMARY KEY (id_kunjungan),
  CONSTRAINT chk_kunjungan_tanggal CHECK (tanggal_keluar > tanggal_masuk),
  KEY idx_kunjungan_pengunjung (id_pengunjung),
  KEY idx_kunjungan_sewa (id_sewa),
  CONSTRAINT fk_kunjungan_pengunjung FOREIGN KEY (id_pengunjung)
    REFERENCES pengunjung (id_pengunjung) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_kunjungan_sewa FOREIGN KEY (id_sewa)
    REFERENCES sewa (id_sewa) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 16. tagihan_bulanan : rincian listrik, air, sampah, pengunjung per bulan
--     (sewa 1 : N tagihan_bulanan, tagihan_bulanan 0..1 : 0..1 tagihan,
--      kunjungan_menginap 1 : N tagihan_bulanan lewat id_kunjungan)
-- ---------------------------------------------------------------------
CREATE TABLE tagihan_bulanan (
  id_tagih_bulanan   INT           NOT NULL AUTO_INCREMENT,
  id_sewa            INT           NOT NULL,
  id_tagihan         INT           NULL,
  id_kunjungan       INT           NULL,
  periode            CHAR(7)       NOT NULL COMMENT 'Format YYYY-MM',
  tagihan_listrik    DECIMAL(12,2) NOT NULL DEFAULT 0,
  tagihan_air        DECIMAL(12,2) NOT NULL DEFAULT 0,
  tagihan_sampah     DECIMAL(12,2) NOT NULL DEFAULT 0,
  tagihan_pengunjung DECIMAL(12,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (id_tagih_bulanan),
  CONSTRAINT chk_tagih_bulanan_nominal CHECK (tagihan_listrik >= 0 AND tagihan_air >= 0 AND tagihan_sampah >= 0 AND tagihan_pengunjung >= 0),
  UNIQUE KEY uq_tagih_bulanan_tagihan (id_tagihan),
  UNIQUE KEY uq_tagih_bulanan_periode (id_sewa, periode),
  KEY idx_tagih_bulanan_kunjungan (id_kunjungan),
  CONSTRAINT fk_tagih_bulanan_sewa FOREIGN KEY (id_sewa)
    REFERENCES sewa (id_sewa) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_tagih_bulanan_tagihan FOREIGN KEY (id_tagihan)
    REFERENCES tagihan (id_tagihan) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_tagih_bulanan_kunjungan FOREIGN KEY (id_kunjungan)
    REFERENCES kunjungan_menginap (id_kunjungan) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 17. notifikasi  (pengguna 1 : N notifikasi,
--     tagihan 0..1 : N notifikasi, laporan_kerusakan 0..1 : N notifikasi)
--     id_tagihan terisi untuk notifikasi tagihan, denda, pembayaran
--     id_laporan terisi untuk notifikasi aduan
--     keduanya kosong untuk pemesanan, piket, perpanjangan, meteran
-- ---------------------------------------------------------------------
CREATE TABLE notifikasi (
  id_notifikasi    INT      NOT NULL AUTO_INCREMENT,
  id_pengguna      INT      NOT NULL,
  id_tagihan       INT      NULL,
  id_laporan       INT      NULL,
  jenis_notifikasi ENUM('tagihan','denda','pembayaran','aduan','pemesanan','piket','perpanjangan','meteran') NOT NULL,
  pesan            TEXT     NOT NULL,
  waktu_kirim      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status_baca      TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 = belum dibaca, 1 = sudah',
  PRIMARY KEY (id_notifikasi),
  KEY idx_notifikasi_pengguna (id_pengguna, status_baca),
  KEY idx_notifikasi_tagihan (id_tagihan),
  KEY idx_notifikasi_laporan (id_laporan),
  CONSTRAINT fk_notifikasi_pengguna FOREIGN KEY (id_pengguna)
    REFERENCES pengguna (id_pengguna) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_notifikasi_tagihan FOREIGN KEY (id_tagihan)
    REFERENCES tagihan (id_tagihan) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_notifikasi_laporan FOREIGN KEY (id_laporan)
    REFERENCES laporan_kerusakan (id_laporan) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  DATA AWAL
-- =====================================================================

-- Password semua akun: password123
INSERT INTO pengguna (id_pengguna, nama, username, `password`, no_hp, `role`) VALUES
(1, 'Pak Hasan',     'pemilik',  '$2y$10$hayXO/Lz7wVZTo6UY.akEujuXuDtdxh8wujL0t6brEocWpTv.z6ZO', '081200000001', 'pemilik'),
(3, 'Budi Santoso',  'budi',     '$2y$10$hayXO/Lz7wVZTo6UY.akEujuXuDtdxh8wujL0t6brEocWpTv.z6ZO', '081200000003', 'penghuni'),
(4, 'Sari Lestari',  'sari',     '$2y$10$hayXO/Lz7wVZTo6UY.akEujuXuDtdxh8wujL0t6brEocWpTv.z6ZO', '081200000004', 'penghuni'),
(5, 'Rina Wulandari','rina',     '$2y$10$hayXO/Lz7wVZTo6UY.akEujuXuDtdxh8wujL0t6brEocWpTv.z6ZO', '081200000005', 'calon_penghuni');

INSERT INTO kamar (id_kamar, nomor_kamar, fasilitas, harga_sewa_tahunan, status_kamar) VALUES
(1, 'K-01', 'Kasur, lemari, meja belajar, kamar mandi dalam', 7200000.00, 'terisi'),
(2, 'K-02', 'Kasur, lemari, meja belajar, kamar mandi dalam', 7200000.00, 'terisi'),
(3, 'K-03', 'Kasur, lemari, kamar mandi luar',                6000000.00, 'kosong'),
(4, 'K-04', 'Kasur, lemari, kamar mandi luar',                6000000.00, 'kosong'),
(5, 'K-05', 'Kasur, lemari, meja belajar, AC, kamar mandi dalam', 9600000.00, 'kosong');

INSERT INTO media_kamar (id_kamar, jenis, `file`) VALUES
(1, 'foto',  'kamar/k01_1.jpg'),
(1, 'foto',  'kamar/k01_2.jpg'),
(2, 'foto',  'kamar/k02_1.jpg'),
(3, 'foto',  'kamar/k03_1.jpg'),
(3, 'video', 'kamar/k03_tur.mp4'),
(4, 'foto',  'kamar/k04_1.jpg'),
(5, 'foto',  'kamar/k05_1.jpg'),
(5, 'video', 'kamar/k05_tur.mp4');

INSERT INTO info_kos (id_info, lokasi, jadwal_pengingat, no_whatsapp, tarif_listrik_per_kwh, tarif_air_per_m3, denda_piket_per_hari, tarif_menginap_per_hari, batas_menginap_per_minggu) VALUES
(1, 'Jl. Almamater No. 10, Padang Bulan, Medan', '7,3,1', '081200000001', 1500.00, 5000.00, 20000.00, 5000.00, 3);

INSERT INTO survei_kamar (id_kamar, nama_calon, no_hp, tanggal_pengajuan, tanggal_survei, waktu_survei) VALUES
(3, 'Dimas Pratama', '081300000001', '2026-11-15 10:12:00', '2026-11-22', '10:00:00'),
(5, 'Rina Wulandari', '081200000005', '2026-11-16 19:40:00', '2026-11-23', '15:30:00');

-- Budi & Sari sudah disetujui, Rina masih menunggu
INSERT INTO pemesanan (id_pemesanan, id_pengguna, id_kamar, tanggal_mulai_sewa, tanggal_pengajuan, status_pengajuan) VALUES
(1, 3, 1, '2026-08-01', '2026-07-20 09:00:00', 'approved'),
(2, 4, 2, '2026-09-01', '2026-08-18 14:30:00', 'approved'),
(3, 5, 5, '2026-12-01', '2026-11-17 20:05:00', 'pending');

INSERT INTO sewa (id_sewa, id_pemesanan, tanggal_mulai, tanggal_berakhir, status_sewa) VALUES
(1, 1, '2026-08-01', '2027-07-31', 'aktif'),
(2, 2, '2026-09-01', '2027-08-31', 'aktif');

-- Budi: DP 50% + pelunasan, lunas semua.
-- Sari: DP 50% lunas, pelunasan lunas, listrik_air pemakaian Oktober (ditagih November) belum dibayar.
INSERT INTO tagihan (id_tagihan, id_sewa, jenis_tagihan, jumlah_tagihan, tanggal_jatuh_tempo, status_tagihan) VALUES
(1, 1, 'dp',          3600000.00, '2026-07-23', 'lunas'),
(2, 1, 'pelunasan',   3600000.00, '2026-07-31', 'lunas'),
(3, 2, 'dp',          3600000.00, '2026-08-21', 'lunas'),
(4, 2, 'pelunasan',   3600000.00, '2026-08-31', 'lunas'),
(5, 2, 'listrik_air',  127500.00, '2026-11-25', 'belum_bayar');

INSERT INTO pembayaran (id_tagihan, id_konfirmator, metode, order_id, id_transaksi_gateway, qris_url, waktu_kedaluwarsa, tanggal_bayar, jumlah_bayar, status_pembayaran) VALUES
(1, NULL, 'qris',  'KOS-20260721-0001', 'TRX-9A1B2C3D', 'https://gateway.example/qris/KOS-20260721-0001', '2026-07-21 10:15:00', '2026-07-21 10:02:11', 3600000.00, 'berhasil'),
(2, 1,    'tunai', NULL, NULL, NULL, NULL, '2026-07-30 16:00:00', 3600000.00, 'berhasil'),
(3, NULL, 'qris',  'KOS-20260819-0002', 'TRX-4E5F6A7B', 'https://gateway.example/qris/KOS-20260819-0002', '2026-08-19 08:30:00', '2026-08-19 08:21:47', 3600000.00, 'berhasil'),
(4, NULL, 'qris',  'KOS-20260828-0003', 'TRX-8C9D0E1F', 'https://gateway.example/qris/KOS-20260828-0003', '2026-08-28 13:15:00', '2026-08-28 13:04:05', 3600000.00, 'berhasil');

INSERT INTO pengumuman (jenis, isi, tanggal_terbit) VALUES
('peraturan',  'Tamu menginap maksimal 3 malam per minggu (Senin-Minggu) dan wajib didaftarkan ke pengelola.', '2026-08-01 08:00:00'),
('peraturan',  'Piket kebersihan sesuai jadwal. Tidak piket tanpa bukti foto dikenai denda sesuai tarif.',      '2026-08-01 08:05:00'),
('pengumuman', 'Perbaikan pompa air hari Sabtu, 28 November 2026, pukul 09.00-12.00. Air mati sementara.',       '2026-11-18 17:30:00');

INSERT INTO pengunjung (id_pengunjung, nama_pengunjung, no_identitas, no_hp) VALUES
(1, 'Lestari Ayu', '1271000000000001', '081400000001');

-- Budi (sewa 1) mengajukan perpanjangan, masih menunggu
INSERT INTO pengajuan_perpanjangan (id_sewa, tanggal_perpanjangan, `status`) VALUES
(1, '2027-08-01', 'pending');

INSERT INTO laporan_kerusakan (id_laporan, id_pengguna, deskripsi, foto, tanggal_lapor, status_perbaikan, catatan_perbaikan, foto_perbaikan, tanggal_selesai) VALUES
(1, 3, 'Keran kamar mandi bocor terus.', 'laporan/lap_001.jpg', '2026-10-05 07:45:00', 'selesai',  'Keran sudah diganti baru.', 'laporan/perbaikan_001.jpg', '2026-10-06 15:00:00'),
(2, 4, 'Lampu kamar mati.',              'laporan/lap_002.jpg', '2026-11-18 21:10:00', 'dilaporkan', NULL, NULL, NULL);

-- Denda piket Budi (tidak piket 14 Nov) -> tagihan baru di tabel tagihan
INSERT INTO tagihan (id_tagihan, id_sewa, jenis_tagihan, jumlah_tagihan, tanggal_jatuh_tempo, status_tagihan) VALUES
(6, 1, 'denda_piket', 20000.00, '2026-11-21', 'belum_bayar');

INSERT INTO piket (id_sewa, id_tagihan, tanggal_piket, foto_piket, waktu_piket, status_piket, catatan_admin) VALUES
(1, NULL, '2026-11-07', 'piket/piket_001.jpg', '2026-11-07 18:20:00', 'selesai',     NULL),
(1, 6,    '2026-11-14', NULL,                  NULL,                  'tidak_piket', 'Tidak ada bukti piket sampai akhir hari.'),
(2, NULL, '2026-11-21', NULL,                  NULL,                  'terjadwal',   NULL);

-- Tamu Sari (sewa 2) menginap 1 malam di bulan Oktober
INSERT INTO kunjungan_menginap (id_kunjungan, id_pengunjung, id_sewa, tanggal_masuk, tanggal_keluar) VALUES
(1, 1, 2, '2026-10-10', '2026-10-11');

-- Rincian tagihan periode Oktober 2026 milik Sari = tagihan id 5 (listrik_air, 127.500)
-- listrik 60 kWh x 1.500 = 90.000, air 5 m3 x 5.000 = 25.000, sampah 7.500, menginap 1 hari x 5.000
INSERT INTO tagihan_bulanan (id_sewa, id_tagihan, id_kunjungan, periode, tagihan_listrik, tagihan_air, tagihan_sampah, tagihan_pengunjung) VALUES
(2, 5, 1, '2026-10', 90000.00, 25000.00, 7500.00, 5000.00);

INSERT INTO notifikasi (id_pengguna, id_tagihan, id_laporan, jenis_notifikasi, pesan, waktu_kirim, status_baca) VALUES
(4, 5,    NULL, 'tagihan',   'Tagihan listrik & air Oktober Rp127.500 jatuh tempo 25 Nov 2026 (H-7).', '2026-11-18 08:00:00', 0),
(3, 6,    NULL, 'denda',     'Kamu dikenai denda piket Rp20.000 untuk jadwal 14 Nov 2026.',           '2026-11-15 08:00:00', 1),
(3, NULL, 1,    'aduan',     'Laporan "Keran kamar mandi bocor" sudah selesai diperbaiki.',          '2026-10-06 15:05:00', 1),
(1, NULL, 2,    'aduan',     'Laporan kerusakan baru dari Sari Lestari: lampu kamar mati.',          '2026-11-18 21:10:30', 0),
(1, NULL, NULL, 'perpanjangan', 'Budi Santoso mengajukan perpanjangan sewa Kamar K-01.',            '2026-11-19 09:00:00', 0),
(1, NULL, NULL, 'pemesanan', 'Pemesanan baru dari Rina Wulandari untuk Kamar K-05.',                 '2026-11-17 20:05:30', 0);
