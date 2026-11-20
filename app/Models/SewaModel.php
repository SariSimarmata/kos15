<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model tabel `sewa`
 * Masa sewa satu tahun.
 */
class SewaModel extends Model
{
    protected $table            = 'sewa';
    protected $primaryKey       = 'id_sewa';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id_pemesanan',
        'tanggal_mulai',
        'tanggal_berakhir',
        'status_sewa',
    ];

    // Tabel tidak memiliki kolom created_at / updated_at
    protected $useTimestamps = false;
}
