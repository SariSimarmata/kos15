<?php

namespace App\Controllers\Pemilik;

use App\Controllers\BaseController;

/**
 * Kartu 11 - halaman pemilik: daftar pengajuan perpanjangan (setujui/tolak)
 * dan sewa menunggu pembayaran (batalkan). Aksinya ada di Pemilik\Perpanjangan.
 */
class DaftarPerpanjangan extends BaseController
{
    public function index()
    {
        helper('tanggal');
        $db = \Config\Database::connect();

        $pengajuan = $db->query(
            "SELECT pe.id_perpanjangan, pe.id_sewa, pe.tanggal_perpanjangan,
                    s.tanggal_berakhir, k.nomor_kamar, pg.*
               FROM pengajuan_perpanjangan pe
               JOIN sewa s       ON s.id_sewa = pe.id_sewa
               JOIN pemesanan p  ON p.id_pemesanan = s.id_pemesanan
               JOIN kamar k      ON k.id_kamar = p.id_kamar
               JOIN pengguna pg  ON pg.id_pengguna = p.id_pengguna
              WHERE pe.status = 'pending'
              ORDER BY pe.id_perpanjangan"
        )->getResult();

        $menunggu = $db->query(
            "SELECT s.id_sewa, s.tanggal_mulai, s.tanggal_berakhir, k.nomor_kamar, pg.*
               FROM sewa s
               JOIN pemesanan p  ON p.id_pemesanan = s.id_pemesanan
               JOIN kamar k      ON k.id_kamar = p.id_kamar
               JOIN pengguna pg  ON pg.id_pengguna = p.id_pengguna
              WHERE s.status_sewa = 'menunggu_pembayaran'
              ORDER BY s.tanggal_mulai"
        )->getResult();

        return view('pemilik/perpanjangan/index', [
            'pengajuan' => $pengajuan,
            'menunggu'  => $menunggu,
        ]);
    }
}

