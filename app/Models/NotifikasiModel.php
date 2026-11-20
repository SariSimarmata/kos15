<?php

namespace App\Models;

use CodeIgniter\Model;

use InvalidArgumentException;

/**
 * Model tabel `notifikasi`
 * Notifikasi di dalam website.
 */
class NotifikasiModel extends Model
{
    protected $table            = 'notifikasi';
    protected $primaryKey       = 'id_notifikasi';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id_pengguna',
        'id_tagihan',
        'id_laporan',
        'jenis_notifikasi',
        'pesan',
        'waktu_kirim',
        'status_baca',
    ];

    // Tabel tidak memiliki kolom created_at / updated_at
    protected $useTimestamps = false;

    /** Jenis notifikasi yang diizinkan (sama dengan ENUM jenis_notifikasi). */
    public const JENIS = [
        'tagihan', 'denda', 'pembayaran', 'aduan',
        'pemesanan', 'piket', 'perpanjangan', 'meteran',
    ];

    /** Jenis yang boleh membawa id_tagihan. */
    private const JENIS_TAGIHAN = ['tagihan', 'denda', 'pembayaran'];

    /** Jenis yang boleh membawa id_laporan. */
    private const JENIS_LAPORAN = ['aduan'];

    /**
     * Mengirim (menyimpan) satu notifikasi ke pengguna.
     *
     * Contoh:
     *   model(NotifikasiModel::class)->kirim(4, 'tagihan', 'Tagihan jatuh tempo H-3', 5);
     *   model(NotifikasiModel::class)->kirim(1, 'aduan', 'Laporan kerusakan baru', null, 2);
     *   model(NotifikasiModel::class)->kirim(5, 'pemesanan', 'Pemesanan kamu disetujui');
     *
     * @param int      $idPengguna Penerima notifikasi
     * @param string   $jenis      Salah satu dari NotifikasiModel::JENIS
     * @param string   $pesan      Isi notifikasi
     * @param int|null $idTagihan  Opsional, hanya untuk jenis tagihan, denda, pembayaran
     * @param int|null $idLaporan  Opsional, hanya untuk jenis aduan
     *
     * @return int ID notifikasi yang baru dibuat
     *
     * @throws InvalidArgumentException Jika jenis, pesan, atau pasangan ID tidak sesuai
     */
    public function kirim(
        int $idPengguna,
        string $jenis,
        string $pesan,
        ?int $idTagihan = null,
        ?int $idLaporan = null
    ): int {
        if (! in_array($jenis, self::JENIS, true)) {
            throw new InvalidArgumentException('Jenis notifikasi tidak valid: ' . $jenis);
        }

        $pesan = trim($pesan);
        if ($pesan === '') {
            throw new InvalidArgumentException('Pesan notifikasi tidak boleh kosong.');
        }

        if ($idTagihan !== null && ! in_array($jenis, self::JENIS_TAGIHAN, true)) {
            throw new InvalidArgumentException('id_tagihan hanya untuk notifikasi tagihan, denda, atau pembayaran.');
        }

        if ($idLaporan !== null && ! in_array($jenis, self::JENIS_LAPORAN, true)) {
            throw new InvalidArgumentException('id_laporan hanya untuk notifikasi aduan.');
        }

        $this->insert([
            'id_pengguna'      => $idPengguna,
            'id_tagihan'       => $idTagihan,
            'id_laporan'       => $idLaporan,
            'jenis_notifikasi' => $jenis,
            'pesan'            => $pesan,
            'waktu_kirim'      => date('Y-m-d H:i:s'),
            'status_baca'      => 0,
        ]);

        return (int) $this->getInsertID();
    }
}
