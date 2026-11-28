<?php

namespace App\Controllers\Pemilik;

use App\Controllers\BaseController;
use App\Models\InfoKosModel;
use App\Models\NotifikasiModel;
use App\Models\TagihanBulananModel;
use App\Models\TagihanModel;
use CodeIgniter\HTTP\RedirectResponse;
use DomainException;
use InvalidArgumentException;
use Throwable;

/**
 * Input bulanan pemilik (Kartu 9): angka meteran listrik dan air serta biaya sampah per penghuni.
 * Sistem menghitung memakai tarif dari info_kos, lalu membuat tagihan jenis listrik_air.
 * Route dilindungi filter 'peran:pemilik' di Config/Routes.php.
 */
class Meteran extends BaseController
{
    protected $helpers = ['form', 'url', 'kos', 'tanggal'];

    /** GET /pemilik/meteran?periode=YYYY-MM (bawaan: bulan lalu) */
    public function index()
    {
        $hariIni = TagihanModel::hariIni();
        $periode = (string) $this->request->getGet('periode');
        if (! TagihanBulananModel::periodeValid($periode) || $periode > substr($hariIni, 0, 7)) {
            $periode = TagihanBulananModel::periodeBulanLalu($hariIni);
        }

        return view('pemilik/meteran/form', [
            'judul'      => 'Input Meteran Bulanan',
            'periode'    => $periode,
            'periodeMaks' => substr($hariIni, 0, 7),
            'jatuhTempo' => TagihanBulananModel::jatuhTempo($periode, $hariIni),
            'info'       => model(InfoKosModel::class)->ambil(),
            'baris'      => model(TagihanBulananModel::class)->sewaUntukPeriode($periode),
        ]);
    }

    /**
     * POST /pemilik/meteran/simpan
     * Form berisi baris[id_sewa][listrik_awal|listrik_akhir|air_awal|air_akhir|sampah].
     * Baris yang dikosongkan semua dilewati. Tiap baris diproses dalam transaksinya sendiri,
     * jadi satu baris yang salah tidak membatalkan baris lain.
     */
    public function simpan(): RedirectResponse
    {
        $hariIni = TagihanModel::hariIni();
        $periode = (string) $this->request->getPost('periode');

        if (! TagihanBulananModel::periodeValid($periode) || $periode > substr($hariIni, 0, 7)) {
            return redirect()->to('pemilik/meteran')
                ->with('gagal', 'Periode tidak valid. Pilih bulan sampai bulan ini.');
        }

        $kembali = 'pemilik/meteran?periode=' . $periode;
        $kiriman = $this->request->getPost('baris');
        if (! is_array($kiriman) || $kiriman === []) {
            return redirect()->to($kembali)->with('gagal', 'Tidak ada data yang dikirim.');
        }

        $tarif    = model(InfoKosModel::class)->ambil();
        $model    = model(TagihanBulananModel::class);
        $sewaOk   = array_column($model->sewaUntukPeriode($periode), null, 'id_sewa');
        $galat    = [];   // id_sewa => pesan (tampil di barisnya)
        $galatUmum = [];  // pesan untuk baris yang tidak ada di halaman (data tidak wajar), tampil di atas
        $berhasil = [];   // baris rincian untuk pesan sukses

        foreach ($kiriman as $idSewa => $isi) {
            $idSewa = (int) $idSewa;
            $isi    = is_array($isi) ? $isi : [];

            // Semua kolom kosong = baris dilewati
            if (implode('', array_map(static fn ($v) => trim((string) $v), $isi)) === '') {
                continue;
            }

            $sewa = $sewaOk[$idSewa] ?? null;
            if ($sewa === null) {
                $galatUmum[] = 'Sewa nomor ' . $idSewa . ' tidak punya sewa aktif pada bulan tersebut.';
                continue;
            }
            if ($sewa['id_tagih_bulanan'] !== null) {
                $galat[$idSewa] = 'Tagihan bulan ini sudah dibuat dan tidak bisa dibuat dua kali.';
                continue;
            }

            try {
                $hasil = $this->prosesBaris($sewa, $periode, $isi, $tarif, $hariIni);
            } catch (DomainException | InvalidArgumentException $e) {
                $galat[$idSewa] = $e->getMessage();
                continue;
            } catch (Throwable $e) {
                log_message('error', 'Input meteran sewa ' . $idSewa . ' ' . $periode . ' gagal: ' . $e->getMessage());
                $galat[$idSewa] = 'Gagal menyimpan, tidak ada data yang berubah. Coba lagi.';
                continue;
            }

            $berhasil[] = $hasil;
        }

        $redirect = redirect()->to($kembali);

        if ($berhasil !== []) {
            $redirect = $redirect->with('sukses', $this->pesanSukses($berhasil))
                ->with('rincian_meteran', $berhasil);
        }
        if ($galat !== [] || $galatUmum !== []) {
            $jumlah = count($galat) + count($galatUmum);
            $pesan  = $berhasil === []
                ? 'Tagihan belum dibuat.'
                : $jumlah . ' baris belum bisa disimpan.';
            $pesan .= $galat !== [] ? ' Periksa baris yang bertanda merah.' : '';
            $pesan .= $galatUmum !== [] ? ' ' . implode(' ', $galatUmum) : '';

            $redirect = $redirect->with('galat_baris', $galat)->with('gagal', trim($pesan))->withInput();
        }
        if ($berhasil === [] && $galat === [] && $galatUmum === []) {
            $redirect = $redirect->with('gagal', 'Belum ada angka yang diisi.');
        }

        return $redirect;
    }

    /**
     * Validasi satu baris, hitung memakai tarif info_kos, buat tagihan, lalu kirim notifikasi.
     *
     * @return array<string, mixed> rincian untuk pesan hasil
     */
    private function prosesBaris(array $sewa, string $periode, array $isi, array $tarif, string $hariIni): array
    {
        $ambil = static fn (string $kunci): ?string => isset($isi[$kunci]) ? trim((string) $isi[$kunci]) : null;

        $angka = [];
        foreach (['listrik_awal' => 'Meter listrik awal', 'listrik_akhir' => 'Meter listrik akhir',
            'air_awal' => 'Meter air awal', 'air_akhir' => 'Meter air akhir'] as $kunci => $label) {
            $nilai = TagihanBulananModel::keSeratus($ambil($kunci));
            if ($nilai === null) {
                throw new InvalidArgumentException($label . ' harus berupa angka tidak negatif (maksimal 2 angka desimal).');
            }
            $angka[$kunci] = $nilai;
        }

        $sampah = TagihanBulananModel::sampahRupiah($ambil('sampah'));
        if ($sampah === null) {
            throw new InvalidArgumentException('Biaya sampah harus berupa rupiah bulat, tidak negatif. Isi 0 bila tidak ada.');
        }

        try {
            $listrik = TagihanBulananModel::hitungPemakaian($angka['listrik_awal'], $angka['listrik_akhir'], $tarif['tarif_listrik_per_kwh']);
        } catch (InvalidArgumentException) {
            throw new InvalidArgumentException('Meter listrik akhir tidak boleh lebih kecil daripada meter awal.');
        }
        try {
            $air = TagihanBulananModel::hitungPemakaian($angka['air_awal'], $angka['air_akhir'], $tarif['tarif_air_per_m3']);
        } catch (InvalidArgumentException) {
            throw new InvalidArgumentException('Meter air akhir tidak boleh lebih kecil daripada meter awal.');
        }

        $dibuat = model(TagihanBulananModel::class)->terbitkan(
            (int) $sewa['id_sewa'],
            $periode,
            $listrik['biaya'],
            $air['biaya'],
            $sampah,
            $hariIni
        );

        // Notifikasi dikirim setelah tagihan tersimpan. Kalau gagal, tagihan tetap sah dan hanya dicatat di log.
        try {
            model(NotifikasiModel::class)->kirim(
                (int) $dibuat['id_pengguna'],
                'tagihan',
                'Tagihan listrik & air ' . TagihanModel::periode(['periode' => $periode, 'jenis_tagihan' => 'listrik_air'])
                . ' kamar ' . $sewa['nomor_kamar'] . ' sebesar ' . rupiah($dibuat['jumlah_tagihan'])
                . ' jatuh tempo ' . tanggal_indonesia($dibuat['tanggal_jatuh_tempo']) . '.',
                (int) $dibuat['id_tagihan']
            );
        } catch (Throwable $e) {
            log_message('error', 'Notifikasi tagihan listrik_air ' . $dibuat['id_tagihan'] . ' gagal: ' . $e->getMessage());
        }

        return [
            'nama'         => $sewa['nama_penghuni'],
            'kamar'        => $sewa['nomor_kamar'],
            'kwh'          => TagihanBulananModel::tampilSatuan($listrik['pemakaian100']),
            'tarif_listrik' => (float) $tarif['tarif_listrik_per_kwh'],
            'listrik'      => $listrik['biaya'],
            'm3'           => TagihanBulananModel::tampilSatuan($air['pemakaian100']),
            'tarif_air'    => (float) $tarif['tarif_air_per_m3'],
            'air'          => $air['biaya'],
            'sampah'       => $sampah,
            'total'        => $dibuat['jumlah_tagihan'],
            'jatuh_tempo'  => $dibuat['tanggal_jatuh_tempo'],
        ];
    }

    /** Ringkasan satu kalimat untuk flash; rincian hitungan lengkap tampil di halaman. */
    private function pesanSukses(array $berhasil): string
    {
        $total = array_sum(array_column($berhasil, 'total'));

        return count($berhasil) . ' tagihan listrik dan air dibuat, total ' . rupiah($total) . '.';
    }
}
