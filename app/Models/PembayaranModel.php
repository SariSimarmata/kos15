<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model tabel `pembayaran`
 * Pembayaran QRIS (gateway) atau tunai.
 */
class PembayaranModel extends Model
{
    protected $table            = 'pembayaran';
    protected $primaryKey       = 'id_pembayaran';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id_tagihan',
        'id_konfirmator',
        'metode',
        'order_id',
        'id_transaksi_gateway',
        'qris_url',
        'waktu_kedaluwarsa',
        'tanggal_bayar',
        'jumlah_bayar',
        'status_pembayaran',
    ];

    // Tabel tidak memiliki kolom created_at / updated_at
    protected $useTimestamps = false;
}
