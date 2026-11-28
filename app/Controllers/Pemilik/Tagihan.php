<?php

namespace App\Controllers\Pemilik;

use App\Controllers\BaseController;
use App\Libraries\QrisGateway;
use App\Models\TagihanModel;
use DateTimeImmutable;
use DateTimeZone;

/**
 * GET pemilik/tagihan : pantau semua tagihan penghuni dan status pembayarannya (menu pemilik "Tagihan").
 * ?status=belum_bayar|lunas memilih tab. Hanya baca; pelunasan QRIS tetap otomatis lewat callback.
 * Login dan peran pemilik dijaga filter 'peran:pemilik' di Routes.php.
 */
class Tagihan extends BaseController
{
    protected $helpers = ['tanggal', 'kos'];

    public function index()
    {
        $status = (string) $this->request->getGet('status');
        if (! in_array($status, ['belum_bayar', 'lunas'], true)) {
            $status = '';
        }

        $sekarang = new DateTimeImmutable('now', new DateTimeZone(QrisGateway::ZONA_WAKTU));
        $model    = model(TagihanModel::class);
        $semua    = $model->daftarPemilik(null, $sekarang->format('Y-m-d H:i:s'));

        $ringkas = ['semua' => count($semua), 'belum_bayar' => 0, 'lunas' => 0, 'nominalBelum' => 0.0, 'nominalLunas' => 0.0, 'terlambat' => 0];
        foreach ($semua as $t) {
            $ringkas[$t['status_tagihan']]++;
            if ($t['status_tagihan'] === 'lunas') {
                $ringkas['nominalLunas'] += (float) $t['jumlah_tagihan'];
            } else {
                $ringkas['nominalBelum'] += (float) $t['jumlah_tagihan'];
                if ($t['tanggal_jatuh_tempo'] < $sekarang->format('Y-m-d')) {
                    $ringkas['terlambat']++;
                }
            }
        }

        $tagihan = $status === '' ? $semua : array_values(array_filter($semua, static fn ($t) => $t['status_tagihan'] === $status));

        return view('pemilik/tagihan/index', [
            'judul'   => 'Tagihan',
            'tagihan' => $tagihan,
            'status'  => $status,
            'ringkas' => $ringkas,
            'hariIni' => $sekarang->format('Y-m-d'),
        ]);
    }
}
