<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model tabel `survei_kamar`
 * Pengajuan survei kamar tanpa login.
 */
class SurveiKamarModel extends Model
{
    protected $table            = 'survei_kamar';
    protected $primaryKey       = 'id_survei';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id_kamar',
        'nama_calon',
        'no_hp',
        'tanggal_pengajuan',
        'tanggal_survei',
        'waktu_survei',
    ];

    // Tabel tidak memiliki kolom created_at / updated_at
    protected $useTimestamps = false;

    /**
     * Survei yang sama persis (kamar, nomor HP, tanggal, jam) sudah ada?
     * Dipakai untuk mencegah data ganda saat tombol ditekan dua kali.
     */
    public function cariDuplikat(int $idKamar, string $noHp, string $tanggal, string $waktu): ?array
    {
        return $this->where([
            'id_kamar'       => $idKamar,
            'no_hp'          => $noHp,
            'tanggal_survei' => $tanggal,
            'waktu_survei'   => $waktu,
        ])->first();
    }
}
