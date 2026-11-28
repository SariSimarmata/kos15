<?php

namespace App\Controllers\Pemilik;

use App\Controllers\BaseController;

/**
 * Kartu 11 - sisi pemilik: setujui/tolak perpanjangan dan batalkan sewa.
 * Memakai tabel tim: sewa, pemesanan, kamar, tagihan, pengajuan_perpanjangan, notifikasi.
 *
 * Sudah dicek ke kos15.sql: pemesanan punya id_pengguna dan id_kamar.
 * Butuh sql/kartu11_perubahan.sql (status_tagihan 'batal').
 */
class Perpanjangan extends BaseController
{
    public function setujui(int $idPerpanjangan)
    {
        $db = \Config\Database::connect();
        $db->transStart();

        $p = $db->query('SELECT * FROM pengajuan_perpanjangan WHERE id_perpanjangan = ? FOR UPDATE', [$idPerpanjangan])->getRow();
        if (!$p || $p->status !== 'pending') {
            $db->transRollback();
            return redirect()->back()->with('error', 'Pengajuan tidak ditemukan atau sudah diputuskan.');
        }
        $lama = $this->infoSewa($db, (int) $p->id_sewa);

        // Sewa baru mulai sehari setelah sewa lama berakhir, durasi satu tahun
        $mulai    = date('Y-m-d', strtotime($lama->tanggal_berakhir . ' +1 day'));
        $berakhir = date('Y-m-d', strtotime($mulai . ' +1 year -1 day'));

        // Status 'menunggu_pembayaran' sampai pelunasan dibayar
        $db->table('sewa')->insert([
            'id_pemesanan'     => $lama->id_pemesanan,
            'tanggal_mulai'    => $mulai,
            'tanggal_berakhir' => $berakhir,
            'status_sewa'      => 'menunggu_pembayaran',
        ]);
        $idSewaBaru = $db->insertID();

        // Tagihan pelunasan sewa baru (harga sewa tahunan kamar, jatuh tempo = tanggal mulai)
        $kamar = $db->table('kamar')->where('id_kamar', $lama->id_kamar)->get()->getRow();
        $db->table('tagihan')->insert([
            'id_sewa'             => $idSewaBaru,
            'jenis_tagihan'       => 'pelunasan',
            'jumlah_tagihan'      => $kamar->harga_sewa_tahunan,
            'tanggal_jatuh_tempo' => $mulai,
            'status_tagihan'      => 'belum_bayar',
        ]);
        $idTagihan = $db->insertID();

        $db->table('pengajuan_perpanjangan')->where('id_perpanjangan', $idPerpanjangan)->update(['status' => 'approved']);

        $db->table('notifikasi')->insert([
            'id_pengguna'      => $lama->id_pengguna,
            'id_tagihan'       => $idTagihan,
            'jenis_notifikasi' => 'perpanjangan',
            'pesan'            => 'Perpanjangan sewa disetujui. Tagihan pelunasan sewa baru sudah dibuat.',
        ]);
        $db->transComplete();

        return $db->transStatus()
            ? redirect()->back()->with('success', 'Perpanjangan disetujui.')
            : redirect()->back()->with('error', 'Gagal menyetujui perpanjangan.');
    }

    public function tolak(int $idPerpanjangan)
    {
        $db = \Config\Database::connect();
        $db->transStart();

        $p = $db->query('SELECT * FROM pengajuan_perpanjangan WHERE id_perpanjangan = ? FOR UPDATE', [$idPerpanjangan])->getRow();
        if (!$p || $p->status !== 'pending') {
            $db->transRollback();
            return redirect()->back()->with('error', 'Pengajuan tidak ditemukan atau sudah diputuskan.');
        }
        $sewa = $this->infoSewa($db, (int) $p->id_sewa);

        $db->table('pengajuan_perpanjangan')->where('id_perpanjangan', $idPerpanjangan)->update(['status' => 'rejected']);
        $db->table('notifikasi')->insert([
            'id_pengguna'      => $sewa->id_pengguna,
            'jenis_notifikasi' => 'perpanjangan',
            'pesan'            => 'Pengajuan perpanjangan sewa ditolak.',
        ]);
        $db->transComplete();

        return $db->transStatus()
            ? redirect()->back()->with('success', 'Pengajuan ditolak.')
            : redirect()->back()->with('error', 'Gagal menolak pengajuan.');
    }

    /** Pembatalan: status sewa, tagihan, dan kamar diubah dalam SATU transaksi. */
    public function batalkan(int $idSewa)
    {
        $db = \Config\Database::connect();
        $db->transStart();

        $sewa = $db->query(
            'SELECT s.*, p.id_pengguna, p.id_kamar
               FROM sewa s JOIN pemesanan p ON p.id_pemesanan = s.id_pemesanan
              WHERE s.id_sewa = ? FOR UPDATE',
            [$idSewa]
        )->getRow();

        $pelunasan = $sewa ? $db->table('tagihan')
            ->where(['id_sewa' => $idSewa, 'jenis_tagihan' => 'pelunasan'])->get()->getRow() : null;

        // Syarat: sewa belum aktif, pelunasan belum dibayar, dan sudah lewat tanggal mulai
        if (!$sewa || !$pelunasan
            || $sewa->status_sewa !== 'menunggu_pembayaran'
            || $pelunasan->status_tagihan !== 'belum_bayar'
            || date('Y-m-d') < $sewa->tanggal_mulai) {
            $db->transRollback();
            return redirect()->back()->with('error', 'Sewa belum memenuhi syarat untuk dibatalkan.');
        }

        $db->table('sewa')->where('id_sewa', $idSewa)->update(['status_sewa' => 'batal']);
        $db->table('tagihan')->where('id_sewa', $idSewa)->where('status_tagihan', 'belum_bayar')->update(['status_tagihan' => 'batal']);
        // Kamar hanya dikosongkan kalau tidak ada sewa aktif lain di kamar yang sama
$masihAktif = (int) $db->query(
    "SELECT COUNT(*) AS n FROM sewa s JOIN pemesanan p ON p.id_pemesanan = s.id_pemesanan
      WHERE p.id_kamar = ? AND s.status_sewa = 'aktif' AND s.id_sewa <> ?",
    [$sewa->id_kamar, $idSewa]
)->getRow()->n;
if ($masihAktif === 0) {
    $db->table('kamar')->where('id_kamar', $sewa->id_kamar)->update(['status_kamar' => 'kosong']);
}

        $db->table('notifikasi')->insert([
            'id_pengguna'      => $sewa->id_pengguna,
            'id_tagihan'       => $pelunasan->id_tagihan,
            'jenis_notifikasi' => 'tagihan',
            'pesan'            => 'Sewa dibatalkan karena pelunasan belum dibayar sampai tanggal mulai.',
        ]);
        $db->transComplete();

        return $db->transStatus()
            ? redirect()->back()->with('success', 'Sewa dibatalkan.')
            : redirect()->back()->with('error', 'Pembatalan gagal.');
    }

    private function infoSewa($db, int $idSewa)
    {
        return $db->table('sewa s')
            ->select('s.*, p.id_pengguna, p.id_kamar')
            ->join('pemesanan p', 'p.id_pemesanan = s.id_pemesanan')
            ->where('s.id_sewa', $idSewa)->get()->getRow();
    }
}
