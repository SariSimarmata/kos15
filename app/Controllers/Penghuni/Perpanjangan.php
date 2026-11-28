<?php

namespace App\Controllers\Penghuni;

use App\Controllers\BaseController;

/**
 * Kartu 11 - sisi penghuni: mengajukan perpanjangan sewa satu tahun.
 * Memakai tabel tim: sewa, pemesanan, pengajuan_perpanjangan, notifikasi, pengguna.
 *
 * ASUMSI (cek di kos15.sql): pemesanan punya kolom id_pengguna dan id_kamar,
 * pengguna.role bernilai 'pemilik' (sudah dicek). Key session login: id_pengguna (sudah dicek).
 */
class Perpanjangan extends BaseController
{
    public function ajukan(int $idSewa)
    {
        $db = \Config\Database::connect();
        $db->transStart();

        // Kunci baris sewa agar dua klik bersamaan tidak lolos dua-duanya
        $sewa = $db->query(
            'SELECT s.*, p.id_pengguna, p.id_kamar
               FROM sewa s JOIN pemesanan p ON p.id_pemesanan = s.id_pemesanan
              WHERE s.id_sewa = ? FOR UPDATE',
            [$idSewa]
        )->getRow();

        if (!$sewa || (int) $sewa->id_pengguna !== (int) session('id_pengguna') || $sewa->status_sewa !== 'aktif') {
            $db->transRollback();
            return redirect()->back()->with('error', 'Sewa tidak ditemukan atau belum aktif.');
        }

        // Hanya satu pengajuan menunggu per sewa
        $menunggu = $db->table('pengajuan_perpanjangan')
            ->where(['id_sewa' => $idSewa, 'status' => 'pending'])->countAllResults();
        if ($menunggu > 0) {
            $db->transRollback();
            return redirect()->back()->with('error', 'Masih ada pengajuan yang menunggu keputusan.');
        }

        // Tanggal perpanjangan = awal sewa baru (sehari setelah sewa lama berakhir)
        $db->table('pengajuan_perpanjangan')->insert([
            'id_sewa'              => $idSewa,
            'tanggal_perpanjangan' => date('Y-m-d', strtotime($sewa->tanggal_berakhir . ' +1 day')),
            'status'               => 'pending',
        ]);

        // Notifikasi ke semua pemilik
        $pemilik = $db->table('pengguna')->where('role', 'pemilik')->get()->getResult();
        foreach ($pemilik as $pm) {
            $db->table('notifikasi')->insert([
                'id_pengguna'      => $pm->id_pengguna,
                'jenis_notifikasi' => 'perpanjangan',
                'pesan'            => "Penghuni mengajukan perpanjangan sewa satu tahun untuk sewa #{$idSewa}.",
            ]);
        }
        $db->transComplete();

        if (!$db->transStatus()) {
            return redirect()->back()->with('error', 'Pengajuan gagal, coba lagi.');
        }
        return redirect()->back()->with('success', 'Pengajuan perpanjangan terkirim.');
    }
}
