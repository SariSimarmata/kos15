<?php

namespace App\Controllers\Pemilik;

use App\Controllers\BaseController;
use App\Models\KamarModel;
use App\Models\PenggunaModel;
use App\Models\SewaModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use DateTime;
use DateTimeZone;
use RuntimeException;
use Throwable;

/**
 * Data penghuni oleh pemilik (Kartu 4 Sprint 4, KF-03).
 *
 * Penghuni lama dibuat lewat alur yang sama dengan pemesanan:
 * akun penghuni -> pemesanan approved -> sewa -> kamar terisi, dalam satu transaksi.
 * Semua route dilindungi filter 'peran:pemilik' (lihat Config/Routes.php).
 */
class Penghuni extends BaseController
{
    protected SewaModel $sewaModel;

    public function __construct()
    {
        $this->sewaModel = new SewaModel();
        helper(['form', 'url', 'tanggal']);
    }

    /** GET /pemilik/penghuni?cari=... */
    public function index()
    {
        $cari = trim((string) $this->request->getGet('cari'));

        return view('pemilik/penghuni/index', [
            'judul'    => 'Data Penghuni',
            'cari'     => $cari,
            'penghuni' => $this->sewaModel->daftarPenghuni($cari),
        ]);
    }

    /** GET /pemilik/penghuni/tambah */
    public function tambah()
    {
        return view('pemilik/penghuni/form', [
            'judul'    => 'Tambah Penghuni',
            'penghuni' => null,
            'kamar'    => $this->pilihanKamar(null),
        ]);
    }

    /** POST /pemilik/penghuni/simpan */
    public function simpan(): RedirectResponse
    {
        $aturan = $this->aturanAkun(null) + [
            'id_kamar'      => ['label' => 'Kamar', 'rules' => 'required|is_natural_no_zero'],
            'tanggal_mulai' => ['label' => 'Tanggal mulai sewa', 'rules' => 'required|valid_date[Y-m-d]'],
            'status_sewa'   => ['label' => 'Status sewa', 'rules' => 'required|in_list[aktif,menunggu_pembayaran]'],
        ];

        if (! $this->validate($aturan, $this->pesanValidasi())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $idKamar = (int) $this->request->getPost('id_kamar');
        $kamar   = model(KamarModel::class)->find($idKamar);
        if ($kamar === null) {
            return redirect()->back()->withInput()->with('errors', ['id_kamar' => 'Kamar tidak ditemukan.']);
        }

        $mulai    = (string) $this->request->getPost('tanggal_mulai');
        $berakhir = SewaModel::tanggalBerakhir($mulai);
        $nama     = trim((string) $this->request->getPost('nama'));

        // Sewa yang didaftarkan sebagai aktif atau menunggu pembayaran harus belum berakhir
        if ($berakhir < $this->hariIni()) {
            return redirect()->back()->withInput()->with('errors', [
                'tanggal_mulai' => 'Sewa dari tanggal ini sudah berakhir pada ' . tanggal_indonesia($berakhir)
                    . '. Pilih tanggal mulai yang membuat sewa masih berjalan.',
            ]);
        }

        $db = db_connect();
        $db->transBegin();

        try {
            // 1. Akun penghuni (kata sandi di-hash oleh PenggunaModel)
            $penggunaModel = model(PenggunaModel::class);
            $this->harusBerhasil($penggunaModel->insert([
                'nama'     => $nama,
                'username' => trim((string) $this->request->getPost('username')),
                'no_hp'    => trim((string) $this->request->getPost('no_hp')),
                'password' => (string) $this->request->getPost('password'),
                'role'     => 'penghuni',
            ]), 'buat akun');
            $idPengguna = (int) $penggunaModel->getInsertID();

            // 2. Kunci kamar: hanya berhasil jika kamar masih kosong
            $this->harusBerhasil($db->table('kamar')
                ->where('id_kamar', $idKamar)
                ->where('status_kamar', 'kosong')
                ->update(['status_kamar' => 'terisi']), 'ubah status kamar');
            if ($db->affectedRows() !== 1) {
                $db->transRollback();

                return redirect()->back()->withInput()->with('errors', [
                    'id_kamar' => 'Kamar ' . $kamar['nomor_kamar'] . ' sudah terisi. Pilih kamar lain yang kosong.',
                ]);
            }

            // 3. Pemesanan yang langsung disetujui, sama seperti alur pemesanan biasa
            $this->harusBerhasil($db->table('pemesanan')->insert([
                'id_pengguna'        => $idPengguna,
                'id_kamar'           => $idKamar,
                'tanggal_mulai_sewa' => $mulai,
                'tanggal_pengajuan'  => date('Y-m-d H:i:s'),
                'status_pengajuan'   => 'approved',
            ]), 'buat pemesanan');
            $idPemesanan = (int) $db->insertID();

            // 4. Sewa satu tahun
            $this->harusBerhasil($this->sewaModel->insert([
                'id_pemesanan'     => $idPemesanan,
                'tanggal_mulai'    => $mulai,
                'tanggal_berakhir' => $berakhir,
                'status_sewa'      => (string) $this->request->getPost('status_sewa'),
            ]), 'buat sewa');

            $this->harusBerhasil($db->transStatus(), 'transaksi');
            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Tambah penghuni gagal: ' . $e->getMessage());

            return redirect()->back()->withInput()
                ->with('gagal', 'Penghuni gagal disimpan dan tidak ada data yang berubah. Coba lagi.');
        }

        return redirect()->to('pemilik/penghuni')->with(
            'sukses',
            $nama . ' ditambahkan sebagai penghuni kamar ' . $kamar['nomor_kamar']
            . ', sewa ' . tanggal_indonesia($mulai) . ' sampai ' . tanggal_indonesia($berakhir) . '.'
        );
    }

    /** GET /pemilik/penghuni/{id}/ubah */
    public function ubah(int $id)
    {
        $penghuni = $this->cariPenghuni($id);

        return view('pemilik/penghuni/form', [
            'judul'    => 'Ubah Penghuni',
            'penghuni' => $penghuni,
            'kamar'    => $this->pilihanKamar($penghuni['id_kamar'] ? (int) $penghuni['id_kamar'] : null),
        ]);
    }

    /** POST /pemilik/penghuni/{id}/perbarui */
    public function perbarui(int $id): RedirectResponse
    {
        $lama    = $this->cariPenghuni($id);
        $adaSewa = $lama['id_sewa'] !== null;

        $aturan = $this->aturanAkun($id);
        if ($adaSewa) {
            $aturan += [
                'id_kamar'         => ['label' => 'Kamar', 'rules' => 'required|is_natural_no_zero'],
                'tanggal_mulai'    => ['label' => 'Tanggal mulai sewa', 'rules' => 'required|valid_date[Y-m-d]'],
                'tanggal_berakhir' => ['label' => 'Tanggal berakhir sewa', 'rules' => 'required|valid_date[Y-m-d]'],
                'status_sewa'      => [
                    'label' => 'Status sewa',
                    'rules' => 'required|in_list[' . implode(',', array_keys(SewaModel::STATUS)) . ']',
                ],
            ];
        }

        if (! $this->validate($aturan, $this->pesanValidasi())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $akun = [
            'nama'     => trim((string) $this->request->getPost('nama')),
            'username' => trim((string) $this->request->getPost('username')),
            'no_hp'    => trim((string) $this->request->getPost('no_hp')),
        ];
        // Kata sandi hanya diganti jika diisi
        $sandi = (string) $this->request->getPost('password');
        if ($sandi !== '') {
            $akun['password'] = $sandi;
        }

        if ($adaSewa) {
            $mulai    = (string) $this->request->getPost('tanggal_mulai');
            $berakhir = (string) $this->request->getPost('tanggal_berakhir');
            $status   = (string) $this->request->getPost('status_sewa');

            if ($berakhir <= $mulai) {
                return redirect()->back()->withInput()->with('errors', [
                    'tanggal_berakhir' => 'Tanggal berakhir harus setelah tanggal mulai.',
                ]);
            }

            // Sewa yang masih berjalan tidak boleh punya tanggal berakhir yang sudah lewat
            if (SewaModel::berjalan($status) && $berakhir < $this->hariIni()) {
                return redirect()->back()->withInput()->with('errors', [
                    'tanggal_berakhir' => 'Sewa berstatus ' . SewaModel::STATUS[$status]
                        . ' harus berakhir hari ini atau setelahnya. Jika masa sewa sudah lewat, ubah status menjadi Selesai.',
                ]);
            }
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $this->harusBerhasil(model(PenggunaModel::class)->update($id, $akun), 'ubah akun');

            if ($adaSewa) {
                $kamarLama   = (int) $lama['id_kamar'];
                $kamarBaru   = (int) $this->request->getPost('id_kamar');
                $berjalanLama = SewaModel::berjalan($lama['status_sewa']);
                $berjalanBaru = SewaModel::berjalan($status);

                // Kamar lama dilepas dulu, lalu kamar baru dikunci jika sewa masih berjalan.
                // Cara ini juga benar untuk pindah kamar dan untuk sewa yang selesai atau batal.
                if ($berjalanLama) {
                    $this->harusBerhasil($db->table('kamar')
                        ->where('id_kamar', $kamarLama)
                        ->update(['status_kamar' => 'kosong']), 'lepas kamar lama');
                }
                if ($berjalanBaru) {
                    $this->harusBerhasil($db->table('kamar')
                        ->where('id_kamar', $kamarBaru)
                        ->where('status_kamar', 'kosong')
                        ->update(['status_kamar' => 'terisi']), 'kunci kamar baru');
                    if ($db->affectedRows() !== 1) {
                        $db->transRollback();

                        return redirect()->back()->withInput()->with('errors', [
                            'id_kamar' => 'Kamar yang dipilih sudah terisi penghuni lain.',
                        ]);
                    }
                }

                if ($kamarBaru !== $kamarLama) {
                    $this->harusBerhasil($db->table('pemesanan')
                        ->where('id_pemesanan', (int) $lama['id_pemesanan'])
                        ->update(['id_kamar' => $kamarBaru]), 'pindah kamar');
                }

                $this->harusBerhasil($this->sewaModel->update((int) $lama['id_sewa'], [
                    'tanggal_mulai'    => $mulai,
                    'tanggal_berakhir' => $berakhir,
                    'status_sewa'      => $status,
                ]), 'ubah sewa');
            }

            $this->harusBerhasil($db->transStatus(), 'transaksi');
            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Ubah penghuni ' . $id . ' gagal: ' . $e->getMessage());

            return redirect()->back()->withInput()
                ->with('gagal', 'Perubahan gagal disimpan dan tidak ada data yang berubah. Coba lagi.');
        }

        return redirect()->to('pemilik/penghuni')->with('sukses', 'Data ' . $akun['nama'] . ' berhasil diperbarui.');
    }

    /**
     * POST /pemilik/penghuni/{id}/hapus
     * Ditolak jika masih ada sewa berjalan atau tagihan belum lunas.
     * Jika boleh, akun beserta seluruh riwayat sewanya dihapus dalam satu transaksi.
     */
    public function hapus(int $id): RedirectResponse
    {
        $penghuni = $this->cariPenghuni($id);

        $alasan = $this->sewaModel->alasanTidakBisaHapus($id);
        if ($alasan !== null) {
            return redirect()->to('pemilik/penghuni')
                ->with('gagal', $penghuni['nama'] . ' tidak bisa dihapus karena ' . $alasan);
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $idPemesanan = array_column($db->table('pemesanan')->select('id_pemesanan')
                ->where('id_pengguna', $id)->get()->getResultArray(), 'id_pemesanan');
            $idSewa = $idPemesanan === [] ? [] : array_column($db->table('sewa')->select('id_sewa')
                ->whereIn('id_pemesanan', $idPemesanan)->get()->getResultArray(), 'id_sewa');
            $idTagihan = $idSewa === [] ? [] : array_column($db->table('tagihan')->select('id_tagihan')
                ->whereIn('id_sewa', $idSewa)->get()->getResultArray(), 'id_tagihan');

            // Urutan mengikuti foreign key: anak dulu, baru induk
            if ($idTagihan !== []) {
                $db->table('pembayaran')->whereIn('id_tagihan', $idTagihan)->delete();
            }
            if ($idSewa !== []) {
                foreach (['tagihan_bulanan', 'piket', 'kunjungan_menginap', 'pengajuan_perpanjangan', 'tagihan'] as $tabel) {
                    $db->table($tabel)->whereIn('id_sewa', $idSewa)->delete();
                }
                $db->table('sewa')->whereIn('id_sewa', $idSewa)->delete();
            }
            $db->table('laporan_kerusakan')->where('id_pengguna', $id)->delete();
            $db->table('pemesanan')->where('id_pengguna', $id)->delete();
            $db->table('notifikasi')->where('id_pengguna', $id)->delete();
            $db->table('pengguna')->where('id_pengguna', $id)->delete();

            $this->harusBerhasil($db->transStatus(), 'transaksi');
            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Hapus penghuni ' . $id . ' gagal: ' . $e->getMessage());

            return redirect()->to('pemilik/penghuni')
                ->with('gagal', $penghuni['nama'] . ' gagal dihapus dan tidak ada data yang berubah.');
        }

        return redirect()->to('pemilik/penghuni')->with('sukses', $penghuni['nama'] . ' berhasil dihapus.');
    }

    // ------------------------------------------------------------------

    private function cariPenghuni(int $id): array
    {
        $penghuni = $this->sewaModel->penghuni($id);
        if ($penghuni === null) {
            throw PageNotFoundException::forPageNotFound('Penghuni tidak ditemukan.');
        }

        return $penghuni;
    }

    /** Kamar kosong, ditambah kamar penghuni saat ini (untuk form ubah). */
    private function pilihanKamar(?int $kamarSekarang): array
    {
        $builder = model(KamarModel::class)->groupStart()->where('status_kamar', 'kosong');
        if ($kamarSekarang !== null) {
            $builder->orWhere('id_kamar', $kamarSekarang);
        }

        return $builder->groupEnd()->orderBy('nomor_kamar', 'ASC')->findAll();
    }

    private function aturanAkun(?int $id): array
    {
        $unik = $id === null ? 'is_unique[pengguna.username]' : 'is_unique[pengguna.username,id_pengguna,' . $id . ']';

        return [
            'nama'     => ['label' => 'Nama', 'rules' => 'required|min_length[3]|max_length[100]'],
            'username' => ['label' => 'Username', 'rules' => 'required|alpha_numeric|min_length[4]|max_length[50]|' . $unik],
            'no_hp'    => ['label' => 'Nomor HP', 'rules' => 'required|regex_match[/^08[0-9]{8,12}$/]'],
            'password' => [
                'label' => 'Kata sandi',
                'rules' => ($id === null ? 'required' : 'permit_empty') . '|min_length[8]|max_length[72]',
            ],
        ];
    }

    private function pesanValidasi(): array
    {
        return [
            'nama'     => [
                'required'   => 'Nama wajib diisi.',
                'min_length' => 'Nama minimal 3 karakter.',
                'max_length' => 'Nama maksimal 100 karakter.',
            ],
            'username' => [
                'required'      => 'Username wajib diisi.',
                'alpha_numeric' => 'Username hanya boleh berisi huruf dan angka.',
                'min_length'    => 'Username minimal 4 karakter.',
                'max_length'    => 'Username maksimal 50 karakter.',
                'is_unique'     => 'Username sudah dipakai, pilih yang lain.',
            ],
            'no_hp'    => [
                'required'    => 'Nomor HP wajib diisi.',
                'regex_match' => 'Nomor HP harus diawali 08 dan terdiri dari 10 sampai 14 angka.',
            ],
            'password' => [
                'required'   => 'Kata sandi awal wajib diisi.',
                'min_length' => 'Kata sandi minimal 8 karakter.',
                'max_length' => 'Kata sandi maksimal 72 karakter.',
            ],
            'id_kamar' => [
                'required'           => 'Pilih kamar.',
                'is_natural_no_zero' => 'Pilih kamar.',
            ],
            'tanggal_mulai' => [
                'required'   => 'Tanggal mulai sewa wajib diisi.',
                'valid_date' => 'Tanggal mulai sewa tidak valid.',
            ],
            'tanggal_berakhir' => [
                'required'   => 'Tanggal berakhir sewa wajib diisi.',
                'valid_date' => 'Tanggal berakhir sewa tidak valid.',
            ],
            'status_sewa' => [
                'required' => 'Pilih status sewa.',
                'in_list'  => 'Status sewa tidak valid.',
            ],
        ];
    }

    /** Tanggal hari ini (Y-m-d) menurut waktu Indonesia barat. */
    private function hariIni(): string
    {
        return (new DateTime('today', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
    }

    /** Lempar exception jika satu langkah query gagal, supaya transaksi dibatalkan. */
    private function harusBerhasil($hasil, string $langkah): void
    {
        if ($hasil === false) {
            throw new RuntimeException('Langkah gagal: ' . $langkah);
        }
    }
}
