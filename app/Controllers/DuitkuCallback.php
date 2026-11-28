<?php

namespace App\Controllers;

use App\Libraries\DuitkuGateway;
use App\Libraries\PembayaranService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Menerima callback dari Duitku: POST /pembayaran/callback-duitku (x-www-form-urlencoded)
 *
 * Tanpa filter login, dikecualikan dari CSRF (Config/Filters.php); keamanannya lewat signature
 * MD5(merchantCode + amount + merchantOrderId + apiKey). Logika lunasnya sama dengan Midtrans
 * (PembayaranService): idempoten, cek nominal, notifikasi sekali.
 */
class DuitkuCallback extends BaseController
{
    public function index(): ResponseInterface
    {
        $payload = $this->request->getPost();
        if (! is_array($payload) || $payload === []) {
            $payload = (array) ($this->request->getJSON(true) ?? []);
        }

        if ($payload === []) {
            $this->catatGagal('payload kosong', []);

            return $this->balas(400, 'Payload tidak valid.');
        }

        if (! (new DuitkuGateway())->callbackValid($payload)) {
            $this->catatGagal('signature tidak cocok', $payload);

            return $this->balas(403, 'Signature tidak valid.');
        }

        $hasil = (new PembayaranService())->terapkanStatus(
            (string) $payload['merchantOrderId'],
            DuitkuGateway::petakanCallback((string) ($payload['resultCode'] ?? '')),
            (float) $payload['amount'],
            isset($payload['reference']) ? (string) $payload['reference'] : null,
            isset($payload['settlementDate']) ? (string) $payload['settlementDate'] : null,
            'callback duitku'
        );

        return $this->balas($hasil['kode'], $hasil['pesan']);
    }

    private function catatGagal(string $alasan, array $payload): void
    {
        log_message('error', 'Callback QRIS gagal (duitku): {alasan}. order_id={order} result={hasil} ip={ip}', [
            'alasan' => $alasan,
            'order'  => $payload['merchantOrderId'] ?? '-',
            'hasil'  => $payload['resultCode'] ?? '-',
            'ip'     => $this->request->getIPAddress(),
        ]);
    }

    private function balas(int $kode, string $pesan): ResponseInterface
    {
        return $this->response->setStatusCode($kode)->setJSON(['pesan' => $pesan]);
    }
}
