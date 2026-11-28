<?php

namespace App\Controllers\Pemilik;

use App\Controllers\BaseController;
use App\Models\LaporanKerusakanModel;
use App\Models\NotifikasiModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use Throwable;

/**
 * Pemilik memperbarui status perbaikan kerusakan.
 * Route dilindungi filter 'peran:pemilik' di Config/Routes.php.
 *
 *   GET  pemilik/kerusakan?status=...     daftar (dengan nama pelapor dan kamar)
 *   GET  pemilik/kerusakan/{id}           detail dan form ubah status
 *   POST pemilik/kerusakan/{id}/status    dilaporkan -> diproses -> selesai
 *
 * Status hanya maju satu langkah. Penghuni pelapor menerima notifikasi setiap status berubah.
 */
class Kerusakan extends BaseController
{
    protected $helpers = ['form', 'url', 'tanggal', 'kos'];

    /** GET /pemilik/kerusakan */
    public function index()
    {
        $model  = model(LaporanKerusakanModel::class);
        $status = (string) $this->request->getGet('status');
        if (! array_key_exists($status, LaporanKerusakanModel::STATUS)) {
            $status = '';
        }

        return view('pemilik/kerusakan/index', [
            'judul'   => 'Status Kerusakan',
            'status'  => $status,
            'laporan' => $model->daftarPemilik($status),
            'jumlah'  => $model->hitungPerStatus(),
        ]);
    }

    /** GET /pemilik/kerusakan/{id} */
    public function detail(int $id)
    {
        $laporan = $this->cari($id);

        return view('pemilik/kerusakan/detail', [
            'judul'   => 'Detail Kerusakan',
            'laporan' => $laporan,
            'berikut' => LaporanKerusakanModel::statusBerikut($laporan['status_perbaikan']),
        ]);
    }

    /** POST /pemilik/kerusakan/{id}/status */
    public function ubahStatus(int $id): RedirectResponse
    {
        $model   = model(LaporanKerusakanModel::class);
        $laporan = $this->cari($id);
        $dari    = $laporan['status_perbaikan'];
        $ke      = (string) $this->request->getPost('status');
        $kembali = 'pemilik/kerusakan/' . $id;

        if (! LaporanKerusakanModel::bolehPindah($dari, $ke)) {
            return redirect()->to($kembali)->with(
                'gagal',
                $dari === 'selesai'
                    ? 'Laporan ini sudah selesai dan tidak bisa diubah lagi.'
                    : 'Status hanya bisa maju satu langkah: ' . LaporanKerusakanModel::STATUS[$dari]
                        . ' lalu ' . LaporanKerusakanModel::STATUS[LaporanKerusakanModel::statusBerikut($dari)] . '.'
            );
        }

        $galat = [];
        if (! $this->validate(LaporanKerusakanModel::aturanStatus($ke), LaporanKerusakanModel::pesanStatus())) {
            $galat = $this->validator->getErrors();
        }

        $foto      = $this->request->getFile('foto_perbaikan');
        $galatFoto = LaporanKerusakanModel::galatFoto($foto, false, 'Foto perbaikan');
        if ($galatFoto !== null) {
            $galat['foto_perbaikan'] = $galatFoto;
        }

        if ($galat !== []) {
            return redirect()->back()->withInput()->with('errors', $galat);
        }

        $catatan  = trim((string) $this->request->getPost('catatan_perbaikan'));
        $tambahan = ['catatan_perbaikan' => $catatan !== '' ? $catatan : $laporan['catatan_perbaikan']];
        if ($ke === 'selesai') {
            $tambahan['tanggal_selesai'] = kos_now()->format('Y-m-d H:i:s');
        }

        $fotoBaru = null;
        try {
            if ($foto !== null && $foto->getError() !== UPLOAD_ERR_NO_FILE) {
                $fotoBaru                   = LaporanKerusakanModel::simpanFoto($foto);
                $tambahan['foto_perbaikan'] = $fotoBaru;
            }

            if (! $model->pindahStatus($id, $dari, $ke, $tambahan)) {
                LaporanKerusakanModel::hapusFoto($fotoBaru);

                return redirect()->to($kembali)
                    ->with('gagal', 'Status laporan sudah diubah oleh orang lain. Muat ulang halaman ini.');
            }
        } catch (Throwable $e) {
            LaporanKerusakanModel::hapusFoto($fotoBaru);
            log_message('error', 'Ubah status laporan ' . $id . ' gagal: ' . $e->getMessage());

            return redirect()->back()->withInput()
                ->with('gagal', 'Status gagal diubah dan tidak ada data yang berubah. Coba lagi.');
        }

        // Foto perbaikan lama sudah tergantikan: hapus filenya
        if ($fotoBaru !== null && ! empty($laporan['foto_perbaikan'])) {
            LaporanKerusakanModel::hapusFoto($laporan['foto_perbaikan']);
        }

        // Beri tahu penghuni pelapor. Kalau gagal, perubahan status tetap tersimpan.
        try {
            $ringkas = mb_strimwidth(trim((string) $laporan['deskripsi']), 0, 60, '...');
            $pesan   = $ke === 'diproses'
                ? 'Laporan kerusakanmu ("' . $ringkas . '") sedang diproses.'
                : 'Laporan kerusakanmu ("' . $ringkas . '") sudah selesai diperbaiki.';
            model(NotifikasiModel::class)->kirim((int) $laporan['id_pengguna'], 'aduan', $pesan, null, $id);
        } catch (Throwable $e) {
            log_message('error', 'Notifikasi status laporan ' . $id . ' gagal: ' . $e->getMessage());
        }

        return redirect()->to($kembali)
            ->with('sukses', 'Status laporan menjadi ' . LaporanKerusakanModel::STATUS[$ke] . '. Penghuni sudah diberi tahu.');
    }

    /** @return array<string, mixed> */
    private function cari(int $id): array
    {
        $laporan = model(LaporanKerusakanModel::class)->detailPemilik($id);
        if ($laporan === null) {
            throw PageNotFoundException::forPageNotFound('Laporan tidak ditemukan.');
        }

        return $laporan;
    }
}
