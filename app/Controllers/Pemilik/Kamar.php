<?php

namespace App\Controllers\Pemilik;

use App\Controllers\BaseController;
use App\Models\KamarModel;
use App\Models\MediaKamarModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Kelola kamar oleh pemilik: lihat, tambah, ubah, hapus, unggah/hapus media.
 * Semua route di sini dilindungi filter 'peran:pemilik' milik Sari (lihat Config/Routes.php).
 */
class Kamar extends BaseController
{
    protected KamarModel $kamarModel;
    protected MediaKamarModel $mediaModel;

    public function __construct()
    {
        $this->kamarModel = new KamarModel();
        $this->mediaModel = new MediaKamarModel();
        helper(['form', 'url']);
    }

    /** GET /pemilik/kamar */
    public function index()
    {
        return view('pemilik/kamar/index', [
            'judul'  => 'Daftar Kamar',
            'kamar'  => $this->kamarModel->getDaftarPemilik(),
        ]);
    }

    /** GET /pemilik/kamar/tambah */
    public function tambah()
    {
        return view('pemilik/kamar/form', [
            'judul'  => 'Tambah Kamar',
            'kamar'  => null,
            'media'  => [],
            'aturan' => MediaKamarModel::ATURAN,
        ]);
    }

    /** POST /pemilik/kamar/simpan */
    public function simpan()
    {
        $data = $this->ambilInput();

        if (! $this->validateData($data, $this->kamarModel->aturanValidasi())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $this->kamarModel->insert($data);
        $idBaru = (int) $this->kamarModel->getInsertID();

        return redirect()->to('/pemilik/kamar/' . $idBaru . '/ubah')
            ->with('sukses', 'Kamar ' . $data['nomor_kamar'] . ' berhasil ditambahkan. Silakan unggah foto atau video.');
    }

    /** GET /pemilik/kamar/{id}/ubah */
    public function ubah(int $id)
    {
        $kamar = $this->cariKamar($id);

        return view('pemilik/kamar/form', [
            'judul'  => 'Ubah Kamar ' . $kamar['nomor_kamar'],
            'kamar'  => $kamar,
            'media'  => $this->mediaModel->getByKamar($id),
            'aturan' => MediaKamarModel::ATURAN,
        ]);
    }

    /** POST /pemilik/kamar/{id}/perbarui */
    public function perbarui(int $id)
    {
        $this->cariKamar($id);
        $data = $this->ambilInput();

        if (! $this->validateData($data, $this->kamarModel->aturanValidasi($id))) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $this->kamarModel->update($id, $data);

        return redirect()->to('/pemilik/kamar')
            ->with('sukses', 'Kamar ' . $data['nomor_kamar'] . ' berhasil diperbarui.');
    }

    /** POST /pemilik/kamar/{id}/hapus */
    public function hapus(int $id)
    {
        $kamar = $this->cariKamar($id);

        // Ditolak kalau kamar sudah punya pemesanan
        if ($this->kamarModel->punyaPemesanan($id)) {
            return redirect()->to('/pemilik/kamar')
                ->with('gagal', 'Kamar ' . $kamar['nomor_kamar'] . ' tidak bisa dihapus karena sudah punya pemesanan.');
        }

        // Hapus file fisik dulu. Baris media_kamar ikut terhapus lewat ON DELETE CASCADE.
        foreach ($this->mediaModel->getByKamar($id) as $m) {
            MediaKamarModel::hapusFile($m['file']);
        }
        $this->kamarModel->delete($id);

        return redirect()->to('/pemilik/kamar')
            ->with('sukses', 'Kamar ' . $kamar['nomor_kamar'] . ' berhasil dihapus.');
    }

    /** POST /pemilik/kamar/{id}/media  (input name="media[]", boleh banyak file) */
    public function unggahMedia(int $id)
    {
        $this->cariKamar($id);
        $files  = $this->request->getFileMultiple('media') ?? [];
        $errors = [];
        $siap   = [];

        // 1) Validasi semua file dulu. Kalau ada satu yang salah, tidak ada yang disimpan.
        foreach ($files as $file) {
            if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $nama = $file->getClientName();

            if (! $file->isValid()) {
                $errors[] = $nama . ': ' . $file->getErrorString();
                continue;
            }

            $jenis = $this->tentukanJenis($file->getMimeType());
            if ($jenis === null) {
                $errors[] = $nama . ': tipe file tidak diizinkan. Gunakan '
                    . MediaKamarModel::ATURAN['foto']['label'] . ' atau '
                    . MediaKamarModel::ATURAN['video']['label'] . '.';
                continue;
            }

            if ($file->getSize() > MediaKamarModel::ATURAN[$jenis]['maks']) {
                $errors[] = $nama . ': ukuran terlalu besar (' . MediaKamarModel::ATURAN[$jenis]['label'] . ').';
                continue;
            }

            $siap[] = ['file' => $file, 'jenis' => $jenis];
        }

        if ($siap === [] && $errors === []) {
            $errors[] = 'Pilih minimal satu file.';
        }
        if ($errors !== []) {
            return redirect()->to('/pemilik/kamar/' . $id . '/ubah')->with('errors', $errors);
        }

        // 2) Semua lolos: pindahkan file dan simpan NAMA FILE saja di database.
        foreach ($siap as $item) {
            $namaBaru = $item['file']->getRandomName();
            $item['file']->move(FCPATH . MediaKamarModel::FOLDER, $namaBaru);

            $this->mediaModel->insert([
                'id_kamar' => $id,
                'jenis'    => $item['jenis'],
                'file'     => $namaBaru,
            ]);
        }

        return redirect()->to('/pemilik/kamar/' . $id . '/ubah')
            ->with('sukses', count($siap) . ' file berhasil diunggah.');
    }

    /** POST /pemilik/media/{idMedia}/hapus */
    public function hapusMedia(int $idMedia)
    {
        $media = $this->mediaModel->find($idMedia);
        if ($media === null) {
            throw PageNotFoundException::forPageNotFound('Media tidak ditemukan.');
        }

        MediaKamarModel::hapusFile($media['file']);
        $this->mediaModel->delete($idMedia);

        return redirect()->to('/pemilik/kamar/' . $media['id_kamar'] . '/ubah')
            ->with('sukses', 'Media berhasil dihapus.');
    }

    // ---------------------------------------------------------------
    // Fungsi bantu
    // ---------------------------------------------------------------

    private function ambilInput(): array
    {
        return [
            'nomor_kamar'        => trim((string) $this->request->getPost('nomor_kamar')),
            'fasilitas'          => trim((string) $this->request->getPost('fasilitas')),
            'harga_sewa_tahunan' => (string) $this->request->getPost('harga_sewa_tahunan'),
            'status_kamar'       => (string) $this->request->getPost('status_kamar'),
        ];
    }

    private function cariKamar(int $id): array
    {
        $kamar = $this->kamarModel->find($id);
        if ($kamar === null) {
            throw PageNotFoundException::forPageNotFound('Kamar tidak ditemukan.');
        }

        return $kamar;
    }

    private function tentukanJenis(string $mime): ?string
    {
        foreach (MediaKamarModel::ATURAN as $jenis => $aturan) {
            if (in_array($mime, $aturan['mime'], true)) {
                return $jenis;
            }
        }

        return null;
    }
}
