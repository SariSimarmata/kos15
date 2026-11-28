<?php

namespace App\Libraries;

use CodeIgniter\HTTP\CURLRequest;
use Config\Services;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

/**
 * Pembungkus API Duitku untuk QRIS dinamis. Bentuk method-nya sama dengan QrisGateway (Midtrans),
 * jadi controller tidak perlu tahu gateway mana yang dipakai (lihat PaymentGateway).
 *
 * Kunci hanya di .env:
 *   PAYMENT_GATEWAY=duitku
 *   DUITKU_MERCHANT_CODE=DSxxxxx
 *   DUITKU_API_KEY=xxxxxxxx
 *   DUITKU_IS_PRODUCTION=false
 *   DUITKU_METODE_QRIS=SP            (SP = ShopeePay QRIS, NQ = Nobu QRIS, LQ = LinkAja QRIS)
 *   DUITKU_CALLBACK_URL=             (opsional, mis. https://xxx.ngrok-free.dev/index.php/pembayaran/callback-duitku)
 *
 * Dokumentasi: https://docs.duitku.com/api/id/
 */
class DuitkuGateway
{
    public const NAMA = 'duitku';

    private const URL_SANDBOX = 'https://sandbox.duitku.com/webapi/api/merchant';
    private const URL_PRODUCTION = 'https://passport.duitku.com/webapi/api/merchant';

    private string $kodeMerchant;
    private string $apiKey;
    private bool $produksi;
    private string $metode;
    private CURLRequest $http;

    public function __construct(?CURLRequest $http = null)
    {
        $this->kodeMerchant = trim((string) env('DUITKU_MERCHANT_CODE', ''));
        $this->apiKey       = trim((string) env('DUITKU_API_KEY', ''));
        $this->produksi     = filter_var(env('DUITKU_IS_PRODUCTION', false), FILTER_VALIDATE_BOOLEAN);
        $this->metode       = strtoupper(trim((string) env('DUITKU_METODE_QRIS', 'SP'))) ?: 'SP';

        $this->http = $http ?? Services::curlrequest([
            'timeout'         => 10,
            'connect_timeout' => 5,
            'http_errors'     => false,
        ], null, null, false);
    }

    public function nama(): string
    {
        return self::NAMA;
    }

    public function siap(): bool
    {
        return $this->kodeMerchant !== '' && $this->apiKey !== '';
    }

    /**
     * Minta QRIS dinamis (POST /v2/inquiry). Nominal dari tagihan, bukan dari penghuni.
     *
     * @return array{qris_url: string, id_transaksi: string, waktu_kedaluwarsa: string, qr_string: ?string}
     *         qris_url = paymentUrl halaman Duitku (cadangan), qr_string = isi QR yang digambar di halaman kita
     */
    public function buatQris(string $orderId, int $nominal, array $pelanggan = []): array
    {
        if (! $this->siap()) {
            throw new RuntimeException('DUITKU_MERCHANT_CODE / DUITKU_API_KEY belum diisi di file .env.');
        }
        if ($nominal < 1) {
            throw new RuntimeException('Nominal QRIS harus lebih dari 0.');
        }

        $sekarang    = new DateTimeImmutable('now', new DateTimeZone(QrisGateway::ZONA_WAKTU));
        $kedaluwarsa = $sekarang->add(new DateInterval('PT' . QrisGateway::MENIT_KEDALUWARSA . 'M'));
        $nama        = trim((string) ($pelanggan['first_name'] ?? 'Penghuni')) ?: 'Penghuni';
        $email       = trim((string) env('DUITKU_EMAIL_PELANGGAN', 'penghuni@kos15.test'));

        $badan = [
            'merchantCode'    => $this->kodeMerchant,
            'paymentAmount'   => $nominal,
            'paymentMethod'   => $this->metode,
            'merchantOrderId' => $orderId,
            'productDetails'  => mb_substr((string) ($pelanggan['keterangan'] ?? 'Tagihan Kos 15'), 0, 255),
            'email'           => $email,
            'customerVaName'  => mb_substr($nama, 0, 20),
            'customerDetail'  => ['firstName' => mb_substr($nama, 0, 50), 'email' => $email],
            'callbackUrl'     => $this->urlCallback(),
            'returnUrl'       => (string) ($pelanggan['return_url'] ?? site_url('penghuni/tagihan')),
            'signature'       => md5($this->kodeMerchant . $orderId . $nominal . $this->apiKey),
            'expiryPeriod'    => QrisGateway::MENIT_KEDALUWARSA,
        ];

        $data = $this->kirim('/v2/inquiry', $badan);

        if (($data['statusCode'] ?? '') !== '00') {
            throw new RuntimeException('Duitku menolak transaksi (' . ($data['statusCode'] ?? '?') . '): '
                . ($data['statusMessage'] ?? $data['Message'] ?? 'tanpa pesan'));
        }
        if (empty($data['qrString']) && empty($data['paymentUrl'])) {
            throw new RuntimeException('Jawaban Duitku tidak berisi qrString maupun paymentUrl. Cek DUITKU_METODE_QRIS.');
        }

        return [
            'qris_url'          => (string) ($data['paymentUrl'] ?? ''),
            'id_transaksi'      => (string) ($data['reference'] ?? ''),
            'waktu_kedaluwarsa' => $kedaluwarsa->format('Y-m-d H:i:s'),
            'qr_string'         => isset($data['qrString']) ? (string) $data['qrString'] : null,
        ];
    }

    /**
     * Tanya status transaksi ke Duitku (POST /transactionStatus).
     *
     * @return array{status: ?string, nominal: float, id_transaksi: ?string, waktu_bayar: ?string}|null
     *         status: berhasil | gagal | null (masih pending). null seluruhnya = gagal menghubungi Duitku.
     */
    public function cekStatus(string $orderId): ?array
    {
        if (! $this->siap()) {
            return null;
        }

        try {
            $data = $this->kirim('/transactionStatus', [
                'merchantCode'    => $this->kodeMerchant,
                'merchantOrderId' => $orderId,
                'signature'       => md5($this->kodeMerchant . $orderId . $this->apiKey),
            ]);
        } catch (Throwable $e) {
            log_message('warning', 'Duitku cekStatus {order} gagal: {pesan}', ['order' => $orderId, 'pesan' => $e->getMessage()]);

            return null;
        }

        if (! isset($data['statusCode'])) {
            return null;
        }

        return [
            'status'       => match ((string) $data['statusCode']) {
                '00'    => 'berhasil',
                '02'    => 'gagal',
                default => null, // 01 = pending
            },
            'nominal'      => (float) ($data['amount'] ?? 0),
            'id_transaksi' => isset($data['reference']) ? (string) $data['reference'] : null,
            'waktu_bayar'  => null,
        ];
    }

    /** Signature callback: MD5(merchantCode + amount + merchantOrderId + apiKey). */
    public function callbackValid(array $payload): bool
    {
        if (! $this->siap()) {
            return false;
        }
        foreach (['merchantCode', 'amount', 'merchantOrderId', 'signature'] as $kunci) {
            if (! isset($payload[$kunci]) || ! is_scalar($payload[$kunci])) {
                return false;
            }
        }
        if ((string) $payload['merchantCode'] !== $this->kodeMerchant) {
            return false;
        }

        $harapan = md5($this->kodeMerchant . $payload['amount'] . $payload['merchantOrderId'] . $this->apiKey);

        return hash_equals($harapan, strtolower((string) $payload['signature']));
    }

    /** resultCode callback -> status_pembayaran. */
    public static function petakanCallback(string $resultCode): ?string
    {
        return match ($resultCode) {
            '00'    => 'berhasil',
            '01'    => 'gagal',
            default => null,
        };
    }

    /** Dipakai untuk membuat callback tiruan saat uji lokal. */
    public function buatSignatureCallback(string $amount, string $orderId): string
    {
        return md5($this->kodeMerchant . $amount . $orderId . $this->apiKey);
    }

    // ------------------------------------------------------------------

    private function urlCallback(): string
    {
        $url = trim((string) env('DUITKU_CALLBACK_URL', ''));

        return $url !== '' ? $url : site_url('pembayaran/callback-duitku');
    }

    private function kirim(string $jalur, array $badan): array
    {
        try {
            $respon = $this->http->post(($this->produksi ? self::URL_PRODUCTION : self::URL_SANDBOX) . $jalur, [
                'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
                'json'    => $badan,
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException('Tidak bisa terhubung ke Duitku: ' . $e->getMessage(), 0, $e);
        }

        $data = json_decode((string) $respon->getBody(), true);
        if (! is_array($data)) {
            throw new RuntimeException('Jawaban Duitku bukan JSON (HTTP ' . $respon->getStatusCode() . ').');
        }

        return $data;
    }
}
