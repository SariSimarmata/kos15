<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model tabel `laporan_kerusakan`
 * Laporan kerusakan fasilitas.
 */
class LaporanKerusakanModel extends Model
{
    protected $table            = 'laporan_kerusakan';
    protected $primaryKey       = 'id_laporan';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id_pengguna',
        'deskripsi',
        'foto',
        'tanggal_lapor',
        'status_perbaikan',
        'catatan_perbaikan',
        'foto_perbaikan',
        'tanggal_selesai',
    ];

    // Tabel tidak memiliki kolom created_at / updated_at
    protected $useTimestamps = false;
}
