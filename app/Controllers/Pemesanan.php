<?php

namespace App\Controllers;

use App\Models\KamarModel;
use App\Models\NotifikasiModel;
use App\Models\PemesananModel;
use App\Models\PenggunaModel;
use App\Models\SewaModel;
use CodeIgniter\HTTP\RedirectResponse;
use DateTime;
use DateTimeZone;
use Throwable;

/**
 * Kartu 8: pemesanan kamar oleh calon penghuni dan persetujuan oleh pemilik.
 * Hak akses dipasang di Routes.php (filter peran), bukan di sini.
 *
 * Calon penghuni : form, simpan, status
 * Pemilik        : daftar, setujui, tolak
 */
class Pemesanan extends BaseController
{
    private const ZONA_WAKTU = 'Asia/Jakarta';

    protected $helpers = ['tanggal'];

    // ------------------------------------------------------------------
    //  Calon penghuni
    // ------------------------------------------------------------------

    /** Form pemesanan. $idKamar (opsional) memilih kamar dari tombol "Pesan" di katalog. */
    public function form(?string $idKamar = null)
    {
        $kamarKosong = model(KamarModel::class)
            ->where('status_kamar', 'kosong')
            ->orderBy('nomor_kamar', 'ASC')
            ->findAll();

        $terpilih = (int) (old('id_kamar') ?: $idKamar);
        $infoKamarTerisi = null;

        // Kamar dari katalog yang ternyata sudah terisi: beri tahu, jangan diam-diam diganti
        if ($idKamar !== null && !in_array($terpilih, array_column($kamarKosong, 'id_kamar'), true)) {
            $kamar = model(KamarModel::class)->find($terpilih);
            $infoKamarTerisi = $kamar
                ? 'Kamar ' . $kamar['nomor_kamar'] . ' sudah terisi. Silakan pilih kamar lain yang masih kosong.'
                : 'Kamar yang kamu pilih tidak ditemukan. Silakan pilih dari daftar kamar kosong.';
            $terpilih = 0;
        }

        return view('pemesanan/form', [
            'judul'            => 'Pesan Kamar',
            'kamarKosong'      => $kamarKosong,
            'terpilih'         => $terpilih,
            'infoKamarTerisi'  => $infoKamarTerisi,
            'tanggalMinimal'   => $this->hariIni()->format('Y-m-d'),
        ]);
    }

    /** Simpan pemesanan berstatus pending, lalu kirim notifikasi ke pemilik. */
    public function simpan(): RedirectResponse
    {
        $aturan = [
            'id_kamar'           => 'required|is_natural_no_zero',
            'tanggal_mulai_sewa' => 'required|valid_date[Y-m-d]',
        ];
        $pesan = [
            'id_kamar' => [
                'required'           => 'Pilih kamar terlebih dahulu.',
                'is_natural_no_zero' => 'Pilih kamar terlebih dahulu.',
            ],
            'tanggal_mulai_sewa' => [
                'required'   => 'Tanggal mulai sewa wajib diisi.',
                'valid_date' => 'Tanggal mulai sewa tidak valid.',
            ],
        ];

        if (!$this->validate($aturan, $pesan)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $idKamar = (int) $this->request->getPost('id_kamar');
        $tanggal = (string) $this->request->getPost('tanggal_mulai_sewa');

        if ($tanggal < $this->hariIni()->format('Y-m-d')) {
            return redirect()->back()->withInput()
                ->with('errors', ['tanggal_mulai_sewa' => 'Tanggal mulai sewa tidak boleh di masa lalu.']);
        }

        $idPengguna = (int) session('id_pengguna');
        $pemesananModel = model(PemesananModel::class);

        // Satu pengajuan pending per calon, supaya pemilik tidak menyetujui dua kamar untuk orang yang sama
        $sudahPending = $pemesananModel
            ->where('id_pengguna', $idPengguna)
            ->where('status_pengajuan', 'pending')
            ->first();
        if ($sudahPending !== null) {
            return redirect()->to('pemesanan/status')->with(
                'gagal',
                'Kamu masih punya pengajuan ' . PemesananModel::nomor((int) $sudahPending['id_pemesanan'])
                . ' yang menunggu keputusan pemilik. Tunggu hasilnya sebelum memesan kamar lain.'
            );
        }

        $kamar = model(KamarModel::class)->find($idKamar);
        if ($kamar === null) {
            return redirect()->back()->withInput()
                ->with('errors', ['id_kamar' => 'Kamar yang dipilih tidak ditemukan.']);
        }
        if ($kamar['status_kamar'] !== 'kosong') {
            return redirect()->back()->withInput()
                ->with('errors', ['id_kamar' => 'Kamar ' . $kamar['nomor_kamar'] . ' sudah terisi. Pilih kamar lain.']);
        }

        $db = db_connect();
        $db->transStart();

        try {
            $this->harusBerhasil($pemesananModel->insert([
                'id_pengguna'        => $idPengguna,
                'id_kamar'           => $idKamar,
                'tanggal_mulai_sewa' => $tanggal,
                'tanggal_pengajuan'  => date('Y-m-d H:i:s'),
                'status_pengajuan'   => 'pending',
            ]), 'simpan pemesanan');
            $idPemesanan = (int) $pemesananModel->getInsertID();
            $nomor = PemesananModel::nomor($idPemesanan);

            // Semua akun pemilik menerima notifikasi pemesanan baru
            $pemilik = model(PenggunaModel::class)->where('role', 'pemilik')->findAll();
            $isi = 'Pemesanan baru ' . $nomor . ' dari ' . session('nama')
                . ' untuk kamar ' . $kamar['nomor_kamar'] . ', mulai sewa ' . tanggal_indonesia($tanggal) . '.';
            foreach ($pemilik as $p) {
                $this->kirimNotifikasi((int) $p['id_pengguna'], $isi);
            }

            $db->transComplete();
            if ($db->transStatus() === false) {
                throw new \RuntimeException('Transaksi dibatalkan karena ada query yang gagal.');
            }
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Simpan pemesanan gagal: ' . $e->getMessage());

            return redirect()->back()->withInput()
                ->with('gagal', 'Pemesanan belum bisa disimpan. Coba lagi beberapa saat.');
        }

        return redirect()->to('pemesanan/status')->with(
            'sukses',
            'Pemesanan ' . $nomor . ' untuk kamar ' . $kamar['nomor_kamar'] . ' sudah dikirim. Pemilik akan memeriksanya.'
        );
    }

    /** Status Pengajuan: pending, approved, atau rejected, lengkap dengan nomor pemesanan. */
    public function status()
    {
        $idPengguna = (int) session('id_pengguna');

        // Peran bisa sudah berubah menjadi penghuni setelah pemilik menyetujui,
        // sementara session masih menyimpan peran lama.
        $pengguna = model(PenggunaModel::class)->find($idPengguna);
        if ($pengguna !== null && $pengguna['role'] !== session('role')) {
            session()->set('role', $pengguna['role']);
            if ($pengguna['role'] === 'penghuni') {
                return redirect()->to('penghuni/tagihan')
                    ->with('sukses', 'Pemesananmu disetujui. Kamu sekarang terdaftar sebagai penghuni.');
            }
        }

        return view('pemesanan/status', [
            'judul'      => 'Status Pengajuan',
            'pemesanan'  => model(PemesananModel::class)->milikPengguna($idPengguna),
        ]);
    }

    // ------------------------------------------------------------------
    //  Pemilik
    // ------------------------------------------------------------------

    /** Daftar pemesanan pending dengan tombol Setujui dan Tolak. */
    public function daftar()
    {
        return view('pemesanan/daftar_pemilik', [
            'judul'     => 'Pemesanan Masuk',
            'pemesanan' => model(PemesananModel::class)->daftarPending(),
        ]);
    }

    /**
     * Setujui pemesanan. Satu transaksi: status approved, sewa baru, peran penghuni,
     * kamar terisi, notifikasi ke calon. Jika salah satu gagal, semuanya dibatalkan.
     */
    public function setujui(int $idPemesanan): RedirectResponse
    {
        $pemesananModel = model(PemesananModel::class);
        $pemesanan = $pemesananModel->find($idPemesanan);

        if ($pemesanan === null) {
            return redirect()->to('pemilik/pemesanan')->with('gagal', 'Pemesanan tidak ditemukan.');
        }

        $nomor = PemesananModel::nomor($idPemesanan);

        if ($pemesanan['status_pengajuan'] !== 'pending') {
            return redirect()->to('pemilik/pemesanan')
                ->with('gagal', 'Pemesanan ' . $nomor . ' sudah diproses sebelumnya.');
        }

        $calon = model(PenggunaModel::class)->find((int) $pemesanan['id_pengguna']);
        $kamar = model(KamarModel::class)->find((int) $pemesanan['id_kamar']);

        if ($calon === null || $kamar === null) {
            return redirect()->to('pemilik/pemesanan')
                ->with('gagal', 'Data calon penghuni atau kamar untuk ' . $nomor . ' tidak lengkap.');
        }

        if ($calon['role'] !== 'calon_penghuni') {
            return redirect()->to('pemilik/pemesanan')->with(
                'gagal',
                $calon['nama'] . ' sudah bukan calon penghuni, sehingga ' . $nomor . ' tidak bisa disetujui.'
            );
        }

        $db = db_connect();
        $db->transStart();

        try {
            // 1. Kunci pemesanan: hanya satu permintaan yang boleh mengubah pending menjadi approved
            $this->harusBerhasil($db->table('pemesanan')
                ->where('id_pemesanan', $idPemesanan)
                ->where('status_pengajuan', 'pending')
                ->update(['status_pengajuan' => 'approved']), 'ubah status pemesanan');
            if ($db->affectedRows() !== 1) {
                $db->transRollback();

                return redirect()->to('pemilik/pemesanan')
                    ->with('gagal', 'Pemesanan ' . $nomor . ' sudah diproses sebelumnya.');
            }

            // 2. Kunci kamar: hanya berhasil jika kamar masih kosong (aman walau dua pemilik klik bersamaan)
            $this->harusBerhasil($db->table('kamar')
                ->where('id_kamar', (int) $pemesanan['id_kamar'])
                ->where('status_kamar', 'kosong')
                ->update(['status_kamar' => 'terisi']), 'ubah status kamar');
            if ($db->affectedRows() !== 1) {
                $db->transRollback();

                return redirect()->to('pemilik/pemesanan')->with(
                    'gagal',
                    'Pemesanan ' . $nomor . ' tidak bisa disetujui karena kamar ' . $kamar['nomor_kamar']
                    . ' sudah terisi. Tolak pemesanan ini atau minta calon memilih kamar lain.'
                );
            }

            // 3. Sewa baru untuk satu tahun
            $mulai = (string) $pemesanan['tanggal_mulai_sewa'];
            $berakhir = (new DateTime($mulai))->modify('+1 year')->format('Y-m-d');
            $this->harusBerhasil(model(SewaModel::class)->insert([
                'id_pemesanan'     => $idPemesanan,
                'tanggal_mulai'    => $mulai,
                'tanggal_berakhir' => $berakhir,
                'status_sewa'      => 'menunggu_pembayaran',
            ]), 'buat sewa');

            // 3b. Tagihan DP + pelunasan untuk sewa ini (kartu tagihan & pembayaran)
            (new \App\Libraries\TagihanService())->siapkanTagihanAwal((int) model(SewaModel::class)->getInsertID());

            // 4. Peran calon menjadi penghuni
            $this->harusBerhasil($db->table('pengguna')
                ->where('id_pengguna', (int) $calon['id_pengguna'])
                ->update(['role' => 'penghuni']), 'ubah peran');

            // 5. Notifikasi ke calon
            $this->kirimNotifikasi(
                (int) $calon['id_pengguna'],
                'Pemesanan ' . $nomor . ' untuk kamar ' . $kamar['nomor_kamar'] . ' disetujui. '
                . 'Masa sewa ' . tanggal_indonesia($mulai) . ' sampai ' . tanggal_indonesia($berakhir)
                . '. Silakan lanjut ke pembayaran.'
            );

            $db->transComplete();
            if ($db->transStatus() === false) {
                throw new \RuntimeException('Transaksi dibatalkan karena ada query yang gagal.');
            }
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Setujui pemesanan ' . $nomor . ' gagal: ' . $e->getMessage());

            return redirect()->to('pemilik/pemesanan')
                ->with('gagal', 'Pemesanan ' . $nomor . ' gagal disetujui dan tidak ada data yang berubah. Coba lagi.');
        }

        return redirect()->to('pemilik/pemesanan')->with(
            'sukses',
            'Pemesanan ' . $nomor . ' disetujui. ' . $calon['nama'] . ' kini penghuni kamar ' . $kamar['nomor_kamar'] . '.'
        );
    }

    /** Tolak pemesanan: rejected, kamar tetap kosong, notifikasi ke calon. */
    public function tolak(int $idPemesanan): RedirectResponse
    {
        $pemesananModel = model(PemesananModel::class);
        $pemesanan = $pemesananModel->find($idPemesanan);

        if ($pemesanan === null) {
            return redirect()->to('pemilik/pemesanan')->with('gagal', 'Pemesanan tidak ditemukan.');
        }

        $nomor = PemesananModel::nomor($idPemesanan);

        if ($pemesanan['status_pengajuan'] !== 'pending') {
            return redirect()->to('pemilik/pemesanan')
                ->with('gagal', 'Pemesanan ' . $nomor . ' sudah diproses sebelumnya.');
        }

        $kamar = model(KamarModel::class)->find((int) $pemesanan['id_kamar']);

        $db = db_connect();
        $db->transStart();

        try {
            $this->harusBerhasil($db->table('pemesanan')
                ->where('id_pemesanan', $idPemesanan)
                ->where('status_pengajuan', 'pending')
                ->update(['status_pengajuan' => 'rejected']), 'ubah status pemesanan');
            if ($db->affectedRows() !== 1) {
                $db->transRollback();

                return redirect()->to('pemilik/pemesanan')
                    ->with('gagal', 'Pemesanan ' . $nomor . ' sudah diproses sebelumnya.');
            }

            $this->kirimNotifikasi(
                (int) $pemesanan['id_pengguna'],
                'Pemesanan ' . $nomor . ' untuk kamar ' . ($kamar['nomor_kamar'] ?? '-') . ' ditolak oleh pemilik. '
                . 'Kamu bisa memesan kamar lain yang masih kosong.'
            );

            $db->transComplete();
            if ($db->transStatus() === false) {
                throw new \RuntimeException('Transaksi dibatalkan karena ada query yang gagal.');
            }
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Tolak pemesanan ' . $nomor . ' gagal: ' . $e->getMessage());

            return redirect()->to('pemilik/pemesanan')
                ->with('gagal', 'Pemesanan ' . $nomor . ' gagal ditolak dan tidak ada data yang berubah. Coba lagi.');
        }

        return redirect()->to('pemilik/pemesanan')->with('sukses', 'Pemesanan ' . $nomor . ' ditolak.');
    }

    // ------------------------------------------------------------------
    //  Pembantu
    // ------------------------------------------------------------------

    /** Kirim notifikasi jenis pemesanan; gagal simpan membatalkan seluruh transaksi. */
    private function kirimNotifikasi(int $idPengguna, string $pesan): void
    {
        $id = model(NotifikasiModel::class)->kirim($idPengguna, 'pemesanan', $pesan);
        $this->harusBerhasil($id > 0 ? true : false, 'kirim notifikasi');
    }

    /**
     * Di production DBDebug mati, sehingga query gagal hanya mengembalikan false tanpa error.
     * Tanpa pemeriksaan ini langkah berikutnya tetap jalan dan transaksi ter-commit setengah jadi.
     *
     * @param mixed $hasil
     */
    private function harusBerhasil($hasil, string $langkah): void
    {
        if ($hasil === false) {
            throw new \RuntimeException('Langkah gagal: ' . $langkah);
        }
    }

    /** Hari ini menurut waktu Indonesia barat (server memakai UTC). */
    private function hariIni(): DateTime
    {
        return new DateTime('today', new DateTimeZone(self::ZONA_WAKTU));
    }
}
