<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Hak akses halaman info kos dan pengumuman. Filter peran menolak sebelum controller jalan,
 * jadi tes ini tidak menyentuh database.
 *
 * @internal
 */
final class HakAksesInfoKosTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const HALAMAN_PEMILIK = ['pemilik/info-kos', 'pemilik/pengumuman', 'pemilik/pengumuman/tambah'];

    private function sebagai(string $peran): self
    {
        return $this->withSession(['logged_in' => true, 'role' => $peran, 'id_pengguna' => 99, 'nama' => 'Uji']);
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
