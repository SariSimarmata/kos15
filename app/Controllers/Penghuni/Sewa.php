<?php

namespace App\Controllers\Penghuni;

use App\Controllers\BaseController;
use App\Models\SewaModel;

/**
 * Halaman masa sewa penghuni (Kartu 4 Sprint 4, KF-29).
 * Route dilindungi filter 'peran:penghuni', dan data selalu diambil dari id di session,
 * jadi penghuni hanya melihat sewa miliknya sendiri.
 */
class Sewa extends BaseController
{
    /** GET /penghuni/sewa */
    public function index()
    {
        helper('tanggal');

        $riwayat = model(SewaModel::class)->riwayatMilik((int) session('id_pengguna'));

        // Sewa utama: yang masih berjalan, kalau tidak ada yang terbaru
        $utama = null;
        foreach ($riwayat as $s) {
            if (SewaModel::berjalan($s['status_sewa'])) {
                $utama = $s;
                break;
            }
        }
        $utama ??= $riwayat[0] ?? null;

        return view('penghuni/sewa', [
            'judul'   => 'Masa Sewa',
            'utama'   => $utama,
            'riwayat' => $riwayat,
        ]);
    }
}
