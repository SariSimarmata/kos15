<?php

namespace App\Controllers\Pemilik;

use App\Controllers\BaseController;
use App\Models\TagihanModel;

/**
 * Halaman tagihan pemilik (Kartu 3): seluruh tagihan dengan filter belum lunas dan lewat jatuh tempo.
 * Route dilindungi filter 'peran:pemilik' di Routes.php.
 */
class Tagihan extends BaseController
{
    protected $helpers = ['tanggal', 'kos'];

    /** GET /pemilik/tagihan?filter=semua|belum_lunas|lewat_jatuh_tempo */
    public function index()
    {
        $filter = (string) $this->request->getGet('filter');
        if (! array_key_exists($filter, TagihanModel::FILTER)) {
            $filter = 'semua';
        }

        $model   = model(TagihanModel::class);
        $hariIni = TagihanModel::hariIni();

        return view('pemilik/tagihan/index', [
            'judul'     => 'Tagihan',
            'filter'    => $filter,
            'hariIni'   => $hariIni,
            'tagihan'   => $model->daftarPemilik($filter, $hariIni),
            'ringkasan' => $model->ringkasan($hariIni),
        ]);
    }
}
