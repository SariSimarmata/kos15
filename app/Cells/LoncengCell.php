<?php

namespace App\Cells;

use App\Models\NotifikasiModel;
use CodeIgniter\View\Cells\Cell;
use Throwable;

/**
 * Lonceng notifikasi di navbar (Kartu 5).
 * Menghitung notifikasi belum dibaca milik pengguna yang sedang login
 * dan menampilkan lima notifikasi terbaru.
 *
 * Dipanggil di layout: <?= view_cell('LoncengCell') ?>
 */
class LoncengCell extends Cell
{
    public int $jumlahBelumDibaca = 0;
    public array $terbaru = [];

    public function mount(): void
    {
        $idPengguna = (int) session('id_pengguna');

        if ($idPengguna === 0) {
            return;
        }

        try {
            $model = model(NotifikasiModel::class);

            $this->jumlahBelumDibaca = $model
                ->where('id_pengguna', $idPengguna)
                ->where('status_baca', 0)
                ->countAllResults();

            $this->terbaru = $model
                ->where('id_pengguna', $idPengguna)
                ->orderBy('waktu_kirim', 'DESC')
                ->findAll(5);
        } catch (Throwable $e) {
            // Lonceng tidak boleh membuat seluruh halaman gagal tampil
            log_message('error', 'Lonceng notifikasi: ' . $e->getMessage());
        }
    }
}
