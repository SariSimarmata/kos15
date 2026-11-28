<?php

namespace App\Controllers;

use App\Libraries\NotifikasiService;
use App\Models\NotifikasiModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Halaman notifikasi untuk semua peran (kartu Notifikasi dan Pengingat Harian).
 * Login dijaga filter 'peran' di Routes.php; setiap pengguna hanya melihat notifikasinya sendiri.
 *
 *   GET  notifikasi              daftar notifikasi
 *   GET  notifikasi/{id}/buka    tandai dibaca lalu buka halaman terkait
 *   POST notifikasi/baca-semua   tandai semua sudah dibaca
 */
class Notifikasi extends BaseController
{
    protected $helpers = ['tanggal'];

    public function index()
    {
        $model  = model(NotifikasiModel::class);
        $id     = (int) session('id_pengguna');
        $filter = $this->request->getGet('filter') === 'belum' ? 'belum' : 'semua';

        return view('notifikasi/index', [
            'judul'       => 'Notifikasi',
            'notifikasi'  => $model->milikPengguna($id, 20, $filter === 'belum'),
            'pager'       => $model->pager,
            'filter'      => $filter,
            'jumlahSemua' => model(NotifikasiModel::class)->where('id_pengguna', $id)->countAllResults(),
            'belumDibaca' => model(NotifikasiModel::class)->where('id_pengguna', $id)->where('status_baca', 0)->countAllResults(),
            'peran'       => (string) session('role'),
        ]);
    }

    public function buka(int $idNotifikasi): RedirectResponse
    {
        $idPengguna = (int) session('id_pengguna');
        $model      = model(NotifikasiModel::class);
        $notif      = $model->where('id_pengguna', $idPengguna)->find($idNotifikasi);

        // Notifikasi orang lain diperlakukan sama dengan yang tidak ada
        if ($notif === null) {
            return redirect()->to('notifikasi')->with('gagal', 'Notifikasi tidak ditemukan.');
        }

        $model->tandaiDibaca($idNotifikasi, $idPengguna);

        $tujuan = (new NotifikasiService())->tujuan($notif, (string) session('role'));
        if ($tujuan === 'notifikasi') {
            return redirect()->to('notifikasi')->with('sukses', 'Notifikasi ditandai sudah dibaca. Halaman terkaitnya belum tersedia.');
        }

        return redirect()->to($tujuan);
    }

    public function bacaSemua(): RedirectResponse
    {
        $jumlah = model(NotifikasiModel::class)->tandaiSemuaDibaca((int) session('id_pengguna'));

        return redirect()->to('notifikasi')->with('sukses', $jumlah > 0
            ? $jumlah . ' notifikasi ditandai sudah dibaca.'
            : 'Semua notifikasi sudah dibaca.');
    }
}
