<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model tabel `kamar`
 * Katalog kamar kos.
 */
class KamarModel extends Model
{
    protected $table            = 'kamar';
    protected $primaryKey       = 'id_kamar';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'nomor_kamar',
        'fasilitas',
        'harga_sewa_tahunan',
        'status_kamar',
    ];

    // Tabel tidak memiliki kolom created_at / updated_at
    protected $useTimestamps = false;
}
