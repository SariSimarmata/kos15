<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Katalog kamar kos.
 * Isi kamar hanya 4 kolom: nomor_kamar, fasilitas, harga_sewa_tahunan, status_kamar.
 */
class KamarModel extends Model
{
    protected $table            = 'kamar';
    protected $primaryKey       = 'id_kamar';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['nomor_kamar', 'fasilitas', 'harga_sewa_tahunan', 'status_kamar'];
    protected $useTimestamps    = false;

    /**
     * Aturan validasi form tambah/ubah kamar.
     * Kirim $idKamar saat ubah supaya nomor kamar milik sendiri tidak dianggap duplikat.
     */
    public function aturanValidasi(?int $idKamar = null): array
    {
        $unik = 'is_unique[kamar.nomor_kamar]';
        if ($idKamar !== null) {
            $unik = 'is_unique[kamar.nomor_kamar,id_kamar,' . $idKamar . ']';
        }

        return [
            'nomor_kamar' => [
                'label' => 'Nomor kamar',
                'rules' => 'required|max_length[10]|' . $unik,
                'errors' => ['is_unique' => 'Nomor kamar sudah dipakai kamar lain.'],
            ],
            'fasilitas' => [
                'label' => 'Fasilitas',
                'rules' => 'required',
            ],
            'harga_sewa_tahunan' => [
                'label' => 'Harga sewa tahunan',
                'rules' => 'required|decimal|greater_than[0]',
            ],
            'status_kamar' => [
                'label' => 'Status kamar',
                'rules' => 'required|in_list[kosong,terisi]',
            ],
        ];
    }

    /**
     * Daftar kamar untuk halaman pemilik, lengkap dengan jumlah media.
     */
    public function getDaftarPemilik(): array
    {
        return $this->select('kamar.*, COUNT(media_kamar.id_media) AS jumlah_media')
            ->join('media_kamar', 'media_kamar.id_kamar = kamar.id_kamar', 'left')
            ->groupBy('kamar.id_kamar')
            ->orderBy('kamar.nomor_kamar', 'ASC')
            ->findAll();
    }

    /**
     * KATALOG PUBLIK: daftar kamar + media pertama (id_media terkecil).
     * Kolom tambahan: media_pertama (nama file) dan jenis_media_pertama (foto/video), bisa null.
     *
     * @param string|null $status 'kosong' / 'terisi' untuk menyaring, null untuk semua.
     */
    public function getKatalog(?string $status = null): array
    {
        $builder = $this->select('kamar.*, m.file AS media_pertama, m.jenis AS jenis_media_pertama')
            ->join(
                'media_kamar m',
                'm.id_media = (SELECT MIN(mk.id_media) FROM media_kamar mk WHERE mk.id_kamar = kamar.id_kamar)',
                'left',
                false
            );

        if ($status !== null) {
            $builder->where('kamar.status_kamar', $status);
        }

        return $builder->orderBy('kamar.nomor_kamar', 'ASC')->findAll();
    }

    /**
     * KATALOG PUBLIK: detail satu kamar + semua medianya (key 'media').
     * Mengembalikan null kalau kamar tidak ditemukan.
     */
    public function getDetailKamar(int $idKamar): ?array
    {
        $kamar = $this->find($idKamar);
        if ($kamar === null) {
            return null;
        }

        $kamar['media'] = (new MediaKamarModel())->getByKamar($idKamar);

        return $kamar;
    }

    /**
     * Cek apakah kamar sudah punya pemesanan (dipakai sebelum hapus).
     */
    public function punyaPemesanan(int $idKamar): bool
    {
        return $this->db->table('pemesanan')
            ->where('id_kamar', $idKamar)
            ->countAllResults() > 0;
    }
}
