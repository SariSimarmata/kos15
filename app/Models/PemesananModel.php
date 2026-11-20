<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model tabel `pemesanan`
 * Pengajuan pemesanan kamar.
 */
class PemesananModel extends Model
{
    protected $table            = 'pemesanan';
    protected $primaryKey       = 'id_pemesanan';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id_pengguna',
        'id_kamar',
        'tanggal_mulai_sewa',
        'tanggal_pengajuan',
        'status_pengajuan',
    ];

    // Tabel tidak memiliki kolom created_at / updated_at
    protected $useTimestamps = false;
}
