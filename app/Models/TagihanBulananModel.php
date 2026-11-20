<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model tabel `tagihan_bulanan`
 * Rincian tagihan listrik, air, sampah, dan pengunjung per bulan.
 */
class TagihanBulananModel extends Model
{
    protected $table            = 'tagihan_bulanan';
    protected $primaryKey       = 'id_tagih_bulanan';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id_sewa',
        'id_tagihan',
        'id_kunjungan',
        'periode',
        'tagihan_listrik',
        'tagihan_air',
        'tagihan_sampah',
        'tagihan_pengunjung',
    ];

    // Tabel tidak memiliki kolom created_at / updated_at
    protected $useTimestamps = false;
}
