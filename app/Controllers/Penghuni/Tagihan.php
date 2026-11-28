<?php

namespace App\Controllers\Penghuni;

use App\Controllers\BaseController;
use App\Models\TagihanModel;
use CodeIgniter\HTTP\RedirectResponse;
use DomainException;
use Throwable;

/**
 * Halaman tagihan penghuni (Kartu 3).
 * Route dilindungi filter 'peran:penghuni' di Routes.php. Semua data diambil memakai id di
 * session, bukan id dari URL atau form, jadi penghuni hanya melihat tagihan miliknya.
 */
class Tagihan extends BaseController
{
    protected $helpers = ['tanggal', 'kos'];

    /** GET /penghuni/tagihan: daftar tagihan, plus pilihan skema bila belum dipilih. */
    public function index()
    {
        $idPengguna = (int) session('id_pengguna');
        $model      = model(TagihanModel::class);
        $hariIni    = TagihanModel::hariIni();

        // Sewa yang belum punya tagihan: tampilkan pilihan skema beserta pratinjau nominalnya
        $perluSkema = [];
        foreach ($model->sewaBelumPilihSkema($idPengguna) as $sewa) {
            $harga = TagihanModel::keRupiahBulat($sewa['harga_sewa_tahunan']);
            $sewa['harga']  = $harga;
            $sewa['rencana'] = [
                'dp'    => TagihanModel::rencanaTagihanSewa($harga, 'dp', $sewa['tanggal_mulai'], $hariIni),
                'lunas' => TagihanModel::rencanaTagihanSewa($harga, 'lunas', $sewa['tanggal_mulai'], $hariIni),
            ];
            $perluSkema[] = $sewa;
        }

        return view('penghuni/tagihan', [
            'judul'      => 'Tagihan',
            'hariIni'    => $hariIni,
            'perluSkema' => $perluSkema,
            'tagihan'    => $model->milikPengguna($idPengguna),
            'ringkasan'  => $model->ringkasan($hariIni, $idPengguna),
        ]);
    }

    /** POST /penghuni/tagihan/skema: penghuni memilih DP 50% atau lunas, sistem membuat tagihan. */
    public function pilihSkema(): RedirectResponse
    {
        $aturan = [
            'id_sewa' => 'required|is_natural_no_zero',
            'skema'   => 'required|in_list[dp,lunas]',
        ];
        $pesan = [
            'id_sewa' => ['required' => 'Sewa tidak dikenali.', 'is_natural_no_zero' => 'Sewa tidak dikenali.'],
            'skema'   => [
                'required' => 'Pilih skema pembayaran terlebih dahulu.',
                'in_list'  => 'Skema pembayaran tidak valid.',
            ],
        ];

        if (! $this->validate($aturan, $pesan)) {
            return redirect()->to('penghuni/tagihan')->with('gagal', implode(' ', $this->validator->getErrors()));
        }

        $idSewa = (int) $this->request->getPost('id_sewa');
        $skema  = (string) $this->request->getPost('skema');
        $model  = model(TagihanModel::class);

        // Sewa harus milik penghuni yang login. Pesan sengaja sama dengan "tidak ada",
        // supaya penghuni tidak bisa menebak sewa milik orang lain.
        $sewa = $model->dataSewaUntukTagihan($idSewa);
        if ($sewa === null || (int) $sewa['id_pengguna'] !== (int) session('id_pengguna')) {
            return redirect()->to('penghuni/tagihan')->with('gagal', 'Sewa tidak ditemukan.');
        }

        try {
            $dibuat = $model->buatTagihanSewa($idSewa, $skema);
        } catch (DomainException $e) {
            return redirect()->to('penghuni/tagihan')->with('gagal', $e->getMessage());
        } catch (Throwable $e) {
            log_message('error', 'Pilih skema sewa ' . $idSewa . ' gagal: ' . $e->getMessage());

            return redirect()->to('penghuni/tagihan')
                ->with('gagal', 'Tagihan belum bisa dibuat dan tidak ada data yang berubah. Coba lagi beberapa saat.');
        }

        $pertama = $dibuat[0];

        return redirect()->to('penghuni/tagihan')->with(
            'sukses',
            'Skema ' . TagihanModel::SKEMA[$skema] . ' dipilih untuk kamar ' . $sewa['nomor_kamar'] . '. '
            . (count($dibuat) === 2 ? 'DP ' : 'Tagihan ') . rupiah($pertama['jumlah_tagihan'])
            . ' jatuh tempo ' . tanggal_indonesia($pertama['tanggal_jatuh_tempo']) . '.'
        );
    }
}
