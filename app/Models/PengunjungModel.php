<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model tabel `pengunjung`
 * Data tamu yang menginap.
 */
class PengunjungModel extends Model
{
    protected $table            = 'pengunjung';
    protected $primaryKey       = 'id_pengunjung';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'nama_pengunjung',
        'no_identitas',
        'no_hp',
    ];

    // Tabel tidak memiliki kolom created_at / updated_at
    protected $useTimestamps = false;
}
