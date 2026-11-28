<?php

use App\Models\TagihanBulananModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Aturan hitung tagihan listrik, air, dan sampah (Kartu 9). Tes murni, tanpa database.
 *
 * @internal
 */
final class TagihanBulananAturanTest extends CIUnitTestCase
{
    public function testContohManualDariSeed(): void
    {
        // Seed: listrik 60 kWh x Rp1.500 = Rp90.000, air 5 m3 x Rp5.000 = Rp25.000, sampah Rp7.500
        $listrik = TagihanBulananModel::hitungPemakaian(120000, 126000, '1500.00'); // meter 1.200 -> 1.260
        $air     = TagihanBulananModel::hitungPemakaian(30000, 30500, '5000.00');   // meter 300 -> 305

        $this->assertSame(6000, $listrik['pemakaian100']);
        $this->assertSame(90000, $listrik['biaya']);
        $this->assertSame(25000, $air['biaya']);
        $this->assertSame(122500, $listrik['biaya'] + $air['biaya'] + 7500);
    }

    public function testPemakaianNolBiayaNol(): void
    {
        $this->assertSame(0, TagihanBulananModel::hitungPemakaian(5000, 5000, 1500)['biaya']);
    }

    public function testMeterAkhirLebihKecilDitolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TagihanBulananModel::hitungPemakaian(5000, 4999, 1500);
    }

    public function testPembulatanSetengahKeAtas(): void
    {
        // 0,5 kWh x Rp1.001 = 500,5 -> 501 ; 0,5 x 1.000 = 500 ; 0,01 kWh x Rp1.449 = 14,49 -> 14
        $this->assertSame(501, TagihanBulananModel::hitungPemakaian(0, 50, 1001)['biaya']);
        $this->assertSame(500, TagihanBulananModel::hitungPemakaian(0, 50, 1000)['biaya']);
        $this->assertSame(14, TagihanBulananModel::hitungPemakaian(0, 1, 1449)['biaya']);
    }

    public function testTarifDesimal(): void
    {
        // 10 kWh x Rp1.444,70 = 14.447
        $this->assertSame(14447, TagihanBulananModel::hitungPemakaian(0, 1000, '1444.70')['biaya']);
    }

    public function testKeSeratus(): void
    {
        $this->assertSame(125000, TagihanBulananModel::keSeratus('1250'));
        $this->assertSame(125050, TagihanBulananModel::keSeratus('1250,5'));
        $this->assertSame(125005, TagihanBulananModel::keSeratus('1250.05'));
        $this->assertSame(0, TagihanBulananModel::keSeratus('0'));
        foreach (['', 'abc', '-5', '1.234', '1e3', '12,3,4', ' '] as $salah) {
            $this->assertNull(TagihanBulananModel::keSeratus($salah), "'{$salah}'");
        }
    }

    public function testSampahRupiah(): void
    {
        $this->assertSame(7500, TagihanBulananModel::sampahRupiah('7500'));
        $this->assertSame(7500, TagihanBulananModel::sampahRupiah('7.500'));
        $this->assertSame(0, TagihanBulananModel::sampahRupiah('0'));
        foreach (['', '-1', '7500,5', 'abc'] as $salah) {
            $this->assertNull(TagihanBulananModel::sampahRupiah($salah), "'{$salah}'");
        }
    }

    public function testTampilSatuan(): void
    {
        $this->assertSame('60', TagihanBulananModel::tampilSatuan(6000));
        $this->assertSame('60,5', TagihanBulananModel::tampilSatuan(6050));
        $this->assertSame('60,05', TagihanBulananModel::tampilSatuan(6005));
    }

    public function testPeriode(): void
    {
        $this->assertTrue(TagihanBulananModel::periodeValid('2026-10'));
        foreach (['2026-13', '2026-00', '2026-1', '26-10', '', '2026-10-01'] as $salah) {
            $this->assertFalse(TagihanBulananModel::periodeValid($salah), $salah);
        }
        $this->assertSame('2026-10', TagihanBulananModel::periodeBulanLalu('2026-11-28'));
        $this->assertSame('2025-12', TagihanBulananModel::periodeBulanLalu('2026-01-05'));
    }

    public function testJatuhTempo(): void
    {
        // Tanggal 25 bulan berikutnya, selama masih lebih dari hari ini + 3 hari
        $this->assertSame('2026-11-25', TagihanBulananModel::jatuhTempo('2026-10', '2026-11-02'));
        $this->assertSame('2026-11-25', TagihanBulananModel::jatuhTempo('2026-10', '2026-11-22')); // tepat 25 = batas
        // Terlambat dibuat: hari ini + 3 hari
        $this->assertSame('2026-12-01', TagihanBulananModel::jatuhTempo('2026-10', '2026-11-28'));
        // Pergantian tahun
        $this->assertSame('2027-01-25', TagihanBulananModel::jatuhTempo('2026-12', '2027-01-03'));
    }
}
