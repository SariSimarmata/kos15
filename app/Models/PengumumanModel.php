<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model tabel `pengumuman`
 * Pengumuman dan peraturan kos.
 */
class PengumumanModel extends Model
{
    protected $table            = 'pengumuman';
    protected $primaryKey       = 'id_pengumuman';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'jenis',
        'isi',
        'tanggal_terbit',
    ];

    // Tabel tidak memiliki kolom created_at / updated_at
    protected $useTimestamps = false;
}
