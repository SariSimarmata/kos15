<?php

namespace App\Controllers\Pemilik;

use App\Controllers\BaseController;
use App\Models\InfoKosModel;
use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

/**
 * Info kos dan tarif oleh pemilik.
 * Tabel info_kos hanya satu baris, jadi form ini selalu MENGUBAH baris yang ada.
 * Route dilindungi filter 'peran:pemilik' di Config/Routes.php.
 */
class InfoKos extends BaseController
{
    protected $helpers = ['form', 'url', 'kos'];

    /** GET /pemilik/info-kos */
    public function index()
    {
        return view('pemilik/info_kos/form', [
            'judul' => 'Info Kos dan Tarif',
            'info'  => model(InfoKosModel::class)->ambil(),
        ]);
    }

    /** POST /pemilik/info-kos/simpan */
    public function simpan(): RedirectResponse
    {
        // Rapikan dulu (hapus spasi, "+62" jadi "62", "3, 7,1" jadi "7,3,1"), baru divalidasi
        $data = InfoKosModel::normalisasi((array) $this->request->getPost());

        $galat = [];
        if (! $this->validateData($data, InfoKosModel::aturan(), InfoKosModel::pesanAturan())) {
            $galat = $this->validator->getErrors();
        }

        if (! isset($galat['jadwal_pengingat']) && ! InfoKosModel::jadwalPengingatValid($data['jadwal_pengingat'])) {
            $galat['jadwal_pengingat'] = 'Jadwal pengingat berisi angka hari yang dipisah koma, antara 1 dan '
                . InfoKosModel::PENGINGAT_MAKS_HARI . ', paling banyak ' . InfoKosModel::PENGINGAT_MAKS_JUMLAH
                . ' angka. Contoh: 7,3,1.';
        }

        if ($galat !== []) {
            // Nilai yang ditampilkan kembali = ketikan pemilik apa adanya
            return redirect()->back()->withInput()->with('errors', $galat);
        }

        try {
            model(InfoKosModel::class)->simpanInfo(InfoKosModel::untukDisimpan($data));
        } catch (Throwable $e) {
            log_message('error', 'Simpan info kos gagal: ' . $e->getMessage());

            return redirect()->back()->withInput()
                ->with('gagal', 'Info kos gagal disimpan dan tidak ada data yang berubah. Coba lagi.');
        }

        return redirect()->to('pemilik/info-kos')->with('sukses', 'Info kos dan tarif berhasil disimpan.');
    }
}
