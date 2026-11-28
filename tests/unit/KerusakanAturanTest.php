<?php

use App\Models\LaporanKerusakanModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Aturan laporan kerusakan: validasi, alur status, dan pemeriksaan foto (tanpa database).
 *
 * @internal
 */
final class KerusakanAturanTest extends CIUnitTestCase
{
    /** @var list<string> */
    private array $sementara = [];

    protected function tearDown(): void
    {
        foreach ($this->sementara as $f) {
            if (is_file($f)) {
                unlink($f);
            }
        }
        parent::tearDown();
    }

    private function galat(array $aturan, array $pesan, array $data): array
    {
        $v = service('validation');
        $v->reset();
        $v->setRules($aturan, $pesan);
        $v->run($data);

        return $v->getErrors();
    }

    private function pngKecil(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    }

    // ---- Deskripsi ----

    public function testDeskripsiValid(): void
    {
        $this->assertSame([], $this->galat(
            LaporanKerusakanModel::aturanLaporan(),
            LaporanKerusakanModel::pesanLaporan(),
            ['deskripsi' => 'Keran kamar mandi bocor terus.']
        ));
    }

    public function testDeskripsiTidakValid(): void
    {
        foreach (['', '   ', 'bocor', str_repeat('a', LaporanKerusakanModel::DESKRIPSI_MAKS + 1)] as $salah) {
            $this->assertArrayHasKey('deskripsi', $this->galat(
                LaporanKerusakanModel::aturanLaporan(),
                LaporanKerusakanModel::pesanLaporan(),
                ['deskripsi' => $salah]
            ), $salah);
        }
    }

    // ---- Alur status ----

    public function testAlurStatusHanyaMajuSatuLangkah(): void
    {
        $this->assertSame('diproses', LaporanKerusakanModel::statusBerikut('dilaporkan'));
        $this->assertSame('selesai', LaporanKerusakanModel::statusBerikut('diproses'));
        $this->assertNull(LaporanKerusakanModel::statusBerikut('selesai'));

        $this->assertTrue(LaporanKerusakanModel::bolehPindah('dilaporkan', 'diproses'));
        $this->assertTrue(LaporanKerusakanModel::bolehPindah('diproses', 'selesai'));
        $this->assertFalse(LaporanKerusakanModel::bolehPindah('dilaporkan', 'selesai'), 'tidak boleh melompat');
        $this->assertFalse(LaporanKerusakanModel::bolehPindah('diproses', 'dilaporkan'), 'tidak boleh mundur');
        $this->assertFalse(LaporanKerusakanModel::bolehPindah('selesai', 'diproses'));
        $this->assertFalse(LaporanKerusakanModel::bolehPindah('selesai', 'selesai'));
        $this->assertFalse(LaporanKerusakanModel::bolehPindah('dilaporkan', 'ngawur'));
    }

    public function testCatatanWajibHanyaSaatSelesai(): void
    {
        $pesan = LaporanKerusakanModel::pesanStatus();
        $this->assertArrayHasKey('catatan_perbaikan', $this->galat(LaporanKerusakanModel::aturanStatus('selesai'), $pesan, ['catatan_perbaikan' => '']));
        $this->assertSame([], $this->galat(LaporanKerusakanModel::aturanStatus('selesai'), $pesan, ['catatan_perbaikan' => 'Keran sudah diganti.']));
        $this->assertSame([], $this->galat(LaporanKerusakanModel::aturanStatus('diproses'), $pesan, ['catatan_perbaikan' => '']));
    }

    // ---- Foto ----

    /** Periksa berkas sementara seperti yang dilakukan galatFoto() pada unggahan sungguhan. */
    private function periksa(string $isi, bool $wajib = true, ?string $mimeAsli = null): ?string
    {
        $path = tempnam(sys_get_temp_dir(), 'kr');
        file_put_contents($path, $isi);
        $this->sementara[] = $path;
        $mime = $mimeAsli ?? (new finfo(FILEINFO_MIME_TYPE))->file($path);

        return LaporanKerusakanModel::galatBerkas(UPLOAD_ERR_OK, strlen($isi), $mime, $path, $wajib);
    }

    public function testFotoWajibUntukLaporan(): void
    {
        $this->assertStringContainsString('wajib', (string) LaporanKerusakanModel::galatFoto(null, true));
        $this->assertStringContainsString('wajib', (string) LaporanKerusakanModel::galatBerkas(UPLOAD_ERR_NO_FILE, 0, null, null, true));
        $this->assertNull(LaporanKerusakanModel::galatBerkas(UPLOAD_ERR_NO_FILE, 0, null, null, false), 'foto perbaikan boleh kosong');
        $this->assertNull(LaporanKerusakanModel::galatFoto(null, false));
    }

    public function testFotoPngAsliLolos(): void
    {
        $this->assertNull($this->periksa($this->pngKecil()));
    }

    public function testFotoSalahFormatDitolak(): void
    {
        // File teks PHP yang berpura-pura jadi .jpg: yang dicek isinya, bukan namanya
        $this->assertStringContainsString('harus berformat', (string) $this->periksa("<?php echo 'x';"));

        // PDF
        $this->assertStringContainsString('harus berformat', (string) $this->periksa("%PDF-1.4\n1 0 obj\n<<>>\nendobj\n"));

        // GIF asli tidak termasuk yang diizinkan
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        $this->assertStringContainsString('harus berformat', (string) $this->periksa($gif));
    }

    public function testFotoBerlabelGambarTapiBukanGambarDitolak(): void
    {
        // Tipe dilaporkan jpeg tetapi isinya bukan gambar
        $this->assertStringContainsString('tidak terbaca', (string) $this->periksa('bukan gambar sama sekali', true, 'image/jpeg'));
    }

    public function testFotoTerlaluBesarDitolak(): void
    {
        $besar = $this->pngKecil() . str_repeat('0', LaporanKerusakanModel::FOTO_MAKS);
        $this->assertStringContainsString('terlalu besar', (string) $this->periksa($besar));

        $this->assertStringContainsString('terlalu besar', (string) LaporanKerusakanModel::galatBerkas(UPLOAD_ERR_INI_SIZE, 0, null, null, true));
        $this->assertStringContainsString('gagal diunggah', (string) LaporanKerusakanModel::galatBerkas(UPLOAD_ERR_PARTIAL, 0, null, null, true));
    }

    // ---- Alamat foto ----

    public function testUrlFotoKosongBilaFileTidakAda(): void
    {
        $this->assertNull(LaporanKerusakanModel::urlFoto(null));
        $this->assertNull(LaporanKerusakanModel::urlFoto(''));
        $this->assertNull(LaporanKerusakanModel::urlFoto('laporan/lap_001.jpg'), 'data contoh tanpa file fisik');
        $this->assertNull(LaporanKerusakanModel::urlFoto('../../../etc/passwd'));
    }

    // ---- Tidak membocorkan pelapor ----

    public function testDaftarBersamaTidakMemuatKolomPelapor(): void
    {
        $kolom = (new ReflectionClassConstant(LaporanKerusakanModel::class, 'KOLOM_PUBLIK'))->getValue();
        $this->assertStringNotContainsString('id_pengguna', $kolom);
        $this->assertStringNotContainsString('nama', $kolom);
    }
}
