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
 * Pembungkus API Midtrans untuk pembayaran QRIS dinamis (kartu pembayaran QRIS).
 *
 * Semua pemanggilan ke payment gateway lewat kelas ini, jadi controller tidak
 * perlu tahu alamat API, cara login, atau bentuk JSON Midtrans.
 *
 * Kunci API dibaca dari .env, TIDAK PERNAH ditulis di kode:
 *   MIDTRANS_SERVER_KEY=SB-Mid-server-xxxxxxxx
 *   MIDTRANS_IS_PRODUCTION=false
 *
 * Contoh:
 *   $qris  = new QrisGateway();
 *   $hasil = $qris->buatQris('KOS-20261127-0005-A1B2C3', 127500);
 *   $hasil['qris_url'];            // URL gambar QR untuk <img>
 *   $qris->signatureValid($json);  // dipakai PaymentCallback
 */
class QrisGateway
{
    public const ZONA_WAKTU = 'Asia/Jakarta';

    /** Lama kode QRIS berlaku (menit). */
    public const MENIT_KEDALUWARSA = 15;

    private const URL_SANDBOX = 'https://api.sandbox.midtrans.com';
    private const URL_PRODUCTION = 'https://api.midtrans.com';

    private string $serverKey;
    private bool $produksi;
    private CURLRequest $http;

    public function __construct(?CURLRequest $http = null)
    {
        $this->serverKey = trim((string) env('MIDTRANS_SERVER_KEY', ''));
        $this->produksi  = filter_var(env('MIDTRANS_IS_PRODUCTION', false), FILTER_VALIDATE_BOOLEAN);

        // Instans baru (bukan shared) supaya opsi ini tidak bocor ke fitur lain
        $this->http = $http ?? Services::curlrequest([
            'timeout'         => 10,
            'connect_timeout' => 5,
            'http_errors'     => false, // jawaban 4xx/5xx tetap dibaca, tidak dilempar
        ], null, null, false);
    }

    public const NAMA = 'midtrans';

    public function nama(): string
    {
        return self::NAMA;
    }

    /** true jika MIDTRANS_SERVER_KEY sudah diisi di .env. */
    public function siap(): bool
    {
        return $this->serverKey !== '';
    }

    /**
     * Minta kode QRIS dinamis ke Midtrans (POST /v2/charge).
     * Nominal diambil dari tagihan oleh pemanggil, penghuni tidak mengetik nominal.
     *
     * @return array{qris_url: string, id_transaksi: string, waktu_kedaluwarsa: string, qr_string: ?string}
     *         waktu_kedaluwarsa dalam WIB, format Y-m-d H:i:s
     *
     * @throws RuntimeException jika server key kosong atau Midtrans menolak permintaan
     */
    public function buatQris(string $orderId, int $nominal, array $pelanggan = []): array
    {
        if (! $this->siap()) {
            throw new RuntimeException('MIDTRANS_SERVER_KEY belum diisi di file .env.');
        }
        if ($nominal < 1) {
            throw new RuntimeException('Nominal QRIS harus lebih dari 0.');
        }

        $sekarang    = new DateTimeImmutable('now', new DateTimeZone(self::ZONA_WAKTU));
        $kedaluwarsa = $sekarang->add(new DateInterval('PT' . self::MENIT_KEDALUWARSA . 'M'));

        $badan = [
            'payment_type'        => 'qris',
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => $nominal, // Rupiah tanpa desimal
            ],
            'qris'          => ['acquirer' => 'gopay'],
            'custom_expiry' => [
                'order_time'      => $sekarang->format('Y-m-d H:i:s O'), // contoh: 2026-11-27 13:30:00 +0700
                'expiry_duration' => self::MENIT_KEDALUWARSA,
                'unit'            => 'minute',
            ],
        ];
        if (! empty($pelanggan['first_name'])) {
            $badan['customer_details'] = ['first_name' => (string) $pelanggan['first_name']];
        }

        try {
            $respon = $this->http->post($this->urlApi() . '/v2/charge', [
                'headers' => $this->headerAuth(),
                'json'    => $badan,
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException('Tidak bisa terhubung ke Midtrans: ' . $e->getMessage(), 0, $e);
        }

        $data = json_decode((string) $respon->getBody(), true);
        if (! is_array($data)) {
            throw new RuntimeException('Jawaban Midtrans bukan JSON (HTTP ' . $respon->getStatusCode() . ').');
        }

        // Charge QRIS yang berhasil dibuat selalu status_code "201"
        if (($data['status_code'] ?? '') !== '201') {
            $pesan = $data['status_message'] ?? 'tanpa pesan';
            throw new RuntimeException('Midtrans menolak charge (' . ($data['status_code'] ?? '?') . '): ' . $pesan);
        }

        $qrisUrl = $this->ambilUrlQr($data['actions'] ?? []);
        if ($qrisUrl === null) {
            throw new RuntimeException('Jawaban Midtrans tidak berisi action "generate-qr-code".');
        }

        return [
            'qris_url'          => $qrisUrl,
            'id_transaksi'      => (string) ($data['transaction_id'] ?? ''),
            'waktu_kedaluwarsa' => $this->waktuKedaluwarsa($data, $kedaluwarsa),
            'qr_string'         => $data['qr_string'] ?? null,
        ];
    }

    /**
     * Cek signature notifikasi Midtrans:
     * SHA512(order_id + status_code + gross_amount + SERVER_KEY)
     * Nilai dipakai persis seperti yang dikirim Midtrans (gross_amount mis. "127500.00").
     */
    public function signatureValid(array $payload): bool
    {
        if (! $this->siap()) {
            return false;
        }

        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key'] as $kunci) {
            if (! isset($payload[$kunci]) || ! is_scalar($payload[$kunci])) {
                return false;
            }
        }

        $harapan = $this->buatSignature(
            (string) $payload['order_id'],
            (string) $payload['status_code'],
            (string) $payload['gross_amount']
        );

        // hash_equals: perbandingan waktu-konstan, aman dari timing attack
        return hash_equals($harapan, strtolower((string) $payload['signature_key']));
    }

    /** Dipakai signatureValid() dan untuk membuat callback palsu saat uji lokal. */
    /**
     * Tanya status transaksi ke Midtrans (GET /v2/{order_id}/status). Cadangan jika callback tidak sampai
     * (mis. ngrok mati). Hasilnya dipercaya karena permintaan ini memakai Server Key kita sendiri.
     *
     * @return array{status: ?string, nominal: float, id_transaksi: ?string, waktu_bayar: ?string}|null
     */
    public function cekStatus(string $orderId): ?array
    {
        if (! $this->siap()) {
            return null;
        }

        try {
            $respon = $this->http->get($this->urlApi() . '/v2/' . rawurlencode($orderId) . '/status', [
                'headers' => $this->headerAuth(),
            ]);
            $data = json_decode((string) $respon->getBody(), true);
        } catch (Throwable $e) {
            log_message('warning', 'Midtrans cekStatus {order} gagal: {pesan}', ['order' => $orderId, 'pesan' => $e->getMessage()]);

            return null;
        }

        if (! is_array($data) || empty($data['transaction_status'])) {
            return null;
        }

        return [
            'status'       => self::petakanStatus((string) $data['transaction_status'], isset($data['fraud_status']) ? (string) $data['fraud_status'] : null),
            'nominal'      => (float) ($data['gross_amount'] ?? 0),
            'id_transaksi' => isset($data['transaction_id']) ? (string) $data['transaction_id'] : null,
            'waktu_bayar'  => isset($data['settlement_time']) ? (string) $data['settlement_time'] : null,
        ];
    }

    public function buatSignature(string $orderId, string $statusCode, string $grossAmount): string
    {
        return hash('sha512', $orderId . $statusCode . $grossAmount . $this->serverKey);
    }

    /**
     * Terjemahkan transaction_status Midtrans ke status_pembayaran di tabel pembayaran.
     * null berarti tidak ada perubahan (mis. masih pending).
     */
    public static function petakanStatus(string $statusMidtrans, ?string $fraudStatus = null): ?string
    {
        return match ($statusMidtrans) {
            'settlement'                 => 'berhasil',
            // capture hanya untuk kartu kredit; anggap lunas kecuali ditandai fraud
            'capture'                    => ($fraudStatus === null || $fraudStatus === 'accept') ? 'berhasil' : null,
            'expire'                     => 'kedaluwarsa',
            'cancel', 'deny', 'failure'  => 'gagal',
            default                      => null, // pending, authorize, refund, dll.
        };
    }

    // ------------------------------------------------------------------

    private function urlApi(): string
    {
        return $this->produksi ? self::URL_PRODUCTION : self::URL_SANDBOX;
    }

    private function headerAuth(): array
    {
        return [
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'Authorization' => 'Basic ' . base64_encode($this->serverKey . ':'),
        ];
    }

    /** Ambil URL gambar QR dari actions -> name "generate-qr-code". */
    private function ambilUrlQr(array $actions): ?string
    {
        foreach ($actions as $aksi) {
            if (($aksi['name'] ?? '') === 'generate-qr-code' && ! empty($aksi['url'])) {
                return (string) $aksi['url'];
            }
        }

        return null;
    }

    /** Pakai expiry_time dari Midtrans (WIB) jika ada, selain itu hitungan sendiri. */
    private function waktuKedaluwarsa(array $data, DateTimeImmutable $cadangan): string
    {
        if (! empty($data['expiry_time'])) {
            try {
                return (new DateTimeImmutable((string) $data['expiry_time'], new DateTimeZone(self::ZONA_WAKTU)))
                    ->setTimezone(new DateTimeZone(self::ZONA_WAKTU))
                    ->format('Y-m-d H:i:s');
            } catch (Throwable) {
                // format tak dikenal, pakai cadangan
            }
        }

        return $cadangan->format('Y-m-d H:i:s');
    }
}
