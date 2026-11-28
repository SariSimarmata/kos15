<?php

namespace App\Models;

use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Model;

/**
 * Model tabel `laporan_kerusakan`
 * Laporan kerusakan fasilitas oleh penghuni, diperbarui statusnya oleh pemilik.
 *
 * Tabel ini tidak punya kolom kamar. Kamar pelapor selalu diambil dari sewa aktifnya
 * (kamarAktif()), tidak pernah dari isian form, jadi penghuni tidak bisa melapor atas nama kamar lain.
 *
 * Nama pelapor hanya boleh tampil di halaman pemilik. Method bersama() dan milikPengguna()
 * sengaja tidak mengambil kolom id_pengguna sama sekali.
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

    /** Nilai ENUM status_perbaikan beserta labelnya. */
    public const STATUS = [
        'dilaporkan' => 'Dilaporkan',
        'diproses'   => 'Diproses',
        'selesai'    => 'Selesai',
    ];

    /** Warna lencana per status. */
    public const LENCANA = [
        'dilaporkan' => 'lencana-kuning',
        'diproses'   => 'lencana-biru',
        'selesai'    => 'lencana-hijau',
    ];

    /** Alur status: hanya maju satu langkah (dilaporkan, diproses, selesai). */
    public const ALUR = [
        'dilaporkan' => 'diproses',
        'diproses'   => 'selesai',
    ];

    public const DESKRIPSI_MIN = 10;
    public const DESKRIPSI_MAKS = 1000;
    public const CATATAN_MAKS = 1000;

    /** Folder fisik foto (relatif ke folder public). Database hanya menyimpan NAMA FILE. */
    public const FOLDER = 'uploads/kerusakan/';

    /** Foto yang diizinkan: tipe asli file (bukan nama), dan ukuran maksimal. */
    public const FOTO_MIME  = ['image/jpeg', 'image/png', 'image/webp'];
    public const FOTO_MAKS  = 2 * 1024 * 1024; // 2 MB
    public const FOTO_LABEL = 'JPG, PNG, atau WEBP, maksimal 2 MB';

    // ------------------------------------------------------------------
    //  Aturan status (murni, tanpa database)
    // ------------------------------------------------------------------

    /** Status berikutnya setelah $dari, atau null bila sudah selesai. */
    public static function statusBerikut(string $dari): ?string
    {
        return self::ALUR[$dari] ?? null;
    }

    /** Boleh pindah dari $dari ke $ke? Hanya satu langkah maju. */
    public static function bolehPindah(string $dari, string $ke): bool
    {
        return (self::ALUR[$dari] ?? null) === $ke;
    }

    // ------------------------------------------------------------------
    //  Foto
    // ------------------------------------------------------------------

    /**
     * Periksa foto unggahan. Mengembalikan pesan galat, atau null bila foto aman dipakai.
     * Yang dicek: ada atau tidaknya file, galat unggah, tipe ASLI file (dibaca dari isinya, bukan dari
     * nama atau tipe yang dikirim browser), ukuran, dan bahwa isinya benar-benar gambar.
     */
    public static function galatFoto(?UploadedFile $file, bool $wajib, string $label = 'Foto'): ?string
    {
        if ($file === null) {
            return self::galatBerkas(UPLOAD_ERR_NO_FILE, 0, null, null, $wajib, $label);
        }

        $kode = $file->getError();
        if ($kode !== UPLOAD_ERR_OK) {
            return self::galatBerkas($kode, 0, null, null, $wajib, $label);
        }

        return self::galatBerkas(
            $kode,
            (int) $file->getSize(),
            $file->getMimeType(),
            $file->getRealPath() ?: $file->getPathname(),
            $wajib,
            $label
        );
    }

    /**
     * Inti pemeriksaan foto, tanpa objek unggahan.
     *
     * @param int         $kode  kode galat unggah PHP (UPLOAD_ERR_*)
     * @param string|null $mime  tipe asli file (finfo)
     * @param string|null $path  lokasi file sementara
     */
    public static function galatBerkas(int $kode, int $ukuran, ?string $mime, ?string $path, bool $wajib, string $label = 'Foto'): ?string
    {
        if ($kode === UPLOAD_ERR_NO_FILE) {
            return $wajib ? $label . ' wajib diunggah.' : null;
        }

        if ($kode === UPLOAD_ERR_INI_SIZE || $kode === UPLOAD_ERR_FORM_SIZE) {
            return $label . ' terlalu besar. Maksimal 2 MB.';
        }

        if ($kode !== UPLOAD_ERR_OK || $path === null) {
            return $label . ' gagal diunggah. Coba lagi.';
        }

        if ($ukuran > self::FOTO_MAKS) {
            return $label . ' terlalu besar. Maksimal 2 MB.';
        }

        if (! in_array($mime, self::FOTO_MIME, true)) {
            return $label . ' harus berformat ' . self::FOTO_LABEL . '.';
        }

        if (@getimagesize($path) === false) {
            return $label . ' tidak terbaca sebagai gambar.';
        }

        return null;
    }

    /**
     * Pindahkan foto ke folder kerusakan dengan nama acak. Ekstensi mengikuti tipe asli file.
     * Mengembalikan nama file yang disimpan.
     */
    public static function simpanFoto(UploadedFile $file): string
    {
        $folder = FCPATH . self::FOLDER;
        if (! is_dir($folder)) {
            mkdir($folder, 0755, true);
        }

        $nama = $file->getRandomName();
        $file->move($folder, $nama);

        return $nama;
    }

    /** Hapus file fisik (aman bila file sudah tidak ada). Hanya nama file, tanpa folder. */
    public static function hapusFoto(?string $file): void
    {
        if ($file === null || $file === '') {
            return;
        }

        $path = FCPATH . self::FOLDER . basename($file);
        if (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * Alamat foto untuk ditampilkan, atau null bila file belum ada (view menampilkan kotak kosong).
     * Data contoh lama berisi "laporan/lap_001.jpg"; awalan folder dibuang.
     */
    public static function urlFoto(?string $file): ?string
    {
        if ($file === null || trim($file) === '') {
            return null;
        }

        $nama = basename(str_replace('\\', '/', trim($file)));
        if (! is_file(FCPATH . self::FOLDER . $nama)) {
            return null;
        }

        helper('url');

        return base_url(self::FOLDER . $nama);
    }

    // ------------------------------------------------------------------
    //  Kamar pelapor
    // ------------------------------------------------------------------

    /**
     * Kamar dari sewa AKTIF milik penghuni ini (yang terbaru bila ada beberapa), atau null.
     * Satu-satunya sumber kamar untuk laporan.
     *
     * @return array{id_kamar: int|string, nomor_kamar: string, id_sewa: int|string}|null
     */
    public function kamarAktif(int $idPengguna): ?array
    {
        return $this->db->table('sewa s')
            ->select('k.id_kamar, k.nomor_kamar, s.id_sewa')
            ->join('pemesanan p', 'p.id_pemesanan = s.id_pemesanan')
            ->join('kamar k', 'k.id_kamar = p.id_kamar')
            ->where('p.id_pengguna', $idPengguna)
            ->where('s.status_sewa', 'aktif')
            ->orderBy('s.id_sewa', 'DESC')
            ->limit(1)
            ->get()->getRowArray() ?: null;
    }

    // ------------------------------------------------------------------
    //  Daftar untuk penghuni (TANPA pelapor)
    // ------------------------------------------------------------------

    /** Kolom yang boleh dilihat semua penghuni. Jangan menambah id_pengguna atau nama ke sini. */
    private const KOLOM_PUBLIK = 'id_laporan, deskripsi, foto, tanggal_lapor, status_perbaikan, '
        . 'catatan_perbaikan, foto_perbaikan, tanggal_selesai';

    /** Halaman laporan bersama: semua laporan, tanpa kolom pelapor. Yang terbaru di atas. */
    public function bersama(): array
    {
        return $this->select(self::KOLOM_PUBLIK)
            ->orderBy('tanggal_lapor', 'DESC')
            ->orderBy('id_laporan', 'DESC')
            ->findAll();
    }

    /** Laporan milik satu penghuni (id hanya dipakai untuk menyaring, tidak ikut diambil). */
    public function milikPengguna(int $idPengguna): array
    {
        return $this->select(self::KOLOM_PUBLIK)
            ->where('id_pengguna', $idPengguna)
            ->orderBy('tanggal_lapor', 'DESC')
            ->orderBy('id_laporan', 'DESC')
            ->findAll();
    }

    // ------------------------------------------------------------------
    //  Untuk pemilik (dengan nama pelapor dan kamar)
    // ------------------------------------------------------------------

    private function dasarPemilik()
    {
        // Kamar = kamar dari sewa aktif pelapor. Kosong bila pelapor tidak lagi punya sewa aktif.
        return $this->db->table('laporan_kerusakan l')
            ->select('l.*, g.nama AS nama_pelapor, k.nomor_kamar')
            ->join('pengguna g', 'g.id_pengguna = l.id_pengguna')
            ->join(
                $this->db->prefixTable('pemesanan') . ' pm',
                'pm.id_pemesanan = (SELECT s2.id_pemesanan FROM ' . $this->db->prefixTable('sewa') . ' s2'
                . ' JOIN ' . $this->db->prefixTable('pemesanan') . ' p2 ON p2.id_pemesanan = s2.id_pemesanan'
                . " WHERE p2.id_pengguna = l.id_pengguna AND s2.status_sewa = 'aktif'"
                . ' ORDER BY s2.id_sewa DESC LIMIT 1)',
                'left',
                false
            )
            ->join('kamar k', 'k.id_kamar = pm.id_kamar', 'left');
    }

    /** Semua laporan untuk pemilik. $status kosong = semua status. */
    public function daftarPemilik(string $status = ''): array
    {
        $b = $this->dasarPemilik();
        if (array_key_exists($status, self::STATUS)) {
            $b->where('l.status_perbaikan', $status);
        }

        return $b->orderBy('l.tanggal_lapor', 'DESC')->orderBy('l.id_laporan', 'DESC')
            ->get()->getResultArray();
    }

    public function detailPemilik(int $id): ?array
    {
        return $this->dasarPemilik()->where('l.id_laporan', $id)->get()->getRowArray() ?: null;
    }

    /** Jumlah laporan per status, mis. ['dilaporkan' => 2, 'diproses' => 0, 'selesai' => 1]. */
    public function hitungPerStatus(): array
    {
        $hasil = array_fill_keys(array_keys(self::STATUS), 0);
        foreach ($this->select('status_perbaikan, COUNT(*) AS jumlah')->groupBy('status_perbaikan')->findAll() as $b) {
            $hasil[$b['status_perbaikan']] = (int) $b['jumlah'];
        }

        return $hasil;
    }

    /**
     * Ubah status satu langkah maju. Memakai syarat status lama pada UPDATE, jadi dua pemilik yang
     * menekan tombol bersamaan tidak membuat status melompat. Mengembalikan false bila status
     * sudah berubah oleh orang lain.
     *
     * @param array<string, mixed> $tambahan kolom lain (catatan_perbaikan, foto_perbaikan, tanggal_selesai)
     */
    public function pindahStatus(int $id, string $dari, string $ke, array $tambahan = []): bool
    {
        $data = array_intersect_key($tambahan, array_flip(['catatan_perbaikan', 'foto_perbaikan', 'tanggal_selesai']));
        $data['status_perbaikan'] = $ke;

        $this->builder()
            ->where('id_laporan', $id)
            ->where('status_perbaikan', $dari)
            ->update($data);

        return $this->db->affectedRows() === 1;
    }

    // ------------------------------------------------------------------
    //  Validasi form (dipakai $this->validate())
    // ------------------------------------------------------------------

    public static function aturanLaporan(): array
    {
        return [
            'deskripsi' => [
                'label' => 'Deskripsi',
                'rules' => 'required|min_length[' . self::DESKRIPSI_MIN . ']|max_length[' . self::DESKRIPSI_MAKS . ']',
            ],
        ];
    }

    public static function pesanLaporan(): array
    {
        return [
            'deskripsi' => [
                'required'   => 'Deskripsi kerusakan wajib diisi.',
                'min_length' => 'Deskripsi minimal ' . self::DESKRIPSI_MIN . ' karakter, jelaskan kerusakannya.',
                'max_length' => 'Deskripsi maksimal ' . self::DESKRIPSI_MAKS . ' karakter.',
            ],
        ];
    }

    /** Aturan form pemilik. Catatan wajib saat status baru adalah selesai. */
    public static function aturanStatus(string $statusBaru): array
    {
        return [
            'catatan_perbaikan' => [
                'label' => 'Catatan perbaikan',
                'rules' => ($statusBaru === 'selesai' ? 'required' : 'permit_empty') . '|max_length[' . self::CATATAN_MAKS . ']',
            ],
        ];
    }

    public static function pesanStatus(): array
    {
        return [
            'catatan_perbaikan' => [
                'required'   => 'Catatan perbaikan wajib diisi saat menandai laporan selesai.',
                'max_length' => 'Catatan maksimal ' . self::CATATAN_MAKS . ' karakter.',
            ],
        ];
    }
}
