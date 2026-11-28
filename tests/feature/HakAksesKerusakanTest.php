<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AksesUjiTrait;

/**
 * Hak akses halaman kerusakan. Filter peran menolak sebelum controller jalan;
 * database uji hanya berisi tabel pengguna (lihat AksesUjiTrait).
 *
 * @internal
 */
final class HakAksesKerusakanTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AksesUjiTrait;

    // Tanpa migrasi: tabel pengguna dibuat sendiri oleh siapkanAkunUji()
    protected $migrate = false;
    protected $refresh = false;

    private const HALAMAN_PENGHUNI = ['penghuni/kerusakan', 'penghuni/kerusakan/saya', 'penghuni/kerusakan/baru'];
    private const HALAMAN_PEMILIK  = ['pemilik/kerusakan', 'pemilik/kerusakan/1'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanAkunUji();
    }

    public function testTamuDiarahkanKeLogin(): void
    {
        foreach ([...self::HALAMAN_PENGHUNI, ...self::HALAMAN_PEMILIK] as $url) {
            $hasil = $this->get($url);
            $this->assertTrue($hasil->isRedirect(), $url);
            $this->assertStringContainsString('login', $hasil->getRedirectUrl(), $url);
        }
    }

    public function testPenghuniDitolakDiHalamanPemilik(): void
    {
        foreach (self::HALAMAN_PEMILIK as $url) {
            $this->sebagai('penghuni')->get($url)->assertStatus(403);
        }
    }

    public function testPemilikDanCalonDitolakDiHalamanPenghuni(): void
    {
        foreach (['pemilik', 'calon_penghuni'] as $peran) {
            foreach (self::HALAMAN_PENGHUNI as $url) {
                $this->sebagai($peran)->get($url)->assertStatus(403);
            }
        }
    }
}
