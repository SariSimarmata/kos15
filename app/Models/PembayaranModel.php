<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model tabel `pembayaran`
 * Pembayaran QRIS (gateway) atau tunai.
 */
class PembayaranModel extends Model
{
    protected $table            = 'pembayaran';
    protected $primaryKey       = 'id_pembayaran';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id_tagihan',
        'id_konfirmator',
        'metode',
        'order_id',
        'id_transaksi_gateway',
        'qris_url',
        'waktu_kedaluwarsa',
        'tanggal_bayar',
        'jumlah_bayar',
        'status_pembayaran',
    ];

    // Tabel tidak memiliki kolom created_at / updated_at
    protected $useTimestamps = false;

    // ------------------------------------------------------------------
    //  Pembayaran QRIS (kartu pembayaran QRIS + callback)
    // ------------------------------------------------------------------

    /**
     * order_id unik untuk Midtrans (maks. 50 karakter), contoh: KOS-20261127-0005-A1B2C3
     * Bagian acak membuat order_id tetap unik walau satu tagihan dibuatkan QRIS berkali-kali.
     */
    public static function buatOrderId(int $idTagihan, string $tanggalYmd, string $awalan = 'KOS'): string
    {
        // Awalan menandai gateway pembuatnya: KOS = Midtrans, KOSD = Duitku (lihat PaymentGateway)
        return $awalan . '-' . $tanggalYmd . '-' . str_pad((string) $idTagihan, 4, '0', STR_PAD_LEFT)
            . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    /** Pembayaran lengkap dengan tagihan dan pemilik tagihan (id_pengguna penghuni). */
    public function detailLengkap(int $idPembayaran): ?array
    {
        return $this->select('pembayaran.*, tagihan.jenis_tagihan, tagihan.jumlah_tagihan, '
                . 'tagihan.tanggal_jatuh_tempo, tagihan.status_tagihan, pemesanan.id_pengguna, kamar.nomor_kamar')
            ->join('tagihan', 'tagihan.id_tagihan = pembayaran.id_tagihan')
            ->join('sewa', 'sewa.id_sewa = tagihan.id_sewa')
            ->join('pemesanan', 'pemesanan.id_pemesanan = sewa.id_pemesanan')
            ->join('kamar', 'kamar.id_kamar = pemesanan.id_kamar')
            ->where('pembayaran.id_pembayaran', $idPembayaran)
            ->first();
    }

    /**
     * QRIS yang masih bisa dipakai untuk satu tagihan (menunggu dan belum lewat waktu).
     * Dipakai supaya klik "Bayar" dua kali tidak membuat dua kode QRIS.
     */
    public function qrisAktif(int $idTagihan, string $sekarangWib): ?array
    {
        return $this->where('id_tagihan', $idTagihan)
            ->where('metode', 'qris')
            ->where('status_pembayaran', 'menunggu')
            ->where('waktu_kedaluwarsa >', $sekarangWib)
            ->orderBy('id_pembayaran', 'DESC')
            ->first();
    }

    /**
     * Tandai QRIS yang sudah lewat waktu sebagai kedaluwarsa.
     * Hanya mengubah yang masih "menunggu", jadi aman jika callback datang bersamaan.
     *
     * @return bool true jika baris ini baru saja diubah
     */
    public function tandaiKedaluwarsa(int $idPembayaran, string $sekarangWib): bool
    {
        $this->builder()
            ->where('id_pembayaran', $idPembayaran)
            ->where('status_pembayaran', 'menunggu')
            ->where('waktu_kedaluwarsa <=', $sekarangWib)
            ->update(['status_pembayaran' => 'kedaluwarsa']);

        return $this->db->affectedRows() === 1;
    }

    /**
     * QRIS milik penghuni yang waktunya sudah habis tapi masih "menunggu" (halamannya tidak dibuka lagi)
     * ditandai kedaluwarsa, supaya Tagihan dan Riwayat tidak menampilkan "Menunggu" yang sudah basi.
     * Callback lunas yang datang belakangan tetap diproses PembayaranService.
     */
    public function kedaluwarsakanMilikPenghuni(int $idPengguna, string $sekarangWib): int
    {
        $this->db->query(
            'UPDATE pembayaran b
               JOIN tagihan t   ON t.id_tagihan = b.id_tagihan
               JOIN sewa s      ON s.id_sewa = t.id_sewa
               JOIN pemesanan p ON p.id_pemesanan = s.id_pemesanan
                SET b.status_pembayaran = \'kedaluwarsa\'
              WHERE p.id_pengguna = ? AND b.metode = \'qris\'
                AND b.status_pembayaran = \'menunggu\' AND b.waktu_kedaluwarsa <= ?',
            [$idPengguna, $sekarangWib]
        );

        return $this->db->affectedRows();
    }

    /**
     * Riwayat pembayaran milik penghuni (halaman Riwayat Bayar).
     *
     * @param string|null $cari   kata kunci: jenis tagihan, metode, atau nomor order
     * @param string|null $status berhasil | menunggu | kedaluwarsa | gagal
     */
    public function riwayatPenghuni(int $idPengguna, ?string $cari = null, ?string $status = null): array
    {
        $b = $this->select('pembayaran.*, tagihan.jenis_tagihan, tagihan.jumlah_tagihan, kamar.nomor_kamar')
            ->join('tagihan', 'tagihan.id_tagihan = pembayaran.id_tagihan')
            ->join('sewa', 'sewa.id_sewa = tagihan.id_sewa')
            ->join('pemesanan', 'pemesanan.id_pemesanan = sewa.id_pemesanan')
            ->join('kamar', 'kamar.id_kamar = pemesanan.id_kamar')
            ->where('pemesanan.id_pengguna', $idPengguna);

        if (in_array($status, ['berhasil', 'menunggu', 'kedaluwarsa', 'gagal'], true)) {
            $b->where('pembayaran.status_pembayaran', $status);
        }

        $cari = trim((string) $cari);
        if ($cari !== '') {
            $jenis = [];
            foreach (TagihanModel::LABEL_JENIS as $kode => $label) {
                if (mb_stripos($label, $cari) !== false) {
                    $jenis[] = $kode;
                }
            }
            $b->groupStart()
                ->like('pembayaran.metode', $cari)
                ->orLike('pembayaran.order_id', $cari)
                ->orLike('tagihan.jenis_tagihan', $cari);
            if ($jenis !== []) {
                $b->orWhereIn('tagihan.jenis_tagihan', $jenis);
            }
            $b->groupEnd();
        }

        return $b->orderBy('COALESCE(pembayaran.tanggal_bayar, pembayaran.waktu_kedaluwarsa)', 'DESC', false)
            ->orderBy('pembayaran.id_pembayaran', 'DESC')
            ->findAll();
    }
}
