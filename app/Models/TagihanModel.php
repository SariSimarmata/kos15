<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model tabel `tagihan`
 * Semua jenis tagihan (DP, pelunasan, listrik_air, denda_piket, menginap_pengunjung).
 */
class TagihanModel extends Model
{
    protected $table            = 'tagihan';
    protected $primaryKey       = 'id_tagihan';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id_sewa',
        'jenis_tagihan',
        'jumlah_tagihan',
        'tanggal_jatuh_tempo',
        'status_tagihan',
    ];

    // Tabel tidak memiliki kolom created_at / updated_at
    protected $useTimestamps = false;
}
