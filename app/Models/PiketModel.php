<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model tabel `piket`
 * Jadwal dan bukti piket kebersihan.
 */
class PiketModel extends Model
{
    protected $table            = 'piket';
    protected $primaryKey       = 'id_piket';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id_sewa',
        'id_tagihan',
        'tanggal_piket',
        'foto_piket',
        'waktu_piket',
        'status_piket',
        'catatan_admin',
    ];

    // Tabel tidak memiliki kolom created_at / updated_at
    protected $useTimestamps = false;
}
