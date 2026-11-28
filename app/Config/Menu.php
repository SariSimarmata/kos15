<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Menu navbar per peran (Kartu 5).
 *
 * Kartu lain cukup menambah atau mengubah baris di sini,
 * tidak perlu menyentuh file layout.
 * Format: 'url relatif' => 'Label menu'
 * Urutan menu mengikuti use case di dokumen UC-01 sampai UC-36.
 */
class Menu extends BaseConfig
{
    public array $pemilik = [
        'pemilik/dashboard'   => 'Dasbor',
        'pemilik/kamar'       => 'Kamar',
        'pemilik/pemesanan'   => 'Pemesanan',
        'pemilik/penghuni'    => 'Penghuni',
        'pemilik/tagihan'     => 'Tagihan',
        'pemilik/meteran'     => 'Meteran',
        'pemilik/piket'       => 'Piket',
        'pemilik/kerusakan'   => 'Kerusakan',
        'pemilik/pengumuman'  => 'Pengumuman',
        'pemilik/info-kos'    => 'Info Kos',
    ];

    public array $penghuni = [
        'penghuni/tagihan'    => 'Tagihan',
        'penghuni/riwayat'    => 'Riwayat Bayar',
        'penghuni/piket'      => 'Piket',
        'penghuni/pengunjung' => 'Pengunjung',
        'penghuni/kerusakan'  => 'Lapor Kerusakan',
        'penghuni/sewa'       => 'Masa Sewa',
        'pengumuman'          => 'Pengumuman',
    ];

    public array $calon_penghuni = [
        'katalog'             => 'Katalog',
        'pemesanan/status'    => 'Status Pengajuan',
        'pengumuman'          => 'Pengumuman',
    ];

    /** Menu untuk tamu yang belum login. */
    public array $tamu = [
        'katalog' => 'Katalog',
    ];

    /** Label peran yang tampil di navbar. */
    public array $labelPeran = [
        'pemilik'        => 'Pemilik',
        'penghuni'       => 'Penghuni',
        'calon_penghuni' => 'Calon Penghuni',
    ];

    public function untuk(?string $peran): array
    {
        return match ($peran) {
            'pemilik'        => $this->pemilik,
            'penghuni'       => $this->penghuni,
            'calon_penghuni' => $this->calon_penghuni,
            default          => [],
        };
    }
}
