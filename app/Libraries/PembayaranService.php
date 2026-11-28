<?php

namespace App\Libraries;

use App\Models\NotifikasiModel;
use App\Models\PembayaranModel;
use App\Models\PenggunaModel;
use App\Models\TagihanModel;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use RuntimeException;
use Throwable;

/**
 * Satu-satunya tempat yang mengubah status pembayaran dan melunasi tagihan.
 *
 * QRIS : terapkanStatus(), dipakai PaymentCallback (Midtrans), DuitkuCallback, dan cek status di halaman pembayaran.
 * Tunai (Kartu 6): ajukanTunai(), konfirmasiTunai(), tolakTunai(), dipakai Penghuni\PembayaranTunai dan Pemilik\KonfirmasiTunai.
 *
 * Tagihan hanya menjadi lunas lewat lunasi() di bawah, baik dari QRIS maupun tunai, jadi aturan
 * "satu tagihan tidak lunas dua kali" dan notifikasinya ada di satu tempat.
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
    //  Pembayaran tunai (Kartu 6)
    //  Penghuni mengajukan -> pemilik mengonfirmasi atau menolak.
    //  Kesalahan aturan bisnis dilempar sebagai DomainException (pesannya aman ditampilkan ke pengguna);
    //  kesalahan lain dilempar apa adanya dan dicatat di controller.
    // ------------------------------------------------------------------

    /**
     * Penghuni mengajukan pembayaran tunai: pembayaran berstatus menunggu, pemilik diberi notifikasi.
     * Tagihan sengaja TIDAK diubah, jadi tetap belum_bayar sampai pemilik mengonfirmasi.
     *
     * tanggal_bayar diisi waktu pengajuan supaya Riwayat punya tanggal. Saat dikonfirmasi diganti waktu konfirmasi.
     * order_id memakai awalan TUNAI sebagai nomor bukti (kolomnya unik dan boleh kosong untuk tunai, tapi
     * Riwayat dan Kwitansi menampilkannya sebagai "No. transaksi").
     *
     * @return int id_pembayaran yang baru dibuat
     *
     * @throws DomainException tagihan bukan milik penghuni, sudah lunas, atau masih ada pembayaran yang menunggu
     */
    public function ajukanTunai(int $idTagihan, int $idPenghuni): int
    {
        $db = db_connect();
        $db->transBegin();

        try {
            // Kunci baris tagihan: dua klik bersamaan tidak membuat dua pengajuan (sama dengan Pembayaran::buatQris)
            $db->query('SELECT id_tagihan FROM tagihan WHERE id_tagihan = ? FOR UPDATE', [$idTagihan]);

            // Pesan sama untuk "tidak ada" dan "milik orang lain", supaya penghuni tidak bisa menebak tagihan orang lain
            $tagihan = model(TagihanModel::class)->milikPenghuni($idTagihan, $idPenghuni);
            if ($tagihan === null) {
                throw new DomainException('Tagihan tidak ditemukan.');
            }
            if ($tagihan['status_tagihan'] === 'lunas') {
                throw new DomainException('Tagihan ini sudah lunas.');
            }

            // Cegah pengajuan kedua: ada tunai yang menunggu, atau QRIS yang kodenya masih berlaku
            $sekarang = NotifikasiModel::sekarangWib();
            $menunggu = $db->table('pembayaran')
                ->select('metode')
                ->where('id_tagihan', $idTagihan)
                ->where('status_pembayaran', 'menunggu')
                ->groupStart()
                    ->where('metode', 'tunai')
                    ->orGroupStart()
                        ->where('metode', 'qris')
                        ->where('waktu_kedaluwarsa >', $sekarang)
                    ->groupEnd()
                ->groupEnd()
                ->get()->getRowArray();

            if ($menunggu !== null) {
                throw new DomainException($menunggu['metode'] === 'tunai'
                    ? 'Pembayaran tunai untuk tagihan ini masih menunggu konfirmasi pemilik.'
                    : 'Tagihan ini masih punya kode QRIS yang berlaku. Selesaikan pembayaran QRIS atau tunggu sampai kedaluwarsa.');
            }

            $nominal = (int) round((float) $tagihan['jumlah_tagihan']);
            $model   = model(PembayaranModel::class);

            $tersimpan = $model->insert([
                'id_tagihan'        => $idTagihan,
                'metode'            => 'tunai',
                'order_id'          => PembayaranModel::buatOrderId($idTagihan, str_replace('-', '', substr($sekarang, 0, 10)), 'TUNAI'),
                'tanggal_bayar'     => $sekarang,
                'jumlah_bayar'      => $nominal,
                'status_pembayaran' => 'menunggu',
            ]);
            if ($tersimpan === false) {
                throw new RuntimeException('Gagal menyimpan pembayaran tunai: ' . implode(', ', $model->errors()));
            }
            $idPembayaran = (int) $model->getInsertID();

            $penghuni = model(PenggunaModel::class)->find($idPenghuni);
            (new NotifikasiService())->pembayaranTunai(
                $idTagihan,
                (string) ($penghuni['nama'] ?? 'Penghuni'),
                (string) $tagihan['nomor_kamar'],
                $nominal
            );

            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi pengajuan tunai gagal di tengah jalan.');
            }
            $db->transCommit();

            return $idPembayaran;
        } catch (Throwable $e) {
            $db->transRollback();

            throw $e;
        }
    }

    /**
     * Pemilik mengonfirmasi uang tunai sudah diterima.
     * Tagihan dilunasi lewat lunasi(), method yang sama dengan QRIS, dan id_konfirmator tercatat.
     *
     * @throws DomainException pembayaran tidak ada, bukan tunai, sudah diproses, atau tagihannya sudah lunas
     */
    public function konfirmasiTunai(int $idPembayaran, int $idPemilik): void
    {
        $db = db_connect();
        $db->transBegin();

        try {
            $bayar = $this->kunciTunaiMenunggu($db, $idPembayaran);

            // Tagihan yang sudah lunas (mis. lewat QRIS) tidak boleh dilunasi lagi.
            // Biasanya pengajuan ini sudah otomatis kedaluwarsa oleh lunasi() QRIS, ini jaga-jaga jika berbarengan.
            $statusTagihan = $db->query('SELECT status_tagihan FROM tagihan WHERE id_tagihan = ? FOR UPDATE', [$bayar['id_tagihan']])->getRow('status_tagihan');
            if ($statusTagihan === 'lunas') {
                throw new DomainException('Tagihan ini sudah lunas.');
            }

            $this->lunasi($db, $bayar, null, null, $idPemilik);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi konfirmasi tunai gagal di tengah jalan.');
            }
            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();

            throw $e;
        }
    }

    /**
     * Pemilik menolak pengajuan tunai (uang belum diterima).
     * Pembayaran menjadi gagal dan id_konfirmator tercatat. Tagihan tidak pernah diubah saat pengajuan,
     * jadi otomatis tetap belum_bayar dan penghuni boleh mengajukan lagi atau memakai QRIS.
     * Alasan (opsional) tidak punya kolom sendiri, jadi dikirim lewat notifikasi ke penghuni.
     *
     * @throws DomainException pembayaran tidak ada, bukan tunai, atau sudah diproses
     */
    public function tolakTunai(int $idPembayaran, int $idPemilik, ?string $alasan = null): void
    {
        helper('kos');

        $db = db_connect();
        $db->transBegin();

        try {
            $this->kunciTunaiMenunggu($db, $idPembayaran);

            $this->harusBerhasil($db->table('pembayaran')
                ->where('id_pembayaran', $idPembayaran)
                ->where('status_pembayaran', 'menunggu')
                ->update([
                    'status_pembayaran' => 'gagal',
                    'id_konfirmator'    => $idPemilik,
                ]), 'tolak pembayaran tunai');

            $detail = model(PembayaranModel::class)->detailLengkap($idPembayaran);
            if ($detail === null) {
                throw new RuntimeException('Data pembayaran ' . $idPembayaran . ' tidak lengkap.');
            }

            $alasan = trim((string) $alasan);
            $jenis  = TagihanModel::LABEL_JENIS[$detail['jenis_tagihan']] ?? $detail['jenis_tagihan'];

            model(NotifikasiModel::class)->kirim(
                (int) $detail['id_pengguna'],
                'pembayaran',
                'Pembayaran tunai ' . rupiah($detail['jumlah_bayar']) . ' untuk tagihan ' . $jenis . ' ditolak pemilik.'
                    . ($alasan !== '' ? ' Alasan: ' . $alasan . '.' : '')
                    . ' Tagihan tetap belum dibayar.',
                (int) $detail['id_tagihan']
            );

            if ($db->transStatus() === false) {
                throw new RuntimeException('Transaksi penolakan tunai gagal di tengah jalan.');
            }
            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();

            throw $e;
        }
    }

    /** Kunci baris pembayaran tunai yang masih menunggu. Dipanggil di dalam transaksi. */
    private function kunciTunaiMenunggu($db, int $idPembayaran): array
    {
        $bayar = $db->query('SELECT * FROM pembayaran WHERE id_pembayaran = ? FOR UPDATE', [$idPembayaran])->getRowArray();

        if ($bayar === null || $bayar['metode'] !== 'tunai') {
            throw new DomainException('Pembayaran tunai tidak ditemukan.');
        }
        if ($bayar['status_pembayaran'] !== 'menunggu') {
            throw new DomainException('Pembayaran ini sudah diproses.');
        }

        return $bayar;
    }

    // ------------------------------------------------------------------

    /**
     * Pembayaran berhasil + tagihan lunas + sewa aktif + notifikasi.
     * $idKonfirmator hanya diisi untuk pembayaran tunai (pemilik yang mengonfirmasi).
     */
    private function lunasi($db, array $bayar, ?string $idTransaksi, ?string $waktuBayar, ?int $idKonfirmator = null): void
    {
        helper('kos');

        $ubah = [
            'status_pembayaran'    => 'berhasil',
            'tanggal_bayar'        => $this->waktuWib($waktuBayar),
            'id_transaksi_gateway' => ($idTransaksi !== null && $idTransaksi !== '') ? $idTransaksi : $bayar['id_transaksi_gateway'],
        ];
        if ($idKonfirmator !== null) {
            $ubah['id_konfirmator'] = $idKonfirmator;
        }

        $this->harusBerhasil($db->table('pembayaran')
            ->where('id_pembayaran', $bayar['id_pembayaran'])
            ->where('status_pembayaran !=', 'berhasil')
            ->update($ubah), 'ubah pembayaran menjadi berhasil');

        $this->harusBerhasil($db->table('tagihan')
            ->where('id_tagihan', $bayar['id_tagihan'])
            ->where('status_tagihan', 'belum_bayar')
            ->update(['status_tagihan' => 'lunas']), 'ubah tagihan menjadi lunas');

        if ($db->affectedRows() !== 1) {
            log_message('warning', 'Pembayaran {order} berhasil padahal tagihan {id} sudah lunas. Cek pengembalian dana.', [
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
        $tunai     = $bayar['metode'] === 'tunai';
        $notif     = model(NotifikasiModel::class);

        $notif->kirim(
            (int) $tagihan['id_pengguna'],
            'pembayaran',
            $tunai
                ? 'Pembayaran tunai ' . $nominal . ' untuk tagihan ' . $jenis . ' sudah dikonfirmasi pemilik. Tagihan sudah lunas.'
                : 'Pembayaran QRIS ' . $nominal . ' untuk tagihan ' . $jenis . ' berhasil. Tagihan sudah lunas.',
            $idTagihan
        );

        $pesanPemilik = $tagihan['nama'] . ' (Kamar ' . $tagihan['nomor_kamar'] . ') membayar tagihan '
            . $jenis . ' ' . $nominal . ($tunai ? ' secara tunai.' : ' lewat QRIS.');
        foreach (model(PenggunaModel::class)->where('role', 'pemilik')->findAll() as $pemilik) {
            // Pemilik yang baru saja mengonfirmasi tidak perlu diberi tahu lagi
            if ($idKonfirmator !== null && (int) $pemilik['id_pengguna'] === $idKonfirmator) {
                continue;
            }
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
