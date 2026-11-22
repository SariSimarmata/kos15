<?php

/**
 * Helper Kos 15 untuk katalog dan survei (Kartu 7).
 * Muat di controller dengan: protected $helpers = ['kos'];
 * Format tanggal pendek ("1 Des 2026") tetap pakai tanggal_helper milik Kartu 5.
 */

if (! function_exists('kos_now')) {
    /**
     * Waktu sekarang di zona Asia/Jakarta (WIB).
     */
    function kos_now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
    }
}

if (! function_exists('rupiah')) {
    /**
     * 7200000 -> "Rp7.200.000"
     */
    function rupiah($angka): string
    {
        return 'Rp' . number_format((float) $angka, 0, ',', '.');
    }
}

if (! function_exists('media_kamar_url')) {
    /**
     * URL file foto/video kamar dari kolom media_kamar.file.
     * Folder mengikuti Kartu 6: public/uploads/kamar/ (MediaKamarModel::FOLDER),
     * kolom file berisi nama file saja, mis. "1732170000_a1b2c3.jpg".
     * Data contoh lama di kos15.sql berisi "kamar/k01_1.jpg", jadi awalan "kamar/" ikut dibuang.
     * Mengembalikan null jika file belum ada, supaya view menampilkan kotak placeholder.
     */
    function media_kamar_url(?string $file): ?string
    {
        if ($file === null || trim($file) === '') {
            return null;
        }

        $nama    = preg_replace('#^/?(uploads/)?kamar/#', '', trim($file));
        $relatif = \App\Models\MediaKamarModel::FOLDER . $nama;

        return is_file(FCPATH . $relatif) ? base_url($relatif) : null;
    }
}

if (! function_exists('daftar_fasilitas')) {
    /**
     * "Kasur, lemari, meja belajar" -> ['Kasur', 'Lemari', 'Meja belajar']
     */
    function daftar_fasilitas(?string $teks): array
    {
        $bagian = preg_split('/[,;\r\n]+/', (string) $teks) ?: [];
        $bagian = array_filter(array_map('trim', $bagian), static fn ($s) => $s !== '');

        return array_values(array_map(static fn ($s) => mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1), $bagian));
    }
}

if (! function_exists('tanggal_panjang')) {
    /**
     * "2026-11-22" -> "Minggu, 22 November 2026" (dengan hari) atau "22 November 2026".
     * Dipakai di pesan WhatsApp survei supaya tanggalnya jelas.
     */
    function tanggal_panjang(string $tanggal, bool $denganHari = false): string
    {
        $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli',
            'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

        $t     = new DateTimeImmutable($tanggal);
        $hasil = $t->format('j') . ' ' . $bulan[(int) $t->format('n')] . ' ' . $t->format('Y');

        return $denganHari ? $hari[(int) $t->format('w')] . ', ' . $hasil : $hasil;
    }
}

if (! function_exists('normalisasi_no_hp')) {
    /**
     * "+62 812-3456-7890" / "62812..." / "0812 ..." -> "081234567890"
     */
    function normalisasi_no_hp(?string $no): string
    {
        $angka = preg_replace('/\D+/', '', (string) $no);

        if (str_starts_with($angka, '62')) {
            $angka = '0' . substr($angka, 2);
        }

        return $angka;
    }
}

if (! function_exists('wa_link')) {
    /**
     * Link WhatsApp dengan pesan terisi. Nomor 08xx diubah ke format 628xx.
     */
    function wa_link(string $no, string $pesan): string
    {
        $angka = preg_replace('/\D+/', '', $no);

        if (str_starts_with($angka, '0')) {
            $angka = '62' . substr($angka, 1);
        }

        return 'https://wa.me/' . $angka . '?text=' . rawurlencode($pesan);
    }
}

