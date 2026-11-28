<?php

use App\Models\LaporanKerusakanModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Query laporan kerusakan pada database uji (SQLite memori, tabel dibuat seperlunya).
 * Memastikan: kamar diambil dari sewa AKTIF, halaman bersama tidak memuat kolom pelapor,
 * halaman pemilik memuat pelapor dan kamar, dan status tidak bisa melompat.
 *
 * @internal
 */
final class KerusakanDbTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    private LaporanKerusakanModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        $t = fn (string $n) => $this->db->prefixTable($n);
        foreach (['laporan_kerusakan', 'sewa', 'pemesanan', 'kamar', 'pengguna'] as $n) {
            $this->db->query('DROP TABLE IF EXISTS ' . $t($n));
        }
        $this->db->query('CREATE TABLE ' . $t('pengguna') . ' (id_pengguna INTEGER PRIMARY KEY, nama TEXT, username TEXT, password TEXT, no_hp TEXT, role TEXT)');
        $this->db->query('CREATE TABLE ' . $t('kamar') . ' (id_kamar INTEGER PRIMARY KEY, nomor_kamar TEXT)');
        $this->db->query('CREATE TABLE ' . $t('pemesanan') . ' (id_pemesanan INTEGER PRIMARY KEY, id_pengguna INTEGER, id_kamar INTEGER)');
        $this->db->query('CREATE TABLE ' . $t('sewa') . ' (id_sewa INTEGER PRIMARY KEY, id_pemesanan INTEGER, status_sewa TEXT)');
        $this->db->query('CREATE TABLE ' . $t('laporan_kerusakan') . ' (id_laporan INTEGER PRIMARY KEY, id_pengguna INTEGER, deskripsi TEXT, foto TEXT,'
            . " tanggal_lapor TEXT, status_perbaikan TEXT DEFAULT 'dilaporkan', catatan_perbaikan TEXT, foto_perbaikan TEXT, tanggal_selesai TEXT)");

        // Budi di K-01 (sewa aktif), Sari di K-02 (sewa aktif), Dewi pernah di K-03 (sewa selesai)
        foreach ([[3, 'Budi Santoso', 'penghuni'], [4, 'Sari Lestari', 'penghuni'], [6, 'Dewi', 'penghuni']] as [$id, $nama, $peran]) {
            $this->db->table('pengguna')->insert(['id_pengguna' => $id, 'nama' => $nama, 'username' => 'u' . $id, 'password' => 'x', 'no_hp' => '0812', 'role' => $peran]);
        }
        foreach ([[1, 'K-01'], [2, 'K-02'], [3, 'K-03']] as [$id, $no]) {
            $this->db->table('kamar')->insert(['id_kamar' => $id, 'nomor_kamar' => $no]);
        }
        foreach ([[1, 3, 1], [2, 4, 2], [3, 6, 3]] as [$id, $p, $k]) {
            $this->db->table('pemesanan')->insert(['id_pemesanan' => $id, 'id_pengguna' => $p, 'id_kamar' => $k]);
        }
        foreach ([[1, 1, 'aktif'], [2, 2, 'aktif'], [3, 3, 'selesai']] as [$id, $pm, $st]) {
            $this->db->table('sewa')->insert(['id_sewa' => $id, 'id_pemesanan' => $pm, 'status_sewa' => $st]);
        }
        foreach ([
            [1, 3, 'Keran bocor', 'a.jpg', '2026-10-05 07:45:00', 'selesai', 'Diganti'],
            [2, 4, 'Lampu mati', 'b.jpg', '2026-11-18 21:10:00', 'dilaporkan', null],
            [3, 6, 'Pintu macet', 'c.jpg', '2026-09-01 08:00:00', 'selesai', null],
        ] as [$id, $p, $desk, $foto, $tgl, $st, $catatan]) {
            $this->db->table('laporan_kerusakan')->insert([
                'id_laporan' => $id, 'id_pengguna' => $p, 'deskripsi' => $desk, 'foto' => $foto,
                'tanggal_lapor' => $tgl, 'status_perbaikan' => $st, 'catatan_perbaikan' => $catatan,
            ]);
        }

        $this->model = new LaporanKerusakanModel();
    }

    public function testKamarDiambilDariSewaAktif(): void
    {
        $this->assertSame('K-01', $this->model->kamarAktif(3)['nomor_kamar']);
        $this->assertSame('K-02', $this->model->kamarAktif(4)['nomor_kamar']);
        $this->assertNull($this->model->kamarAktif(6), 'sewa sudah selesai, tidak boleh melapor');
        $this->assertNull($this->model->kamarAktif(999));
    }

    public function testHalamanBersamaTidakMemuatPelapor(): void
    {
        $baris = $this->model->bersama();
        $this->assertCount(3, $baris);
        foreach ($baris as $b) {
            $this->assertArrayNotHasKey('id_pengguna', $b);
            $this->assertArrayNotHasKey('nama', $b);
            $this->assertArrayNotHasKey('nama_pelapor', $b);
            $this->assertArrayNotHasKey('nomor_kamar', $b);
        }
        $this->assertSame([2, 1, 3], array_map('intval', array_column($baris, 'id_laporan')), 'terbaru di atas');
        $this->assertStringNotContainsString('Budi', json_encode($baris));
        $this->assertStringNotContainsString('Sari', json_encode($baris));
    }

    public function testLaporanSayaHanyaMilikSendiriTanpaIdPengguna(): void
    {
        $saya = $this->model->milikPengguna(4);
        $this->assertCount(1, $saya);
        $this->assertSame('Lampu mati', $saya[0]['deskripsi']);
        $this->assertArrayNotHasKey('id_pengguna', $saya[0]);
    }

    public function testHalamanPemilikMemuatPelaporDanKamar(): void
    {
        $semua = $this->model->daftarPemilik();
        $this->assertCount(3, $semua);
        $olehId = array_column($semua, null, 'id_laporan');
        $this->assertSame('Sari Lestari', $olehId[2]['nama_pelapor']);
        $this->assertSame('K-02', $olehId[2]['nomor_kamar']);
        $this->assertSame('K-01', $olehId[1]['nomor_kamar']);
        $this->assertNull($olehId[3]['nomor_kamar'], 'pelapor tanpa sewa aktif: kamar kosong');

        $this->assertCount(1, $this->model->daftarPemilik('dilaporkan'));
        $this->assertSame('Sari Lestari', $this->model->detailPemilik(2)['nama_pelapor']);
        $this->assertNull($this->model->detailPemilik(999));
        $this->assertSame(['dilaporkan' => 1, 'diproses' => 0, 'selesai' => 2], $this->model->hitungPerStatus());
    }

    public function testStatusTidakBisaMelompatAtauDiulang(): void
    {
        $this->assertTrue($this->model->pindahStatus(2, 'dilaporkan', 'diproses', ['catatan_perbaikan' => 'Teknisi besok']));
        $baris = $this->model->detailPemilik(2);
        $this->assertSame('diproses', $baris['status_perbaikan']);
        $this->assertSame('Teknisi besok', $baris['catatan_perbaikan']);

        // Permintaan kedua dengan status lama (dua pemilik menekan bersamaan) ditolak
        $this->assertFalse($this->model->pindahStatus(2, 'dilaporkan', 'diproses'));

        $this->assertTrue($this->model->pindahStatus(2, 'diproses', 'selesai', ['catatan_perbaikan' => 'Lampu diganti', 'tanggal_selesai' => '2026-11-29 10:00:00']));
        $this->assertSame('2026-11-29 10:00:00', $this->model->detailPemilik(2)['tanggal_selesai']);
        $this->assertFalse($this->model->pindahStatus(2, 'diproses', 'selesai'));
    }
}
