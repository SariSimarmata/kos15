<?php

namespace App\Controllers\Penghuni;

use App\Controllers\BaseController;
use App\Libraries\PaymentGateway;
use App\Libraries\PembayaranService;
use App\Libraries\QrisGateway;
use App\Models\PembayaranModel;
use App\Models\TagihanModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Kartu pembayaran QRIS: penghuni memilih QRIS pada tagihan, sistem meminta kode QRIS
 * dinamis ke Midtrans, lalu halaman memantau status sampai lunas atau kedaluwarsa.
 *
 * Login dan peran penghuni dijaga filter 'peran:penghuni' di Routes.php.
 * Kepemilikan tagihan dicek di sini: penghuni hanya boleh membayar tagihannya sendiri.
 *
 * Perubahan status menjadi lunas dilakukan PembayaranService: dari callback gateway, atau dari
 * cek status ke gateway saat halaman ini menunggu (cadangan jika callback tidak sampai).
 */
class Pembayaran extends BaseController
{
    protected $helpers = ['tanggal', 'kos'];

    /**
     * GET penghuni/bayar : menu "Bayar Tagihan". Buka tagihan belum bayar yang paling dekat jatuh tempo.
     * Tagihan sewa dibuat di halaman Tagihan (Kartu 3) setelah penghuni memilih skema.
     */
    public function mulai()
    {
        $sekarang = $this->sekarang()->format('Y-m-d H:i:s');
        model(PembayaranModel::class)->kedaluwarsakanMilikPenghuni((int) session('id_pengguna'), $sekarang);

        foreach (model(TagihanModel::class)->daftarPenghuni((int) session('id_pengguna'), $sekarang) as $t) {
            if ($t['status_tagihan'] === 'belum_bayar') {
                return redirect()->to('penghuni/tagihan/' . $t['id_tagihan'] . '/bayar');
            }
        }

        return redirect()->to('penghuni/tagihan')->with('sukses', 'Tidak ada tagihan yang perlu dibayar.');
    }

    /** GET penghuni/tagihan/{id}/bayar : ringkasan tagihan + pilihan metode QRIS. */
    public function pilih(int $idTagihan)
    {
        $tagihan = $this->tagihanMilikSaya($idTagihan);
        if ($tagihan === null) {
            return $this->aksesDitolak();
        }

        $aktif = null;
        if ($tagihan['status_tagihan'] === 'belum_bayar') {
            $aktif = model(PembayaranModel::class)->qrisAktif($idTagihan, $this->sekarang()->format('Y-m-d H:i:s'));
        }

        // Bagian "1. Pilih Tagihan": semua tagihan belum bayar milik penghuni ini
        $belumBayar = array_values(array_filter(
            model(TagihanModel::class)->daftarPenghuni((int) session('id_pengguna'), $this->sekarang()->format('Y-m-d H:i:s')),
            static fn (array $t): bool => $t['status_tagihan'] === 'belum_bayar'
        ));

        return view('pembayaran/pilih', [
            'judul'       => 'Bayar Tagihan',
            'tagihan'     => $tagihan,
            'belumBayar'  => $belumBayar,
            'qrisAktif'   => $aktif,
            'gatewaySiap' => PaymentGateway::aktif()->siap(),
        ]);
    }

    /** POST penghuni/tagihan/{id}/qris : minta kode QRIS ke Midtrans, simpan berstatus menunggu. */
    public function buatQris(int $idTagihan)
    {
        $tagihan = $this->tagihanMilikSaya($idTagihan);
        if ($tagihan === null) {
            return $this->aksesDitolak();
        }

        $kembali = 'penghuni/tagihan/' . $idTagihan . '/bayar';

        if ($tagihan['status_tagihan'] === 'lunas') {
            return redirect()->to($kembali)->with('gagal', 'Tagihan ini sudah lunas.');
        }

        $gateway = PaymentGateway::aktif();
        if (! $gateway->siap()) {
            log_message('error', 'QRIS: kunci {gateway} di .env belum diisi, kode QRIS tidak bisa dibuat.', ['gateway' => $gateway->nama()]);

            return redirect()->to($kembali)
                ->with('gagal', 'Pembayaran QRIS belum aktif. Hubungi pemilik kos.');
        }

        $model    = model(PembayaranModel::class);
        $sekarang = $this->sekarang();
        $db       = db_connect();

        try {
            $db->transBegin();

            // Kunci baris tagihan: dua klik bersamaan tidak membuat dua kode QRIS
            $db->query('SELECT id_tagihan FROM tagihan WHERE id_tagihan = ? FOR UPDATE', [$idTagihan]);

            // Masih ada QRIS yang berlaku? Pakai lagi, jangan buat baru.
            $aktif = $model->qrisAktif($idTagihan, $sekarang->format('Y-m-d H:i:s'));
            if ($aktif !== null) {
                $db->transCommit();

                return redirect()->to('penghuni/pembayaran/' . $aktif['id_pembayaran']);
            }

            // QRIS DINAMIS: nominal dari database, bukan dari input penghuni
            $nominal = (int) round((float) $tagihan['jumlah_tagihan']);
            $orderId = PembayaranModel::buatOrderId($idTagihan, $sekarang->format('Ymd'), PaymentGateway::awalan($gateway));

            $hasil = $gateway->buatQris($orderId, $nominal, [
                'first_name' => (string) session('nama'),
                'keterangan' => 'Tagihan ' . (TagihanModel::LABEL_JENIS[$tagihan['jenis_tagihan']] ?? $tagihan['jenis_tagihan'])
                    . ' Kamar ' . $tagihan['nomor_kamar'],
                'return_url' => site_url($kembali),
            ]);

            $tersimpan = $model->insert([
                'id_tagihan'           => $idTagihan,
                'metode'               => 'qris',
                'order_id'             => $orderId,
                'id_transaksi_gateway' => $hasil['id_transaksi'],
                'qris_url'             => $hasil['qris_url'],
                'waktu_kedaluwarsa'    => $hasil['waktu_kedaluwarsa'],
                'jumlah_bayar'         => $nominal,
                'status_pembayaran'    => 'menunggu',
            ]);
            if ($tersimpan === false) {
                throw new \RuntimeException('Gagal menyimpan pembayaran: ' . implode(', ', $model->errors()));
            }
            $idPembayaran = (int) $model->getInsertID();

            $db->transCommit();

            // Duitku mengirim isi QR (qrString), bukan gambar. Isinya terlalu panjang untuk kolom qris_url,
            // jadi disimpan di cache selama QR berlaku dan digambar di halaman (public/js/qrcode-generator.js).
            if (! empty($hasil['qr_string'])) {
                cache()->save($this->kunciQr($orderId), $hasil['qr_string'], (QrisGateway::MENIT_KEDALUWARSA + 5) * 60);
            }
        } catch (Throwable $e) {
            $db->transRollback(); // aman walau transaksi belum/tidak aktif
            log_message('error', 'QRIS: gagal membuat kode untuk tagihan {id}: {pesan}', [
                'id'    => $idTagihan,
                'pesan' => $e->getMessage(),
            ]);

            return redirect()->to($kembali)
                ->with('gagal', 'Kode QRIS belum bisa dibuat. Coba lagi beberapa saat.');
        }

        return redirect()->to('penghuni/pembayaran/' . $idPembayaran);
    }

    /** GET penghuni/pembayaran/{id} : halaman kode QRIS + status yang diperbarui otomatis. */
    public function qris(int $idPembayaran)
    {
        $bayar = $this->pembayaranMilikSaya($idPembayaran);
        if ($bayar === null) {
            return $this->aksesDitolak();
        }

        $bayar = $this->perbaruiDariGateway($bayar);
        $bayar = $this->perbaruiKedaluwarsa($bayar);

        return view('pembayaran/qris', [
            'judul'     => 'Pembayaran QRIS',
            'bayar'     => $bayar,
            'sisaDetik' => $this->sisaDetik($bayar),
            'qrString'  => cache($this->kunciQr($bayar['order_id'])),
        ]);
    }

    /** GET penghuni/pembayaran/{id}/status : JSON untuk polling AJAX tiap 3 detik. */
    public function status(int $idPembayaran): ResponseInterface
    {
        $bayar = $this->pembayaranMilikSaya($idPembayaran);
        if ($bayar === null) {
            return $this->response->setStatusCode(403)->setJSON(['pesan' => 'Akses ditolak.']);
        }

        $bayar = $this->perbaruiDariGateway($bayar);
        $bayar = $this->perbaruiKedaluwarsa($bayar);

        return $this->response
            ->setHeader('Cache-Control', 'no-store')
            ->setJSON([
                'status_pembayaran' => $bayar['status_pembayaran'],
                'status_tagihan'    => $bayar['status_tagihan'],
                'tanggal_bayar'     => $bayar['tanggal_bayar'],
                'sisa_detik'        => $this->sisaDetik($bayar),
            ]);
    }

    // ------------------------------------------------------------------
    //  Pembantu
    // ------------------------------------------------------------------

    private function tagihanMilikSaya(int $idTagihan): ?array
    {
        return model(TagihanModel::class)->milikPenghuni($idTagihan, (int) session('id_pengguna'));
    }

    private function pembayaranMilikSaya(int $idPembayaran): ?array
    {
        $bayar = model(PembayaranModel::class)->detailLengkap($idPembayaran);

        if ($bayar === null || $bayar['metode'] !== 'qris'
            || (int) $bayar['id_pengguna'] !== (int) session('id_pengguna')) {
            return null;
        }

        return $bayar;
    }

    /**
     * Jika waktu QRIS habis tapi callback "expire" belum datang (mis. ngrok mati),
     * status tetap berubah menjadi kedaluwarsa. Callback settlement yang datang
     * belakangan tetap bisa melunasi (lihat PaymentCallback).
     */
    private function perbaruiKedaluwarsa(array $bayar): array
    {
        if ($bayar['status_pembayaran'] === 'menunggu' && $this->sisaDetik($bayar) === 0) {
            model(PembayaranModel::class)->tandaiKedaluwarsa(
                (int) $bayar['id_pembayaran'],
                $this->sekarang()->format('Y-m-d H:i:s')
            );

            return model(PembayaranModel::class)->detailLengkap((int) $bayar['id_pembayaran']) ?? $bayar;
        }

        return $bayar;
    }

    /**
     * Cadangan jika callback tidak sampai (mis. tanpa ngrok): tanya status langsung ke gateway,
     * paling sering sekali per 10 detik untuk satu pembayaran. Hasilnya diproses lewat
     * PembayaranService, jadi aturan idempoten dan notifikasinya sama dengan callback.
     */
    private function perbaruiDariGateway(array $bayar): array
    {
        if ($bayar['status_pembayaran'] !== 'menunggu' || empty($bayar['order_id'])) {
            return $bayar;
        }

        $kunci = 'cek_status_' . (int) $bayar['id_pembayaran'];
        if (cache($kunci) !== null) {
            return $bayar;
        }
        cache()->save($kunci, 1, 10);

        $gateway = PaymentGateway::untukOrder($bayar['order_id']);
        $hasil   = $gateway->cekStatus($bayar['order_id']);
        if ($hasil === null || $hasil['status'] === null) {
            return $bayar;
        }

        (new PembayaranService())->terapkanStatus(
            $bayar['order_id'],
            $hasil['status'],
            $hasil['nominal'],
            $hasil['id_transaksi'],
            $hasil['waktu_bayar'],
            'cek status ' . $gateway->nama()
        );

        return model(PembayaranModel::class)->detailLengkap((int) $bayar['id_pembayaran']) ?? $bayar;
    }

    private function kunciQr(string $orderId): string
    {
        return 'qris_' . md5($orderId);
    }

    private function sisaDetik(array $bayar): int
    {
        if ($bayar['status_pembayaran'] !== 'menunggu' || empty($bayar['waktu_kedaluwarsa'])) {
            return 0;
        }

        $batas = new DateTimeImmutable($bayar['waktu_kedaluwarsa'], new DateTimeZone(QrisGateway::ZONA_WAKTU));

        return max(0, $batas->getTimestamp() - $this->sekarang()->getTimestamp());
    }

    /** waktu_kedaluwarsa disimpan dalam WIB, jadi pembanding juga WIB (appTimezone = UTC). */
    private function sekarang(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone(QrisGateway::ZONA_WAKTU));
    }

    private function aksesDitolak(): ResponseInterface
    {
        return $this->response->setStatusCode(403)->setBody(view('errors/akses_ditolak'));
    }
}
