<?php

namespace App\Controllers\Pemilik;

use App\Controllers\BaseController;
use App\Models\PengumumanModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

/**
 * Kelola pengumuman dan peraturan kos oleh pemilik (tambah, ubah, hapus).
 * Route dilindungi filter 'peran:pemilik' di Config/Routes.php.
 * Menulis ke database hanya lewat PengumumanModel, dan semua aksi ubah memakai POST + CSRF.
 */
class Pengumuman extends BaseController
{
    protected $helpers = ['form', 'url', 'tanggal', 'kos'];

    /** GET /pemilik/pengumuman?jenis=pengumuman|peraturan */
    public function index()
    {
        $model = model(PengumumanModel::class);
        $jenis = (string) $this->request->getGet('jenis');
        if (! array_key_exists($jenis, PengumumanModel::JENIS)) {
            $jenis = '';
        }

        return view('pemilik/pengumuman/index', [
            'judul'  => 'Pengumuman',
            'jenis'  => $jenis,
            'daftar' => $model->daftar($jenis),
            'jumlah' => $model->hitungPerJenis(),
        ]);
    }

    /** GET /pemilik/pengumuman/tambah?jenis=peraturan */
    public function tambah()
    {
        $jenis = (string) $this->request->getGet('jenis');

        return view('pemilik/pengumuman/form', [
            'judul' => 'Tambah Pengumuman',
            'item'  => ['jenis' => array_key_exists($jenis, PengumumanModel::JENIS) ? $jenis : 'pengumuman', 'isi' => ''],
            'ubah'  => false,
        ]);
    }

    /** POST /pemilik/pengumuman/simpan */
    public function simpan(): RedirectResponse
    {
        if (! $this->validate(PengumumanModel::aturan(), PengumumanModel::pesanAturan())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $jenis = (string) $this->request->getPost('jenis');

        try {
            $berhasil = model(PengumumanModel::class)->insert([
                'jenis'          => $jenis,
                'isi'            => trim((string) $this->request->getPost('isi')),
                // Waktu WIB, bukan zona waktu server database
                'tanggal_terbit' => kos_now()->format('Y-m-d H:i:s'),
            ]);
            if ($berhasil === false) {
                throw new \RuntimeException('insert pengumuman ditolak model');
            }
        } catch (Throwable $e) {
            log_message('error', 'Tambah pengumuman gagal: ' . $e->getMessage());

            return redirect()->back()->withInput()->with('gagal', 'Gagal menyimpan. Tidak ada data yang berubah, coba lagi.');
        }

        return redirect()->to('pemilik/pengumuman')->with('sukses', PengumumanModel::JENIS[$jenis] . ' berhasil ditambahkan.');
    }

    /** GET /pemilik/pengumuman/{id}/ubah */
    public function ubah(int $id)
    {
        return view('pemilik/pengumuman/form', [
            'judul' => 'Ubah Pengumuman',
            'item'  => $this->cari($id),
            'ubah'  => true,
        ]);
    }

    /** POST /pemilik/pengumuman/{id}/perbarui. Tanggal terbit tidak berubah. */
    public function perbarui(int $id): RedirectResponse
    {
        $this->cari($id);

        if (! $this->validate(PengumumanModel::aturan(), PengumumanModel::pesanAturan())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $jenis = (string) $this->request->getPost('jenis');

        try {
            $berhasil = model(PengumumanModel::class)->update($id, [
                'jenis' => $jenis,
                'isi'   => trim((string) $this->request->getPost('isi')),
            ]);
            if ($berhasil === false) {
                throw new \RuntimeException('update pengumuman ditolak model');
            }
        } catch (Throwable $e) {
            log_message('error', 'Ubah pengumuman ' . $id . ' gagal: ' . $e->getMessage());

            return redirect()->back()->withInput()->with('gagal', 'Gagal menyimpan. Tidak ada data yang berubah, coba lagi.');
        }

        return redirect()->to('pemilik/pengumuman')->with('sukses', PengumumanModel::JENIS[$jenis] . ' berhasil diperbarui.');
    }

    /** POST /pemilik/pengumuman/{id}/hapus */
    public function hapus(int $id): RedirectResponse
    {
        $item = $this->cari($id);

        try {
            model(PengumumanModel::class)->delete($id);
        } catch (Throwable $e) {
            log_message('error', 'Hapus pengumuman ' . $id . ' gagal: ' . $e->getMessage());

            return redirect()->to('pemilik/pengumuman')->with('gagal', 'Gagal menghapus. Tidak ada data yang berubah, coba lagi.');
        }

        return redirect()->to('pemilik/pengumuman')->with('sukses', PengumumanModel::JENIS[$item['jenis']] . ' berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function cari(int $id): array
    {
        $item = model(PengumumanModel::class)->find($id);
        if ($item === null) {
            throw PageNotFoundException::forPageNotFound('Pengumuman tidak ditemukan.');
        }

        return $item;
    }
}
