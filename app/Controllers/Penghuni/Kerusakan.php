<?php

namespace App\Controllers\Penghuni;

use App\Controllers\BaseController;
use App\Libraries\NotifikasiService;
use App\Models\LaporanKerusakanModel;
use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

/**
 * Laporan kerusakan untuk penghuni.
 * Route dilindungi filter 'peran:penghuni' di Config/Routes.php.
 *
 *   GET  penghuni/kerusakan          laporan bersama (semua laporan, hanya baca, TANPA nama pelapor)
 *   GET  penghuni/kerusakan/saya     laporan milik penghuni yang login
 *   GET  penghuni/kerusakan/baru     form laporan
 *   POST penghuni/kerusakan/simpan   simpan laporan
 *
 * Pelapor dan kamar tidak pernah dibaca dari form: pelapor = akun yang login (session),
 * kamar = kamar dari sewa aktifnya.
 */
class Kerusakan extends BaseController
{
    protected $helpers = ['form', 'url', 'tanggal', 'kos'];

    /** GET /penghuni/kerusakan */
    public function index()
    {
        return view('penghuni/kerusakan/daftar', [
            'judul'   => 'Laporan Kerusakan',
            'mode'    => 'bersama',
            'laporan' => model(LaporanKerusakanModel::class)->bersama(),
        ]);
    }

    /** GET /penghuni/kerusakan/saya */
    public function saya()
    {
        return view('penghuni/kerusakan/daftar', [
            'judul'   => 'Laporan Kerusakan',
            'mode'    => 'saya',
            'laporan' => model(LaporanKerusakanModel::class)->milikPengguna((int) session('id_pengguna')),
        ]);
    }

    /** GET /penghuni/kerusakan/baru */
    public function baru()
    {
        return view('penghuni/kerusakan/form', [
            'judul' => 'Buat Laporan Kerusakan',
            'kamar' => model(LaporanKerusakanModel::class)->kamarAktif((int) session('id_pengguna')),
        ]);
    }

    /** POST /penghuni/kerusakan/simpan */
    public function simpan(): RedirectResponse
    {
        $model      = model(LaporanKerusakanModel::class);
        $idPengguna = (int) session('id_pengguna');

        // Kamar dari sewa aktif. Isian "kamar" dari form (kalau ada) diabaikan.
        $kamar = $model->kamarAktif($idPengguna);
        if ($kamar === null) {
            return redirect()->to('penghuni/kerusakan/baru')
                ->with('gagal', 'Laporan hanya bisa dibuat oleh penghuni dengan sewa aktif.');
        }

        $galat = [];
        if (! $this->validate(LaporanKerusakanModel::aturanLaporan(), LaporanKerusakanModel::pesanLaporan())) {
            $galat = $this->validator->getErrors();
        }

        $foto      = $this->request->getFile('foto');
        $galatFoto = LaporanKerusakanModel::galatFoto($foto, true);
        if ($galatFoto !== null) {
            $galat['foto'] = $galatFoto;
        }

        if ($galat !== []) {
            return redirect()->back()->withInput()->with('errors', $galat);
        }

        $deskripsi = trim((string) $this->request->getPost('deskripsi'));
        $namaFoto  = null;

        try {
            $namaFoto = LaporanKerusakanModel::simpanFoto($foto);

            $berhasil = $model->insert([
                'id_pengguna'      => $idPengguna,
                'deskripsi'        => $deskripsi,
                'foto'             => $namaFoto,
                'tanggal_lapor'    => kos_now()->format('Y-m-d H:i:s'),
                'status_perbaikan' => 'dilaporkan',
            ]);
            if ($berhasil === false) {
                throw new \RuntimeException('insert laporan ditolak model');
            }
            $idLaporan = (int) $model->getInsertID();
        } catch (Throwable $e) {
            // Laporan gagal disimpan: jangan tinggalkan foto yatim di folder
            LaporanKerusakanModel::hapusFoto($namaFoto);
            log_message('error', 'Simpan laporan kerusakan gagal: ' . $e->getMessage());

            return redirect()->back()->withInput()
                ->with('gagal', 'Laporan gagal disimpan dan tidak ada data yang berubah. Coba lagi.');
        }

        // Notifikasi ke pemilik. Kalau gagal, laporan tetap tersimpan.
        try {
            (new NotifikasiService())->laporanKerusakan(
                $idLaporan,
                (string) session('nama'),
                (string) $kamar['nomor_kamar'],
                $deskripsi
            );
        } catch (Throwable $e) {
            log_message('error', 'Notifikasi laporan kerusakan ' . $idLaporan . ' gagal: ' . $e->getMessage());
        }

        return redirect()->to('penghuni/kerusakan/saya')
            ->with('sukses', 'Laporan kerusakan kamar ' . $kamar['nomor_kamar'] . ' terkirim. Pemilik sudah diberi tahu.');
    }
}
