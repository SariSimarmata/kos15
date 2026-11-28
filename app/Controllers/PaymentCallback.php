<?php

namespace App\Controllers;

use App\Libraries\PembayaranService;
use App\Libraries\QrisGateway;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * Menerima notifikasi (callback / webhook) dari Midtrans: POST /pembayaran/callback
 *
 * Route ini TIDAK memakai filter login dan DIKECUALIKAN dari CSRF (lihat Config/Filters.php),
 * karena yang memanggil adalah server Midtrans, bukan browser penghuni.
 * Sebagai gantinya, setiap callback wajib lolos cek signature.
 *
 *  1. Baca JSON. Tidak valid -> 400, dicatat di log.
 *  2. Cek signature. Salah -> 403, dicatat di log, TIDAK ada data yang diubah.
 *  3. Sisanya (kunci baris, idempoten, lunas, notifikasi) di PembayaranService.
 */
class PaymentCallback extends BaseController
{
    public function index(): ResponseInterface
    {
        $payload = $this->bacaPayload();
        if ($payload === null) {
            $this->catatGagal('payload bukan JSON yang valid', []);

            return $this->balas(400, 'Payload tidak valid.');
        }

        // Signature dulu, sebelum menyentuh database
        if (! (new QrisGateway())->signatureValid($payload)) {
            $this->catatGagal('signature tidak cocok', $payload);

            return $this->balas(403, 'Signature tidak valid.');
        }

        $hasil = (new PembayaranService())->terapkanStatus(
            (string) $payload['order_id'],
            QrisGateway::petakanStatus(
                (string) ($payload['transaction_status'] ?? ''),
                isset($payload['fraud_status']) ? (string) $payload['fraud_status'] : null
            ),
            (float) $payload['gross_amount'],
            isset($payload['transaction_id']) ? (string) $payload['transaction_id'] : null,
            isset($payload['settlement_time']) ? (string) $payload['settlement_time'] : null,
            'callback midtrans'
        );

        // 500 membuat Midtrans mengirim ulang callback nanti
        return $this->balas($hasil['kode'], $hasil['pesan']);
    }

    private function bacaPayload(): ?array
    {
        try {
            $data = $this->request->getJSON(true);
        } catch (Throwable) {
            $data = null;
        }

        // Cadangan jika dikirim sebagai form (mis. uji manual dari Postman)
        if (! is_array($data) || $data === []) {
            $data = $this->request->getPost();
        }

        return (is_array($data) && $data !== []) ? $data : null;
    }

    /** Callback yang gagal dicatat di writable/logs (tanpa signature_key). */
    private function catatGagal(string $alasan, array $payload): void
    {
        log_message('error', 'Callback QRIS gagal: {alasan}. order_id={order} status={status} ip={ip}', [
            'alasan' => $alasan,
            'order'  => $payload['order_id'] ?? '-',
            'status' => $payload['transaction_status'] ?? '-',
            'ip'     => $this->request->getIPAddress(),
        ]);
    }

    private function balas(int $kode, string $pesan): ResponseInterface
    {
        return $this->response->setStatusCode($kode)->setJSON(['pesan' => $pesan]);
    }
}
