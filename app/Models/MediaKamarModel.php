<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model tabel `media_kamar`
 * Foto dan video kamar.
 */
class MediaKamarModel extends Model
{
    protected $table            = 'media_kamar';
    protected $primaryKey       = 'id_media';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id_kamar',
        'jenis',
        'file',
    ];

    // Tabel tidak memiliki kolom created_at / updated_at
    protected $useTimestamps = false;
}
