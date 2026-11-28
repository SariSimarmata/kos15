<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AksesUjiTrait;

/**
 * Hak akses halaman info kos dan pengumuman. Filter peran menolak sebelum controller jalan;
 * database uji hanya berisi tabel pengguna (lihat AksesUjiTrait).
 *
 * @internal
 */
final class HakAksesInfoKosTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AksesUjiTrait;

    // Tanpa migrasi: tabel pengguna dibuat sendiri oleh siapkanAkunUji()
    protected $migrate = false;
    protected $refresh = false;

    private const HALAMAN_PEMILIK = ['pemilik/info-kos', 'pemilik/pengumuman', 'pemilik/pengumuman/tambah'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanAkunUji();
    }

    public function testTamuDiarahkanKeLogin(): void
    {
        foreach ([...self::HALAMAN_PEMILIK, 'pengumuman'] as $url) {
            $hasil = $this->get($url);
            $this->assertTrue($hasil->isRedirect(), $url);
            $this->assertStringContainsString('login', $hasil->getRedirectUrl(), $url);
        }
    }

    public function testPenghuniDanCalonDitolakDiHalamanPemilik(): void
    {
        foreach (['penghuni', 'calon_penghuni'] as $peran) {
            foreach (self::HALAMAN_PEMILIK as $url) {
                $this->sebagai($peran)->get($url)->assertStatus(403);
            }
        }
    }

    public function testPemilikDitolakDiHalamanBacaPenghuni(): void
    {
        $this->sebagai('pemilik')->get('pengumuman')->assertStatus(403);
    }
}
