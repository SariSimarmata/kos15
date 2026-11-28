<?php

namespace App\Commands;

use App\Libraries\NotifikasiService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Perintah harian pengingat tagihan (kartu Notifikasi dan Pengingat Harian).
 *
 *   php spark pengingat:kirim                       pakai tanggal hari ini (WIB)
 *   php spark pengingat:kirim --tanggal=2026-12-01  uji seolah-olah hari ini 1 Des 2026
 *   php spark pengingat:kirim --coba                tampilkan saja, tidak menyimpan
 *
 * Dijadwalkan sekali sehari lewat Task Scheduler Windows (lihat README bagian 10).
 */
class KirimPengingat extends BaseCommand
{
    protected $group       = 'Kos 15';
    protected $name        = 'pengingat:kirim';
    protected $description = 'Membuat notifikasi pengingat tagihan belum bayar pada H-7, H-3, dan H-1 (sesuai jadwal_pengingat).';
    protected $usage       = 'pengingat:kirim [--tanggal=YYYY-MM-DD] [--coba]';
    protected $options     = [
        '--tanggal' => 'Tanggal uji (YYYY-MM-DD). Kosong = hari ini (WIB).',
        '--coba'    => 'Hanya tampilkan tagihan yang akan diingatkan, tanpa menyimpan notifikasi.',
    ];

    public function run(array $params)
    {
        $teks     = $this->opsi('tanggal', $params);
        $cobaSaja = $this->opsi('coba', $params) !== null;
        $zona     = new DateTimeZone(NotifikasiService::ZONA_WAKTU);

        if ($teks === null || $teks === true) {
            $tanggal = new DateTimeImmutable('today', $zona);
        } else {
            $tanggal = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $teks, $zona);
            if ($tanggal === false || $tanggal->format('Y-m-d') !== $teks) {
                CLI::error('Format --tanggal salah. Contoh: --tanggal=2026-12-01');

                return EXIT_USER_INPUT;
            }
        }

        try {
            $hasil = (new NotifikasiService())->pengingatTagihan($tanggal, $cobaSaja);
        } catch (Throwable $e) {
            log_message('error', 'pengingat:kirim gagal: ' . $e->getMessage());
            CLI::error('Pengingat gagal dijalankan: ' . $e->getMessage());

            return EXIT_ERROR;
        }

        CLI::write('Tanggal  : ' . $hasil['tanggal'] . (is_string($teks) ? ' (tanggal uji)' : ''), 'yellow');
        CLI::write('Jadwal   : H-' . implode(', H-', $hasil['jadwal']));
        if ($cobaSaja) {
            CLI::write('Mode coba: tidak ada notifikasi yang disimpan.', 'light_gray');
        }
        CLI::newLine();

        $kepala = ['H', 'ID Tagihan', 'Penghuni', 'Jenis', 'Nominal', 'Jatuh tempo'];
        $baris  = static fn (array $r) => ['H-' . $r['h'], $r['id_tagihan'], $r['penghuni'], $r['jenis'], $r['nominal'], $r['jatuh_tempo']];

        if ($hasil['dikirim'] !== []) {
            CLI::write(($cobaSaja ? 'Akan dikirim' : 'Dikirim') . ' (' . count($hasil['dikirim']) . '):', 'green');
            CLI::table(array_map($baris, $hasil['dikirim']), $kepala);
        }
        if ($hasil['dilewati'] !== []) {
            CLI::write('Dilewati, sudah ada pengingatnya (' . count($hasil['dilewati']) . '):', 'light_gray');
            CLI::table(array_map($baris, $hasil['dilewati']), $kepala);
        }
        if ($hasil['dikirim'] === [] && $hasil['dilewati'] === []) {
            CLI::write('Tidak ada tagihan belum bayar yang jatuh tempo pada H-' . implode(', H-', $hasil['jadwal']) . '.');
        }

        log_message('info', 'pengingat:kirim {tgl}: {kirim} dikirim, {lewat} dilewati{coba}.', [
            'tgl'   => $hasil['tanggal'],
            'kirim' => count($hasil['dikirim']),
            'lewat' => count($hasil['dilewati']),
            'coba'  => $cobaSaja ? ' (mode coba)' : '',
        ]);

        return EXIT_SUCCESS;
    }

    /**
     * Baca opsi dalam dua bentuk: --tanggal 2026-12-01 dan --tanggal=2026-12-01.
     * Parser bawaan CI4 hanya mengenal bentuk pertama; bentuk kedua terbaca sebagai opsi bernama "tanggal=2026-12-01".
     *
     * @return string|true|null null jika opsi tidak ada, true jika ada tanpa nilai
     */
    private function opsi(string $nama, array $params)
    {
        foreach (array_merge($params, CLI::getOptions()) as $kunci => $nilai) {
            $kunci = (string) $kunci;
            if ($kunci === $nama) {
                return ($nilai === null || $nilai === '') ? true : (string) $nilai;
            }
            if (str_starts_with($kunci, $nama . '=')) {
                return substr($kunci, strlen($nama) + 1);
            }
        }

        return null;
    }
}
