<?php

namespace App\Libraries;

use App\Models\NotifikasiModel;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Tagihan awal sewa: DP dan pelunasan (kartu tagihan & pembayaran).
 *
 * Mengikuti pola data contoh kos15.sql:
 *   - DP 50% harga sewa tahunan, jatuh tempo 3 hari setelah pemesanan disetujui
 *   - Pelunasan sisanya, jatuh tempo sehari sebelum tanggal mulai sewa
 * Sewa menjadi "aktif" setelah DP dan pelunasan lunas (lihat PembayaranService).
 */
class TagihanService
{
    public const PERSEN_DP        = 50;
    public const HARI_JATUH_TEMPO = 3;

    /**
     * Buat tagihan DP + pelunasan untuk satu sewa, jika belum ada. Aman dipanggil berkali-kali.
     *
     * @return int jumlah tagihan yang baru dibuat (0 jika sudah ada)
     */
    public function siapkanTagihanAwal(int $idSewa): int
    {
        $db = db_connect();

        $sudahAda = $db->table('tagihan')
            ->where('id_sewa', $idSewa)
            ->whereIn('jenis_tagihan', ['dp', 'pelunasan'])
            ->countAllResults();
        if ($sudahAda > 0) {
            return 0;
        }

        $sewa = $db->query(
            'SELECT s.id_sewa, s.tanggal_mulai, s.status_sewa, k.harga_sewa_tahunan, k.nomor_kamar, p.id_pengguna
               FROM sewa s
               JOIN pemesanan p ON p.id_pemesanan = s.id_pemesanan
               JOIN kamar k     ON k.id_kamar = p.id_kamar
              WHERE s.id_sewa = ?',
            [$idSewa]
        )->getRowArray();

        if ($sewa === null || $sewa['status_sewa'] !== 'menunggu_pembayaran') {
            return 0;
        }

        $zona     = new DateTimeZone(QrisGateway::ZONA_WAKTU);
        $hariIni  = new DateTimeImmutable('today', $zona);
        $mulai    = new DateTimeImmutable($sewa['tanggal_mulai'], $zona);
        $harga    = (float) $sewa['harga_sewa_tahunan'];
        $dp       = round($harga * self::PERSEN_DP / 100);
        $sisa     = $harga - $dp;

        // DP: 3 hari lagi, tapi tidak melewati hari sebelum mulai sewa (dan tidak sebelum hari ini)
        $sebelumMulai = $mulai->modify('-1 day');
        $tempoDp      = $hariIni->modify('+' . self::HARI_JATUH_TEMPO . ' days');
        if ($tempoDp > $sebelumMulai) {
            $tempoDp = max($hariIni, $sebelumMulai);
        }
        $tempoLunas = max($tempoDp, $sebelumMulai);

        $db->table('tagihan')->insertBatch([
            [
                'id_sewa'             => $idSewa,
                'jenis_tagihan'       => 'dp',
                'jumlah_tagihan'      => $dp,
                'tanggal_jatuh_tempo' => $tempoDp->format('Y-m-d'),
                'status_tagihan'      => 'belum_bayar',
            ],
            [
                'id_sewa'             => $idSewa,
                'jenis_tagihan'       => 'pelunasan',
                'jumlah_tagihan'      => $sisa,
                'tanggal_jatuh_tempo' => $tempoLunas->format('Y-m-d'),
                'status_tagihan'      => 'belum_bayar',
            ],
        ]);

        $idDp = (int) $db->table('tagihan')->select('id_tagihan')
            ->where('id_sewa', $idSewa)->where('jenis_tagihan', 'dp')
            ->get()->getRow('id_tagihan');

        helper(['kos', 'tanggal']);
        model(NotifikasiModel::class)->kirim(
            (int) $sewa['id_pengguna'],
            'tagihan',
            'Tagihan DP sewa Kamar ' . $sewa['nomor_kamar'] . ' ' . rupiah($dp) . ' terbit, jatuh tempo '
                . tanggal_indonesia($tempoDp->format('Y-m-d')) . '. Pelunasan ' . rupiah($sisa)
                . ' jatuh tempo ' . tanggal_indonesia($tempoLunas->format('Y-m-d')) . '.',
            $idDp ?: null
        );

        return 2;
    }

    /**
     * Sewa yang menunggu pembayaran milik penghuni ini tapi belum punya tagihan awal
     * (mis. disetujui sebelum fitur ini ada). Dipanggil saat halaman Tagihan dibuka.
     */
    public function lengkapiUntukPenghuni(int $idPengguna): void
    {
        $daftar = db_connect()->query(
            'SELECT s.id_sewa FROM sewa s
               JOIN pemesanan p ON p.id_pemesanan = s.id_pemesanan
              WHERE p.id_pengguna = ? AND s.status_sewa = ?',
            [$idPengguna, 'menunggu_pembayaran']
        )->getResultArray();

        foreach ($daftar as $s) {
            $this->siapkanTagihanAwal((int) $s['id_sewa']);
        }
    }
}
