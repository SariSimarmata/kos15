<?php

namespace App\Models;

use CodeIgniter\Model;
use DateTime;

/**
 * Model tabel `sewa`
 * Masa sewa satu tahun.
 */
class SewaModel extends Model
{
    protected $table            = 'sewa';
    protected $primaryKey       = 'id_sewa';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id_pemesanan',
        'tanggal_mulai',
        'tanggal_berakhir',
        'status_sewa',
    ];

    // Tabel tidak memiliki kolom created_at / updated_at
    protected $useTimestamps = false;

    /** Nilai ENUM status_sewa beserta labelnya. */
    public const STATUS = [
        'menunggu_pembayaran' => 'Menunggu pembayaran',
        'aktif'               => 'Aktif',
        'selesai'             => 'Selesai',
        'batal'               => 'Batal',
    ];

    /** Status sewa yang masih berjalan: kamar dianggap terisi oleh penyewa ini. */
    public const STATUS_BERJALAN = ['menunggu_pembayaran', 'aktif'];

    /**
     * Tanggal berakhir sewa satu tahun.
     * Sama dengan perhitungan Pemesanan::setujui(): tanggal mulai + 1 tahun.
     */
    public static function tanggalBerakhir(string $tanggalMulai): string
    {
        return (new DateTime($tanggalMulai))->modify('+1 year')->format('Y-m-d');
    }

    public static function berjalan(?string $status): bool
    {
        return in_array($status, self::STATUS_BERJALAN, true);
    }

    /**
     * SQL id sewa terakhir milik pengguna dari tabel luar `p`.
     * Sewa yang masih berjalan didahulukan, lalu yang paling baru.
     */
    private function subquerySewaTerakhir(): string
    {
        return '(SELECT s2.id_sewa FROM sewa s2'
            . ' JOIN pemesanan m2 ON m2.id_pemesanan = s2.id_pemesanan'
            . ' WHERE m2.id_pengguna = p.id_pengguna'
            . " ORDER BY CASE WHEN s2.status_sewa IN ('menunggu_pembayaran','aktif') THEN 0 ELSE 1 END,"
            . ' s2.tanggal_mulai DESC, s2.id_sewa DESC LIMIT 1)';
    }

    /**
     * Daftar penghuni beserta kamar dan sewa terakhirnya (KF-03).
     * $cari mencocokkan nama, username, nomor HP, atau nomor kamar.
     */
    public function daftarPenghuni(?string $cari = null): array
    {
        $builder = $this->db->table('pengguna p')
            ->select('p.id_pengguna, p.nama, p.username, p.no_hp,'
                . ' s.id_sewa, s.tanggal_mulai, s.tanggal_berakhir, s.status_sewa,'
                . ' k.id_kamar, k.nomor_kamar')
            ->join('sewa s', 's.id_sewa = ' . $this->subquerySewaTerakhir(), 'left', false)
            ->join('pemesanan m', 'm.id_pemesanan = s.id_pemesanan', 'left')
            ->join('kamar k', 'k.id_kamar = m.id_kamar', 'left')
            ->where('p.role', 'penghuni');

        $cari = trim((string) $cari);
        if ($cari !== '') {
            $builder->groupStart()
                ->like('p.nama', $cari)
                ->orLike('p.username', $cari)
                ->orLike('p.no_hp', $cari)
                ->orLike('k.nomor_kamar', $cari)
                ->groupEnd();
        }

        return $builder->orderBy('k.nomor_kamar', 'ASC')
            ->orderBy('p.nama', 'ASC')
            ->get()->getResultArray();
    }

    /** Satu penghuni beserta sewa terakhirnya, atau null jika bukan penghuni. */
    public function penghuni(int $idPengguna): ?array
    {
        $baris = $this->db->table('pengguna p')
            ->select('p.id_pengguna, p.nama, p.username, p.no_hp,'
                . ' s.id_sewa, s.id_pemesanan, s.tanggal_mulai, s.tanggal_berakhir, s.status_sewa,'
                . ' k.id_kamar, k.nomor_kamar, k.fasilitas, k.harga_sewa_tahunan')
            ->join('sewa s', 's.id_sewa = ' . $this->subquerySewaTerakhir(), 'left', false)
            ->join('pemesanan m', 'm.id_pemesanan = s.id_pemesanan', 'left')
            ->join('kamar k', 'k.id_kamar = m.id_kamar', 'left')
            ->where('p.role', 'penghuni')
            ->where('p.id_pengguna', $idPengguna)
            ->get()->getRowArray();

        return $baris ?: null;
    }

    /** Semua sewa milik pengguna, terbaru di atas (untuk halaman masa sewa penghuni). */
    public function riwayatMilik(int $idPengguna): array
    {
        return $this->db->table('sewa s')
            ->select('s.*, k.nomor_kamar')
            ->join('pemesanan m', 'm.id_pemesanan = s.id_pemesanan')
            ->join('kamar k', 'k.id_kamar = m.id_kamar')
            ->where('m.id_pengguna', $idPengguna)
            ->orderBy('s.tanggal_mulai', 'DESC')
            ->orderBy('s.id_sewa', 'DESC')
            ->get()->getResultArray();
    }

    /**
     * Alasan penghuni tidak boleh dihapus, atau null jika boleh.
     * Ditolak jika masih ada sewa berjalan atau tagihan belum lunas.
     */
    public function alasanTidakBisaHapus(int $idPengguna): ?string
    {
        $sewaBerjalan = $this->db->table('sewa s')
            ->select('s.tanggal_berakhir, k.nomor_kamar')
            ->join('pemesanan m', 'm.id_pemesanan = s.id_pemesanan')
            ->join('kamar k', 'k.id_kamar = m.id_kamar')
            ->where('m.id_pengguna', $idPengguna)
            ->whereIn('s.status_sewa', self::STATUS_BERJALAN)
            ->get()->getRowArray();

        if ($sewaBerjalan) {
            helper('tanggal');

            return 'masih punya sewa aktif di kamar ' . $sewaBerjalan['nomor_kamar']
                . ' sampai ' . tanggal_indonesia($sewaBerjalan['tanggal_berakhir'])
                . '. Ubah status sewanya menjadi selesai atau batal lebih dulu.';
        }

        $tunggakan = $this->db->table('tagihan t')
            ->select('COUNT(*) AS jumlah, SUM(t.jumlah_tagihan) AS total')
            ->join('sewa s', 's.id_sewa = t.id_sewa')
            ->join('pemesanan m', 'm.id_pemesanan = s.id_pemesanan')
            ->where('m.id_pengguna', $idPengguna)
            ->where('t.status_tagihan', 'belum_bayar')
            ->get()->getRowArray();

        if ((int) ($tunggakan['jumlah'] ?? 0) > 0) {
            return 'masih punya ' . (int) $tunggakan['jumlah'] . ' tagihan belum lunas sebesar Rp '
                . number_format((float) $tunggakan['total'], 0, ',', '.') . '.';
        }

        return null;
    }
}
