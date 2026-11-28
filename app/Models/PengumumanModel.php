<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model tabel `pengumuman`
 * Pengumuman dan peraturan kos (kolom `jenis` membedakan keduanya).
 */
class PengumumanModel extends Model
{
    protected $table            = 'pengumuman';
    protected $primaryKey       = 'id_pengumuman';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'jenis',
        'isi',
        'tanggal_terbit',
    ];

    // Tabel tidak memiliki kolom created_at / updated_at
    protected $useTimestamps = false;

    /** Nilai ENUM jenis beserta labelnya. */
    public const JENIS = [
        'pengumuman' => 'Pengumuman',
        'peraturan'  => 'Peraturan',
    ];

    /** Batas panjang isi (kolom TEXT muat 65.535 byte, dibatasi agar tampilan tetap rapi). */
    public const ISI_MIN = 5;
    public const ISI_MAKS = 2000;

    /**
     * Peraturan kos (jenis = 'peraturan'), urut dari yang paling lama terbit.
     * Dipakai halaman detail kamar (Kartu 7) dan halaman pengumuman.
     */
    public function peraturan(): array
    {
        return $this->where('jenis', 'peraturan')
            ->orderBy('tanggal_terbit', 'ASC')
            ->orderBy('id_pengumuman', 'ASC')
            ->findAll();
    }

    /** Pengumuman (jenis = 'pengumuman'), yang terbaru di atas. */
    public function pengumuman(): array
    {
        return $this->where('jenis', 'pengumuman')
            ->orderBy('tanggal_terbit', 'DESC')
            ->orderBy('id_pengumuman', 'DESC')
            ->findAll();
    }

    /**
     * Daftar untuk halaman pemilik. $jenis kosong = semua jenis.
     * Yang terbaru di atas.
     */
    public function daftar(string $jenis = ''): array
    {
        if (array_key_exists($jenis, self::JENIS)) {
            $this->where('jenis', $jenis);
        }

        return $this->orderBy('tanggal_terbit', 'DESC')
            ->orderBy('id_pengumuman', 'DESC')
            ->findAll();
    }

    /** Jumlah per jenis, mis. ['pengumuman' => 3, 'peraturan' => 2]. */
    public function hitungPerJenis(): array
    {
        $hasil = array_fill_keys(array_keys(self::JENIS), 0);
        $baris = $this->select('jenis, COUNT(*) AS jumlah')->groupBy('jenis')->findAll();
        foreach ($baris as $b) {
            $hasil[$b['jenis']] = (int) $b['jumlah'];
        }

        return $hasil;
    }

    public static function aturan(): array
    {
        return [
            'jenis' => ['label' => 'Jenis', 'rules' => 'required|in_list[' . implode(',', array_keys(self::JENIS)) . ']'],
            'isi'   => [
                'label' => 'Isi',
                'rules' => 'required|min_length[' . self::ISI_MIN . ']|max_length[' . self::ISI_MAKS . ']',
            ],
        ];
    }

    public static function pesanAturan(): array
    {
        return [
            'jenis' => [
                'required' => 'Pilih jenis: pengumuman atau peraturan.',
                'in_list'  => 'Jenis harus pengumuman atau peraturan.',
            ],
            'isi'   => [
                'required'   => 'Isi wajib diisi.',
                'min_length' => 'Isi minimal ' . self::ISI_MIN . ' karakter.',
                'max_length' => 'Isi maksimal ' . self::ISI_MAKS . ' karakter.',
            ],
        ];
    }
}
