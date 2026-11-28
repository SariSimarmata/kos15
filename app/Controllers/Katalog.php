<?php

namespace App\Controllers;

use App\Models\InfoKosModel;
use App\Models\KamarModel;
use App\Models\PengumumanModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Kartu 7: katalog publik dan detail kamar (tanpa login).
 * Data kamar dan media memakai method katalog publik dari Kartu 6 (KamarModel::getKatalog / getDetailKamar).
 */
class Katalog extends BaseController
{
    protected $helpers = ['url', 'kos'];

    /** Filter di URL => nilai status_kamar di database. "Tersedia" di tampilan = "kosong". */
    private const FILTER = [
        'semua'    => null,
        'tersedia' => 'kosong',
        'terisi'   => 'terisi',
    ];

    /**
     * GET /katalog?status=semua|tersedia|terisi
     */
    public function index()
    {
        $filter = (string) $this->request->getGet('status');
        if (! array_key_exists($filter, self::FILTER)) {
            $filter = 'semua';
        }

        $semua  = model(KamarModel::class)->getKatalog();
        $status = self::FILTER[$filter];
        $kamar  = $status === null
            ? $semua
            : array_values(array_filter($semua, static fn ($k) => $k['status_kamar'] === $status));

        $jumlahKosong = count(array_filter($semua, static fn ($k) => $k['status_kamar'] === 'kosong'));

        return view('katalog/index', [
            'judul'  => 'Katalog Kamar',
            'filter' => $filter,
            'kamar'  => $kamar,
            'jumlah' => [
                'semua'    => count($semua),
                'tersedia' => $jumlahKosong,
                'terisi'   => count($semua) - $jumlahKosong,
            ],
        ]);
    }

    /**
     * GET /katalog/{id}
     */
    public function detail(int $idKamar)
    {
        $kamar = model(KamarModel::class)->getDetailKamar($idKamar);
        if ($kamar === null) {
            throw PageNotFoundException::forPageNotFound('Kamar tidak ditemukan.');
        }

        // Foto dulu, lalu video, masing-masing urut waktu unggah
        $media = $kamar['media'];
        usort($media, static fn ($a, $b) => [$a['jenis'] !== 'foto', $a['id_media']] <=> [$b['jenis'] !== 'foto', $b['id_media']]);

        $harga = (float) $kamar['harga_sewa_tahunan'];
        $dp    = round($harga * 0.5);

        return view('katalog/detail', [
            'judul'     => 'Kamar ' . $kamar['nomor_kamar'],
            'kamar'     => $kamar,
            'tersedia'  => $kamar['status_kamar'] === 'kosong',
            // Tombol Pesan untuk tamu (diarahkan ke login oleh filter peran) dan calon penghuni.
            'bisaPesan' => ! session('logged_in') || session('role') === 'calon_penghuni',
            'media'     => $media,
            'fasilitas' => daftar_fasilitas($kamar['fasilitas']),
            'peraturan' => model(PengumumanModel::class)->peraturan(),
            'info'      => $this->infoKos(),
            'harga'     => $harga,
            'dp'        => $dp,
            'sisa'      => $harga - $dp,
        ]);
    }

    /** Info kos untuk tampilan publik; null bila pemilik belum mengisinya. */
    private function infoKos(): ?array
    {
        $info = model(InfoKosModel::class)->ambil();

        return $info['id_info'] === null ? null : $info;
    }
}
