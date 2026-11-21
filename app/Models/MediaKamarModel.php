<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Foto dan video kamar. Database hanya menyimpan NAMA FILE.
 * File fisiknya ada di public/uploads/kamar/.
 * Di view, tampilkan dengan: base_url('uploads/kamar/' . $file)
 */
class MediaKamarModel extends Model
{
    protected $table            = 'media_kamar';
    protected $primaryKey       = 'id_media';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['id_kamar', 'jenis', 'file'];
    protected $useTimestamps    = false;

    /** Folder fisik penyimpanan file (relatif ke folder public). */
    public const FOLDER = 'uploads/kamar/';

    /** Tipe file yang diizinkan per jenis, beserta batas ukuran (byte). */
    public const ATURAN = [
        'foto' => [
            'mime'  => ['image/jpeg', 'image/png', 'image/webp'],
            'maks'  => 2 * 1024 * 1024,   // 2 MB
            'label' => 'JPG, PNG, WEBP maks 2 MB',
        ],
        'video' => [
            'mime'  => ['video/mp4', 'video/webm'],
            'maks'  => 20 * 1024 * 1024,  // 20 MB
            'label' => 'MP4, WEBM maks 20 MB',
        ],
    ];

    /** Semua media milik satu kamar, urut dari yang paling awal diunggah. */
    public function getByKamar(int $idKamar): array
    {
        return $this->where('id_kamar', $idKamar)
            ->orderBy('id_media', 'ASC')
            ->findAll();
    }

    /** Path lengkap file di disk. */
    public static function path(string $file): string
    {
        return FCPATH . self::FOLDER . $file;
    }

    /** Hapus file fisik dari disk (aman kalau file sudah tidak ada). */
    public static function hapusFile(string $file): void
    {
        $path = self::path($file);
        if (is_file($path)) {
            unlink($path);
        }
    }
}
