<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model tabel `pengguna`
 * Akun pemilik, admin, penghuni, dan calon penghuni.
 */
class PenggunaModel extends Model
{
    protected $table            = 'pengguna';
    protected $primaryKey       = 'id_pengguna';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'nama',
        'username',
        'password',
        'no_hp',
        'role',
    ];

    // Tabel tidak memiliki kolom created_at / updated_at
    protected $useTimestamps = false;

    // Password otomatis di-hash saat insert/update (tidak di-hash dua kali)
    protected $allowCallbacks = true;
    protected $beforeInsert   = ['hashPassword'];
    protected $beforeUpdate   = ['hashPassword'];

    protected function hashPassword(array $data): array
    {
        if (! isset($data['data']['password']) || $data['data']['password'] === '') {
            return $data;
        }

        $password = (string) $data['data']['password'];

        if (password_get_info($password)['algo'] === null) {
            $data['data']['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        return $data;
    }
}
