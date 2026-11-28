<?php

namespace App\Models;

use CodeIgniter\Model;
use RuntimeException;

/**
 * Model tabel `info_kos`
 * Informasi umum dan tarif kos. Tabel ini HANYA berisi satu baris.
 *
 * Kartu lain (Kartu 8, 9, dst.) mengambil tarif lewat satu pintu:
 *
 *     $info = model(InfoKosModel::class)->ambil();
 *     $info['tarif_listrik_per_kwh'];      // float
 *     $info['batas_menginap_per_minggu'];  // int
 *     $info['pengingat'];                  // [7, 3, 1]
 *
 * Jangan memakai insert() / first() sendiri. Perubahan data selalu lewat simpanInfo(),
 * yang mengubah baris yang sudah ada dan tidak pernah menambah baris kedua.
 */
class InfoKosModel extends Model
{
    protected $table            = 'info_kos';
    protected $primaryKey       = 'id_info';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'lokasi',
        'jadwal_pengingat',
        'no_whatsapp',
        'tarif_listrik_per_kwh',
        'tarif_air_per_m3',
        'denda_piket_per_hari',
        'tarif_menginap_per_hari',
        'batas_menginap_per_minggu',
    ];

    // Tabel tidak memiliki kolom created_at / updated_at
    protected $useTimestamps = false;

    /** Kolom tarif (DECIMAL) beserta labelnya untuk form dan pesan galat. */
    public const TARIF = [
        'tarif_listrik_per_kwh'   => 'Tarif listrik per kWh',
        'tarif_air_per_m3'        => 'Tarif air per m³',
        'denda_piket_per_hari'    => 'Denda piket per hari',
        'tarif_menginap_per_hari' => 'Tarif menginap per hari',
    ];

    /** Batas atas tarif: kolom DECIMAL(12,2) maksimal 9.999.999.999,99. */
    public const TARIF_MAKS = 9999999999;

    /** Jadwal pengingat: hari sebelum jatuh tempo, antara 1 dan 30 hari, maksimal 5 angka. */
    public const PENGINGAT_MAKS_HARI   = 30;
    public const PENGINGAT_MAKS_JUMLAH = 5;

    /** Dipakai hanya bila tabel kosong, supaya halaman lain tidak error. */
    public const BAWAAN = [
        'lokasi'                    => '',
        'jadwal_pengingat'          => '7,3,1',
        'no_whatsapp'               => '',
        'tarif_listrik_per_kwh'     => 0,
        'tarif_air_per_m3'          => 0,
        'denda_piket_per_hari'      => 0,
        'tarif_menginap_per_hari'   => 0,
        'batas_menginap_per_minggu' => 3,
    ];

    // ------------------------------------------------------------------
    //  Membaca
    // ------------------------------------------------------------------

    /**
     * Satu-satunya baris info kos, dengan tipe data yang sudah rapi:
     * tarif sebagai float, batas menginap sebagai int, dan 'pengingat' berisi daftar hari.
     * Bila tabel kosong, yang dikembalikan nilai bawaan (id_info = null), tanpa menulis ke database.
     */
    public function ambil(): array
    {
        $baris = $this->orderBy($this->primaryKey, 'ASC')->first();

        return self::rapikan($baris ?? (['id_info' => null] + self::BAWAAN));
    }

    /** Ubah teks dan angka dari database menjadi tipe yang siap dipakai. */
    public static function rapikan(array $baris): array
    {
        foreach (array_keys(self::TARIF) as $kolom) {
            $baris[$kolom] = (float) ($baris[$kolom] ?? 0);
        }
        $baris['batas_menginap_per_minggu'] = (int) ($baris['batas_menginap_per_minggu'] ?? 3);
        $baris['pengingat']                 = self::daftarPengingat((string) ($baris['jadwal_pengingat'] ?? ''));

        return $baris;
    }

    /** "7,3,1" -> [7, 3, 1] (angka tidak valid dibuang, urutan dipertahankan). */
    public static function daftarPengingat(string $teks): array
    {
        $hasil = [];
        foreach (explode(',', $teks) as $bagian) {
            $bagian = trim($bagian);
            if (ctype_digit($bagian) && (int) $bagian > 0) {
                $hasil[] = (int) $bagian;
            }
        }

        return $hasil;
    }

    // ------------------------------------------------------------------
    //  Menyimpan
    // ------------------------------------------------------------------

    /**
     * Simpan info kos. Mengubah baris yang sudah ada; baris baru hanya dibuat bila tabel masih kosong,
     * sehingga tabel tidak pernah berisi lebih dari satu baris.
     * Data sebaiknya sudah lolos validasi (lihat aturan() dan normalisasi).
     *
     * @throws RuntimeException bila penyimpanan gagal
     */
    public function simpanInfo(array $data): void
    {
        $data = array_intersect_key($data, array_flip($this->allowedFields));

        $ada = $this->orderBy($this->primaryKey, 'ASC')->first();

        $berhasil = $ada === null
            ? $this->insert($data + self::BAWAAN) !== false
            : $this->update($ada[$this->primaryKey], $data);

        if (! $berhasil) {
            throw new RuntimeException('Info kos gagal disimpan: ' . implode(' ', $this->errors()));
        }
    }

    // ------------------------------------------------------------------
    //  Validasi dan normalisasi (murni, tanpa database)
    // ------------------------------------------------------------------

    /**
     * Aturan validasi form info kos untuk $this->validate().
     * Jalankan normalisasi() dulu pada data mentah, lalu validasi hasilnya.
     */
    public static function aturan(): array
    {
        $aturan = [
            'lokasi' => ['label' => 'Lokasi', 'rules' => 'required|min_length[5]|max_length[255]'],
            'no_whatsapp' => [
                'label' => 'Nomor WhatsApp',
                'rules' => 'required|regex_match[/^(08[0-9]{8,12}|628[0-9]{8,11})$/]',
            ],
            'jadwal_pengingat' => ['label' => 'Jadwal pengingat', 'rules' => 'required|max_length[50]'],
            'batas_menginap_per_minggu' => [
                'label' => 'Batas menginap per minggu',
                'rules' => 'required|is_natural_no_zero|less_than_equal_to[7]',
            ],
        ];

        foreach (self::TARIF as $kolom => $label) {
            $aturan[$kolom] = [
                'label' => $label,
                'rules' => 'required|numeric|greater_than_equal_to[0]|less_than_equal_to[' . self::TARIF_MAKS . ']',
            ];
        }

        return $aturan;
    }

    public static function pesanAturan(): array
    {
        $pesan = [
            'lokasi' => [
                'required'   => 'Lokasi wajib diisi.',
                'min_length' => 'Lokasi minimal 5 karakter.',
                'max_length' => 'Lokasi maksimal 255 karakter.',
            ],
            'no_whatsapp' => [
                'required'    => 'Nomor WhatsApp wajib diisi.',
                'regex_match' => 'Nomor WhatsApp tidak valid. Awali dengan 08 atau 628, misalnya 081234567890.',
            ],
            'jadwal_pengingat' => [
                'required' => 'Jadwal pengingat wajib diisi, misalnya 7,3,1.',
            ],
            'batas_menginap_per_minggu' => [
                'required'           => 'Batas menginap per minggu wajib diisi.',
                'is_natural_no_zero' => 'Batas menginap per minggu harus bilangan bulat minimal 1.',
                'less_than_equal_to' => 'Batas menginap per minggu maksimal 7 hari.',
            ],
        ];

        foreach (self::TARIF as $kolom => $label) {
            $pesan[$kolom] = [
                'required'                 => $label . ' wajib diisi.',
                'numeric'                  => $label . ' harus berupa angka.',
                'greater_than_equal_to'    => $label . ' tidak boleh negatif.',
                'less_than_equal_to'       => $label . ' terlalu besar.',
            ];
        }

        return $pesan;
    }

    /**
     * Rapikan input form sebelum divalidasi:
     * - nomor WhatsApp: spasi, strip, titik, tanda kurung, dan awalan + dibuang; "+62" menjadi "62".
     * - jadwal pengingat: "3, 7 ,1,7" menjadi "7,3,1" (urut turun, tanpa duplikat). Bila ada
     *   bagian yang bukan angka 1..30 atau jumlahnya lebih dari 5, teks asli dibiarkan dan
     *   jadwalPengingatValid() yang menolaknya.
     * - tarif: spasi dibuang, koma desimal menjadi titik.
     *
     * @param array<string, mixed> $mentah
     *
     * @return array<string, mixed>
     */
    public static function normalisasi(array $mentah): array
    {
        $data = [];

        $data['lokasi'] = trim((string) ($mentah['lokasi'] ?? ''));

        $wa = preg_replace('/[\s\-\.\(\)]+/', '', trim((string) ($mentah['no_whatsapp'] ?? ''))) ?? '';
        $wa = ltrim($wa, '+');
        $data['no_whatsapp'] = $wa;

        $jadwal = trim((string) ($mentah['jadwal_pengingat'] ?? ''));
        $data['jadwal_pengingat'] = self::rapikanJadwal($jadwal) ?? $jadwal;

        foreach (array_keys(self::TARIF) as $kolom) {
            $angka = str_replace([' ', ','], ['', '.'], trim((string) ($mentah[$kolom] ?? '')));
            $data[$kolom] = $angka;
        }

        $data['batas_menginap_per_minggu'] = trim((string) ($mentah['batas_menginap_per_minggu'] ?? ''));

        return $data;
    }

    /** "3, 7,1,7" -> "7,3,1". Null bila tidak sesuai aturan jadwal pengingat. */
    public static function rapikanJadwal(string $teks): ?string
    {
        if (trim($teks) === '') {
            return null;
        }

        $angka = [];
        foreach (explode(',', $teks) as $bagian) {
            $bagian = trim($bagian);
            if (! ctype_digit($bagian)) {
                return null;
            }
            $hari = (int) $bagian;
            if ($hari < 1 || $hari > self::PENGINGAT_MAKS_HARI) {
                return null;
            }
            $angka[$hari] = $hari;
        }

        if (count($angka) > self::PENGINGAT_MAKS_JUMLAH) {
            return null;
        }

        rsort($angka);

        return implode(',', $angka);
    }

    /** True bila teks sudah berbentuk jadwal yang benar (hasil rapikanJadwal() sama persis). */
    public static function jadwalPengingatValid(string $teks): bool
    {
        return self::rapikanJadwal($teks) === $teks;
    }

    /** Nilai tarif dibulatkan ke 2 desimal agar cocok dengan kolom DECIMAL(12,2). */
    public static function untukDisimpan(array $data): array
    {
        foreach (array_keys(self::TARIF) as $kolom) {
            $data[$kolom] = round((float) $data[$kolom], 2);
        }
        $data['batas_menginap_per_minggu'] = (int) $data['batas_menginap_per_minggu'];

        return $data;
    }
}
