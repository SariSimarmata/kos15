<?php

namespace Tests\Support;

/**
 * Akun uji untuk tes hak akses (feature test).
 * PeranFilter mencocokkan peran di session dengan tabel pengguna, jadi tabel itu harus ada
 * di database uji (SQLite memori). Dipakai bersama DatabaseTestTrait.
 */
trait AksesUjiTrait
{
    /** id akun uji per peran */
    private const AKUN_UJI = ['pemilik' => 901, 'penghuni' => 902, 'calon_penghuni' => 903];

    protected function siapkanAkunUji(): void
    {
        $tabel = $this->db->prefixTable('pengguna');
        $this->db->query('CREATE TABLE IF NOT EXISTS ' . $tabel
            . ' (id_pengguna INTEGER PRIMARY KEY, nama TEXT, username TEXT, password TEXT, no_hp TEXT, role TEXT)');

        foreach (self::AKUN_UJI as $peran => $id) {
            if ($this->db->table('pengguna')->where('id_pengguna', $id)->countAllResults() === 0) {
                $this->db->table('pengguna')->insert([
                    'id_pengguna' => $id, 'nama' => 'Uji ' . $peran, 'username' => 'uji' . $id,
                    'password' => 'x', 'no_hp' => '081200000000', 'role' => $peran,
                ]);
            }
        }
    }

    /** Jalankan permintaan berikutnya sebagai akun uji dengan peran ini. */
    protected function sebagai(string $peran): self
    {
        return $this->withSession([
            'logged_in'   => true,
            'role'        => $peran,
            'id_pengguna' => self::AKUN_UJI[$peran],
            'nama'        => 'Uji ' . $peran,
        ]);
    }
}
