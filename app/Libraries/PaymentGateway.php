<?php

namespace App\Libraries;

/**
 * Memilih payment gateway.
 *
 *   PAYMENT_GATEWAY=midtrans   (bawaan) atau   PAYMENT_GATEWAY=duitku
 *
 * Setiap order_id menyimpan gateway pembuatnya lewat awalan:
 *   KOS-...  -> Midtrans,   KOSD-... -> Duitku
 * Jadi pembayaran lama tetap dicek ke gateway yang benar walau .env diganti.
 */
class PaymentGateway
{
    public const AWALAN = [
        QrisGateway::NAMA   => 'KOS',
        DuitkuGateway::NAMA => 'KOSD',
    ];

    /** Gateway yang dipakai untuk pembayaran baru. */
    public static function aktif(): QrisGateway|DuitkuGateway
    {
        return strtolower(trim((string) env('PAYMENT_GATEWAY', QrisGateway::NAMA))) === DuitkuGateway::NAMA
            ? new DuitkuGateway()
            : new QrisGateway();
    }

    /** Gateway yang membuat order ini. */
    public static function untukOrder(string $orderId): QrisGateway|DuitkuGateway
    {
        return str_starts_with($orderId, self::AWALAN[DuitkuGateway::NAMA] . '-')
            ? new DuitkuGateway()
            : new QrisGateway();
    }

    public static function awalan(QrisGateway|DuitkuGateway $gateway): string
    {
        return self::AWALAN[$gateway->nama()];
    }
}
