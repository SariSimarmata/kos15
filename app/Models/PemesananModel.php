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

    /** Nomor pemesanan sebagai bukti booking, contoh: PSN-00012 (Kartu 8). */
    public static function nomor(int $idPemesanan): string
    {
        return 'PSN-' . str_pad((string) $idPemesanan, 5, '0', STR_PAD_LEFT);
    }

    /** Pemesanan milik satu calon penghuni, terbaru dulu, lengkap dengan nomor kamar. */
    public function milikPengguna(int $idPengguna): array
    {
        return $this->select('pemesanan.*, kamar.nomor_kamar, kamar.harga_sewa_tahunan')
            ->join('kamar', 'kamar.id_kamar = pemesanan.id_kamar')
            ->where('pemesanan.id_pengguna', $idPengguna)
            ->orderBy('pemesanan.tanggal_pengajuan', 'DESC')
            ->orderBy('pemesanan.id_pemesanan', 'DESC')
            ->findAll();
    }

    /** Pemesanan berstatus pending untuk halaman pemilik, yang paling lama menunggu di atas. */
    public function daftarPending(): array
    {
        return $this->select('pemesanan.*, pengguna.nama AS nama_calon, pengguna.no_hp, '
                . 'kamar.nomor_kamar, kamar.status_kamar')
            ->join('pengguna', 'pengguna.id_pengguna = pemesanan.id_pengguna')
            ->join('kamar', 'kamar.id_kamar = pemesanan.id_kamar')
            ->where('pemesanan.status_pengajuan', 'pending')
            ->orderBy('pemesanan.tanggal_pengajuan', 'ASC')
            ->orderBy('pemesanan.id_pemesanan', 'ASC')
            ->findAll();
    }
}
