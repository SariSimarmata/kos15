<?php

namespace App\Libraries;

use App\Models\NotifikasiModel;
use App\Models\PenggunaModel;
use App\Models\TagihanModel;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

/**
 * Satu-satunya tempat yang mengubah status pembayaran QRIS dari hasil gateway.
 * Dipakai oleh: PaymentCallback (Midtrans), DuitkuCallback, dan cek status di halaman pembayaran.
 *
 * Aturan (sama untuk semua sumber):
 *  - Baris pembayaran dikunci (SELECT ... FOR UPDATE) di dalam transaksi.
 *  - Sudah berhasil -> tidak mengubah apa pun, tidak mengirim notifikasi lagi (idempoten).
 *  - Nominal dari gateway harus sama dengan jumlah_bayar.
 *  - berhasil: pembayaran berhasil + tagihan lunas + notifikasi penghuni dan pemilik.
 *  - kedaluwarsa / gagal: hanya mengubah pembayaran yang masih menunggu.
 */
class PembayaranService
{
    /**
     * @param string|null $statusBaru berhasil | kedaluwarsa | gagal | null (tidak ada perubahan)
     * @param string      $sumber     untuk log, mis. "callback midtrans", "cek status duitku"
     *
     * @return array{kode: int, pesan: string}
     */
    public function terapkanStatus(
        string $orderId,
        ?string $statusBaru,
        float $nominal,
        ?string $idTransaksi,
        ?string $waktuBayar,
        string $sumber
    ): array {
        $db = db_connect();

        try {
            $db->transBegin();

            $bayar = $db->query('SELECT * FROM pembayaran WHERE order_id = ? FOR UPDATE', [$orderId])->getRowArray();

            if ($bayar === null) {
                $db->transRollback();
                $this->catatGagal($sumber, 'order_id tidak ditemukan', $orderId);

                return ['kode' => 404, 'pesan' => 'Pembayaran tidak ditemukan.'];
            }

            if ((int) round($nominal) !== (int) round((float) $bayar['jumlah_bayar'])) {
                $db->transRollback();
                $this->catatGagal($sumber, 'nominal ' . $nominal . ' berbeda dengan jumlah_bayar ' . $bayar['jumlah_bayar'], $orderId);

                return ['kode' => 400, 'pesan' => 'Nominal tidak sesuai.'];
            }

            if ($bayar['status_pembayaran'] === 'berhasil') {
                $db->transCommit();
                log_message('info', 'Status QRIS ganda diabaikan ({sumber}), order_id {order}.', ['sumber' => $sumber, 'order' => $orderId]);

                return ['kode' => 200, 'pesan' => 'Sudah diproses sebelumnya.'];
            }

            if ($statusBaru === null || $statusBaru === $bayar['status_pembayaran']) {
                $db->transCommit();

                return ['kode' => 200, 'pesan' => 'Tidak ada perubahan.'];
            }

            if ($statusBaru === 'berhasil') {
                // Settlement boleh menimpa kedaluwarsa/gagal: uangnya sudah masuk
                $this->lunasi($db, $bayar, $idTransaksi, $waktuBayar);
            } elseif ($bayar['status_pembayaran'] === 'menunggu') {
                $this->harusBerhasil($db->table('pembayaran')
                    ->where('id_pembayaran', $bayar['id_pembayaran'])
                    ->where('status_pembayaran', 'menunggu')
                    ->update(['status_pembayaran' => $statusBaru]), 'ubah status pembayaran');
            }

            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi gagal di tengah jalan.');
            }
            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();
            $this->catatGagal($sumber, 'error saat memproses: ' . $e->getMessage(), $orderId);

            return ['kode' => 500, 'pesan' => 'Gagal memproses callback.'];
        }

        return ['kode' => 200, 'pesan' => 'OK'];
    }

    // ------------------------------------------------------------------

    private function lunasi($db, array $bayar, ?string $idTransaksi, ?string $waktuBayar): void
    {
        helper('kos');

        $this->harusBerhasil($db->table('pembayaran')
            ->where('id_pembayaran', $bayar['id_pembayaran'])
            ->where('status_pembayaran !=', 'berhasil')
            ->update([
                'status_pembayaran'    => 'berhasil',
                'tanggal_bayar'        => $this->waktuWib($waktuBayar),
                'id_transaksi_gateway' => ($idTransaksi !== null && $idTransaksi !== '') ? $idTransaksi : $bayar['id_transaksi_gateway'],
            ]), 'ubah pembayaran menjadi berhasil');

        $this->harusBerhasil($db->table('tagihan')
            ->where('id_tagihan', $bayar['id_tagihan'])
            ->where('status_tagihan', 'belum_bayar')
            ->update(['status_tagihan' => 'lunas']), 'ubah tagihan menjadi lunas');

        if ($db->affectedRows() !== 1) {
            log_message('warning', 'QRIS {order} dibayar padahal tagihan {id} sudah lunas. Cek pengembalian dana.', [
                'order' => $bayar['order_id'],
                'id'    => $bayar['id_tagihan'],
            ]);
        }

        // Tagihan sudah lunas: kode QRIS lain untuk tagihan yang sama tidak dipakai lagi.
        // Jika ternyata tetap dibayar, callback-nya masuk ke log peringatan di atas (cek pengembalian dana).
        $db->table('pembayaran')
            ->where('id_tagihan', $bayar['id_tagihan'])
            ->where('id_pembayaran !=', $bayar['id_pembayaran'])
            ->where('status_pembayaran', 'menunggu')
            ->update(['status_pembayaran' => 'kedaluwarsa']);

        // Sewa baru menjadi aktif setelah DP dan pelunasan lunas
        $idSewa = (int) $db->table('tagihan')->select('id_sewa')->where('id_tagihan', $bayar['id_tagihan'])->get()->getRow('id_sewa');
        $sisaAwal = $db->table('tagihan')->where('id_sewa', $idSewa)
            ->whereIn('jenis_tagihan', ['dp', 'pelunasan'])->where('status_tagihan', 'belum_bayar')->countAllResults();
        if ($idSewa > 0 && $sisaAwal === 0) {
            $this->harusBerhasil($db->table('sewa')->where('id_sewa', $idSewa)
                ->where('status_sewa', 'menunggu_pembayaran')->update(['status_sewa' => 'aktif']), 'aktifkan sewa');
        }

        $tagihan = $db->query(
            'SELECT t.id_tagihan, t.jenis_tagihan, p.id_pengguna, g.nama, k.nomor_kamar
               FROM tagihan t
               JOIN sewa s       ON s.id_sewa = t.id_sewa
               JOIN pemesanan p  ON p.id_pemesanan = s.id_pemesanan
               JOIN pengguna g   ON g.id_pengguna = p.id_pengguna
               JOIN kamar k      ON k.id_kamar = p.id_kamar
              WHERE t.id_tagihan = ?',
            [$bayar['id_tagihan']]
        )->getRowArray();

        if ($tagihan === null) {
            throw new RuntimeException('Data tagihan ' . $bayar['id_tagihan'] . ' tidak lengkap.');
        }

        $jenis     = TagihanModel::LABEL_JENIS[$tagihan['jenis_tagihan']] ?? $tagihan['jenis_tagihan'];
        $nominal   = rupiah($bayar['jumlah_bayar']);
        $idTagihan = (int) $tagihan['id_tagihan'];
        $notif     = model(NotifikasiModel::class);

        $notif->kirim(
            (int) $tagihan['id_pengguna'],
            'pembayaran',
            'Pembayaran QRIS ' . $nominal . ' untuk tagihan ' . $jenis . ' berhasil. Tagihan sudah lunas.',
            $idTagihan
        );

        $pesanPemilik = $tagihan['nama'] . ' (Kamar ' . $tagihan['nomor_kamar'] . ') membayar tagihan '
            . $jenis . ' ' . $nominal . ' lewat QRIS.';
        foreach (model(PenggunaModel::class)->where('role', 'pemilik')->findAll() as $pemilik) {
            $notif->kirim((int) $pemilik['id_pengguna'], 'pembayaran', $pesanPemilik, $idTagihan);
        }
    }

    /** Waktu bayar dari gateway (WIB), atau waktu sekarang jika tidak ada / tidak terbaca. */
    private function waktuWib(?string $waktu): string
    {
        $zona = new DateTimeZone(QrisGateway::ZONA_WAKTU);

        try {
            if ($waktu !== null && $waktu !== '') {
                return (new DateTimeImmutable($waktu, $zona))->format('Y-m-d H:i:s');
            }
        } catch (Throwable) {
            // format tak dikenal, pakai waktu sekarang
        }

        return (new DateTimeImmutable('now', $zona))->format('Y-m-d H:i:s');
    }

    private function catatGagal(string $sumber, string $alasan, string $orderId): void
    {
        log_message('error', 'Callback QRIS gagal ({sumber}): {alasan}. order_id={order}', [
            'sumber' => $sumber,
            'alasan' => $alasan,
            'order'  => $orderId,
        ]);
    }

    /** Di production DBDebug mati: query gagal hanya mengembalikan false, jadi dicek manual. */
    private function harusBerhasil($hasil, string $langkah): void
    {
        if ($hasil === false) {
            throw new RuntimeException('Langkah gagal: ' . $langkah);
        }
    }
}
