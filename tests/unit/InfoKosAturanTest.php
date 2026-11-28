<?php

use App\Models\InfoKosModel;
use App\Models\PengumumanModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Aturan validasi dan normalisasi info kos dan pengumuman (tanpa database).
 *
 * @internal
 */
final class InfoKosAturanTest extends CIUnitTestCase
{
    /** Data form yang benar, dipakai sebagai dasar tiap tes. */
    private function dasar(array $ubah = []): array
    {
        return array_merge([
            'lokasi'                    => 'Jl. Almamater No. 10, Padang Bulan, Medan',
            'no_whatsapp'               => '081200000001',
            'jadwal_pengingat'          => '7,3,1',
            'tarif_listrik_per_kwh'     => '1500',
            'tarif_air_per_m3'          => '5000',
            'denda_piket_per_hari'      => '20000',
            'tarif_menginap_per_hari'   => '5000',
            'batas_menginap_per_minggu' => '3',
        ], $ubah);
    }

    /** @return array<string, string> galat per kolom (kosong bila lolos) */
    private function galat(array $ubah = []): array
    {
        $data      = InfoKosModel::normalisasi($this->dasar($ubah));
        $validasi  = service('validation');
        $validasi->reset();
        $validasi->setRules(InfoKosModel::aturan(), InfoKosModel::pesanAturan());
        $validasi->run($data);

        return $validasi->getErrors();
    }

    public function testDataBenarLolos(): void
    {
        $this->assertSame([], $this->galat());
    }

    public function testTarifNegatifDitolak(): void
    {
        foreach (array_keys(InfoKosModel::TARIF) as $kolom) {
            $galat = $this->galat([$kolom => '-1']);
            $this->assertArrayHasKey($kolom, $galat, $kolom);
            $this->assertStringContainsString('tidak boleh negatif', $galat[$kolom]);
        }
    }

    public function testTarifNolDanDesimalBoleh(): void
    {
        $this->assertSame([], $this->galat(['denda_piket_per_hari' => '0']));
        $this->assertSame([], $this->galat(['tarif_listrik_per_kwh' => '1444,70']));
        $this->assertSame([], $this->galat(['tarif_air_per_m3' => ' 5 000 ']));
    }

    public function testTarifBukanAngkaAtauKosongDitolak(): void
    {
        $this->assertArrayHasKey('tarif_air_per_m3', $this->galat(['tarif_air_per_m3' => 'abc']));
        $this->assertArrayHasKey('tarif_air_per_m3', $this->galat(['tarif_air_per_m3' => '']));
        $this->assertArrayHasKey('tarif_air_per_m3', $this->galat(['tarif_air_per_m3' => '99999999999']));
    }

    /** @dataProvider nomorBenar */
    public function testNomorWhatsappBenar(string $masukan, string $tersimpan): void
    {
        $data = InfoKosModel::normalisasi($this->dasar(['no_whatsapp' => $masukan]));
        $this->assertSame($tersimpan, $data['no_whatsapp']);
        $this->assertArrayNotHasKey('no_whatsapp', $this->galat(['no_whatsapp' => $masukan]));
    }

    public static function nomorBenar(): iterable
    {
        yield 'biasa'         => ['081234567890', '081234567890'];
        yield 'spasi dan strip' => ['0812 3456-7890', '081234567890'];
        yield '+62'           => ['+62 812-3456-7890', '6281234567890'];
        yield '62'            => ['6281234567890', '6281234567890'];
    }

    /** @dataProvider nomorSalah */
    public function testNomorWhatsappSalah(string $masukan): void
    {
        $this->assertArrayHasKey('no_whatsapp', $this->galat(['no_whatsapp' => $masukan]));
    }

    public static function nomorSalah(): iterable
    {
        yield 'kosong'        => [''];
        yield 'huruf'         => ['08123abc4567'];
        yield 'terlalu pendek' => ['0812345'];
        yield 'tanpa awalan'  => ['812345678901'];
        yield '+1'            => ['+14155550123'];
        yield 'terlalu panjang' => ['08123456789012345'];
    }

    public function testJadwalPengingat(): void
    {
        $this->assertSame('7,3,1', InfoKosModel::rapikanJadwal('7,3,1'));
        $this->assertSame('7,3,1', InfoKosModel::rapikanJadwal(' 1, 7 ,3,7'));
        $this->assertNull(InfoKosModel::rapikanJadwal(''));
        $this->assertNull(InfoKosModel::rapikanJadwal('7,tiga,1'));
        $this->assertNull(InfoKosModel::rapikanJadwal('0,3'));
        $this->assertNull(InfoKosModel::rapikanJadwal('7,-3'));
        $this->assertNull(InfoKosModel::rapikanJadwal('31'));
        $this->assertNull(InfoKosModel::rapikanJadwal('1,2,3,4,5,6'));

        $this->assertTrue(InfoKosModel::jadwalPengingatValid('7,3,1'));
        $this->assertFalse(InfoKosModel::jadwalPengingatValid('7,x'));
        $this->assertSame([7, 3, 1], InfoKosModel::daftarPengingat('7,3,1'));
    }

    public function testBatasMenginap(): void
    {
        foreach (['0', '8', '-1', 'abc', '', '2.5'] as $salah) {
            $this->assertArrayHasKey('batas_menginap_per_minggu', $this->galat(['batas_menginap_per_minggu' => $salah]), $salah);
        }
        foreach (['1', '3', '7'] as $benar) {
            $this->assertSame([], $this->galat(['batas_menginap_per_minggu' => $benar]), $benar);
        }
    }

    public function testLokasiWajib(): void
    {
        $this->assertArrayHasKey('lokasi', $this->galat(['lokasi' => '   ']));
        $this->assertArrayHasKey('lokasi', $this->galat(['lokasi' => str_repeat('a', 256)]));
    }

    public function testRapikanTipeData(): void
    {
        $info = InfoKosModel::rapikan([
            'id_info' => '1', 'lokasi' => 'x', 'jadwal_pengingat' => '7,3,1', 'no_whatsapp' => '0812',
            'tarif_listrik_per_kwh' => '1500.00', 'tarif_air_per_m3' => '5000.00',
            'denda_piket_per_hari' => '20000.00', 'tarif_menginap_per_hari' => '5000.00',
            'batas_menginap_per_minggu' => '3',
        ]);
        $this->assertSame(1500.0, $info['tarif_listrik_per_kwh']);
        $this->assertSame(3, $info['batas_menginap_per_minggu']);
        $this->assertSame([7, 3, 1], $info['pengingat']);
    }

    public function testUntukDisimpanMembulatkanDuaDesimal(): void
    {
        $data = InfoKosModel::untukDisimpan(InfoKosModel::normalisasi($this->dasar(['tarif_listrik_per_kwh' => '1444,705'])));
        $this->assertSame(1444.71, $data['tarif_listrik_per_kwh']);
        $this->assertSame(3, $data['batas_menginap_per_minggu']);
    }

    // ---- Pengumuman ----

    private function galatPengumuman(array $data): array
    {
        $validasi = service('validation');
        $validasi->reset();
        $validasi->setRules(PengumumanModel::aturan(), PengumumanModel::pesanAturan());
        $validasi->run($data);

        return $validasi->getErrors();
    }

    public function testPengumumanValid(): void
    {
        $this->assertSame([], $this->galatPengumuman(['jenis' => 'pengumuman', 'isi' => 'Air mati hari Sabtu pukul 09.00.']));
        $this->assertSame([], $this->galatPengumuman(['jenis' => 'peraturan', 'isi' => 'Dilarang membawa hewan peliharaan.']));
    }

    public function testPengumumanTidakValid(): void
    {
        $this->assertArrayHasKey('jenis', $this->galatPengumuman(['jenis' => 'iklan', 'isi' => 'Isi yang cukup panjang']));
        $this->assertArrayHasKey('jenis', $this->galatPengumuman(['jenis' => '', 'isi' => 'Isi yang cukup panjang']));
        $this->assertArrayHasKey('isi', $this->galatPengumuman(['jenis' => 'peraturan', 'isi' => '']));
        $this->assertArrayHasKey('isi', $this->galatPengumuman(['jenis' => 'peraturan', 'isi' => 'abc']));
        $this->assertArrayHasKey('isi', $this->galatPengumuman(['jenis' => 'peraturan', 'isi' => str_repeat('a', PengumumanModel::ISI_MAKS + 1)]));
    }
}
