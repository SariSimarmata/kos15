<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model tabel `info_kos`
 * Informasi umum dan tarif kos (hanya 1 baris).
 */
class InfoKosModel extends Model
{
    protected $table            = 'info_kos';
    protected $primaryKey       = 'id_info';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'lokasi',
        'jadwal_pengingat',
        'no_whatsapp',
        'tarif_listrik_per_kwh',
        'tarif_air_per_m3',
        'denda_piket_per_hari',
        'tarif_menginap_per_hari',
        'batas_menginap_per_minggu',
    ];

    // Tabel tidak memiliki kolom created_at / updated_at
    protected $useTimestamps = false;
}
