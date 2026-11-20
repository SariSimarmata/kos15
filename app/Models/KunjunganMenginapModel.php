<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model tabel `kunjungan_menginap`
 * Kunjungan tamu yang menginap.
 */
class KunjunganMenginapModel extends Model
{
    protected $table            = 'kunjungan_menginap';
    protected $primaryKey       = 'id_kunjungan';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id_pengunjung',
        'id_sewa',
        'tanggal_masuk',
        'tanggal_keluar',
    ];

    // Tabel tidak memiliki kolom created_at / updated_at
    protected $useTimestamps = false;
}
