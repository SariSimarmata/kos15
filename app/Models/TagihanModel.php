<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model tabel `tagihan`
 * Semua jenis tagihan (DP, pelunasan, listrik_air, denda_piket, menginap_pengunjung).
 */
class TagihanModel extends Model
{
    protected $table            = 'tagihan';
    protected $primaryKey       = 'id_tagihan';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id_sewa',
        'jenis_tagihan',
        'jumlah_tagihan',
        'tanggal_jatuh_tempo',
        'status_tagihan',
    ];

    // Tabel tidak memiliki kolom created_at / updated_at
    protected $useTimestamps = false;

    /** Label jenis tagihan untuk tampilan. */
    public const LABEL_JENIS = [
        'dp'                  => 'DP Sewa',
        'pelunasan'           => 'Pelunasan Sewa',
        'listrik_air'         => 'Listrik & Air',
        'denda_piket'         => 'Denda Piket',
        'menginap_pengunjung' => 'Menginap Pengunjung',
    ];

    /**
     * Satu tagihan, hanya jika milik penghuni ini (tagihan -> sewa -> pemesanan -> pengguna).
     * null berarti tagihan tidak ada ATAU milik orang lain (dipakai untuk hak akses).
     */
    public function milikPenghuni(int $idTagihan, int $idPengguna): ?array
    {
        return $this->select('tagihan.*, kamar.nomor_kamar, pemesanan.id_pengguna')
            ->join('sewa', 'sewa.id_sewa = tagihan.id_sewa')
            ->join('pemesanan', 'pemesanan.id_pemesanan = sewa.id_pemesanan')
            ->join('kamar', 'kamar.id_kamar = pemesanan.id_kamar')
            ->where('tagihan.id_tagihan', $idTagihan)
            ->where('pemesanan.id_pengguna', $idPengguna)
            ->first();
    }

    /**
     * Semua tagihan milik penghuni (halaman Tagihan), terbaru dulu, lengkap dengan:
     * kamar, periode (tagihan_bulanan / masa sewa / tanggal piket), dan QRIS yang masih berlaku.
     */
    public function daftarPenghuni(int $idPengguna, string $sekarangWib): array
    {
        return $this->db->query(
            'SELECT t.*, k.nomor_kamar, s.tanggal_mulai, s.tanggal_berakhir,
                    tb.periode, pk.tanggal_piket,
                    (SELECT b.id_pembayaran FROM pembayaran b
                      WHERE b.id_tagihan = t.id_tagihan AND b.metode = \'qris\'
                        AND b.status_pembayaran = \'menunggu\' AND b.waktu_kedaluwarsa > ?
                      ORDER BY b.id_pembayaran DESC LIMIT 1) AS id_qris_aktif,
                    (SELECT b.id_pembayaran FROM pembayaran b
                      WHERE b.id_tagihan = t.id_tagihan AND b.status_pembayaran = \'berhasil\'
                      ORDER BY b.id_pembayaran DESC LIMIT 1) AS id_bayar_berhasil
               FROM tagihan t
               JOIN sewa s       ON s.id_sewa = t.id_sewa
               JOIN pemesanan p  ON p.id_pemesanan = s.id_pemesanan
               JOIN kamar k      ON k.id_kamar = p.id_kamar
               LEFT JOIN tagihan_bulanan tb ON tb.id_tagihan = t.id_tagihan
               LEFT JOIN piket pk           ON pk.id_tagihan = t.id_tagihan
              WHERE p.id_pengguna = ?
              ORDER BY (t.status_tagihan = \'lunas\'), t.tanggal_jatuh_tempo ASC, t.id_tagihan ASC',
            [$sekarangWib, $idPengguna]
        )->getResultArray();
    }

    /** Teks kolom Periode untuk satu baris dari daftarPenghuni()/daftarPemilik(). */
    public static function periode(array $t): string
    {
        helper('tanggal');

        if (! empty($t['periode'])) {
            // periode tagihan_bulanan berformat YYYY-MM, mis. 2026-10 -> "Okt 2026"
            return preg_replace('/^\d+ /', '', tanggal_indonesia($t['periode'] . '-01'));
        }
        if (in_array($t['jenis_tagihan'], ['dp', 'pelunasan'], true) && ! empty($t['tanggal_mulai'])) {
            return tanggal_indonesia($t['tanggal_mulai']) . ' – ' . tanggal_indonesia($t['tanggal_berakhir']);
        }
        if (! empty($t['tanggal_piket'])) {
            return 'Piket ' . tanggal_indonesia($t['tanggal_piket']);
        }

        return '–';
    }

    /** Semua tagihan untuk pemilik (halaman Tagihan pemilik), dengan filter status. */
    public function daftarPemilik(?string $status, string $sekarangWib): array
    {
        $filter = in_array($status, ['belum_bayar', 'lunas'], true) ? ' AND t.status_tagihan = ' . $this->db->escape($status) : '';

        return $this->db->query(
            'SELECT t.*, k.nomor_kamar, g.nama, s.tanggal_mulai, s.tanggal_berakhir, tb.periode, pk.tanggal_piket,
                    (SELECT b.id_pembayaran FROM pembayaran b
                      WHERE b.id_tagihan = t.id_tagihan AND b.metode = \'qris\'
                        AND b.status_pembayaran = \'menunggu\' AND b.waktu_kedaluwarsa > ?
                      ORDER BY b.id_pembayaran DESC LIMIT 1) AS id_qris_aktif,
                    (SELECT CONCAT(b.metode, \'|\', COALESCE(b.tanggal_bayar, \'\')) FROM pembayaran b
                      WHERE b.id_tagihan = t.id_tagihan AND b.status_pembayaran = \'berhasil\'
                      ORDER BY b.id_pembayaran DESC LIMIT 1) AS bayar_berhasil
               FROM tagihan t
               JOIN sewa s       ON s.id_sewa = t.id_sewa
               JOIN pemesanan p  ON p.id_pemesanan = s.id_pemesanan
               JOIN pengguna g   ON g.id_pengguna = p.id_pengguna
               JOIN kamar k      ON k.id_kamar = p.id_kamar
               LEFT JOIN tagihan_bulanan tb ON tb.id_tagihan = t.id_tagihan
               LEFT JOIN piket pk           ON pk.id_tagihan = t.id_tagihan
              WHERE 1 = 1' . $filter . '
              ORDER BY (t.status_tagihan = \'lunas\'), t.tanggal_jatuh_tempo ASC, t.id_tagihan ASC',
            [$sekarangWib]
        )->getResultArray();
    }
}
