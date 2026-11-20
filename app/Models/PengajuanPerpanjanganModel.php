<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model tabel `pengajuan_perpanjangan`
 * Pengajuan perpanjangan masa sewa.
 */
class PengajuanPerpanjanganModel extends Model
{
    protected $table            = 'pengajuan_perpanjangan';
    protected $primaryKey       = 'id_perpanjangan';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id_sewa',
        'tanggal_perpanjangan',
        'status',
    ];

    // Tabel tidak memiliki kolom created_at / updated_at
    protected $useTimestamps = false;
}
