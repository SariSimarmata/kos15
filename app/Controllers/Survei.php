<?php

namespace App\Controllers;

use App\Models\InfoKosModel;
use App\Models\KamarModel;
use App\Models\SurveiKamarModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Kartu 7: pengajuan survei kamar tanpa akun.
 * Data disimpan ke tabel survei_kamar, lalu calon penghuni diarahkan ke WhatsApp pemilik.
 */
class Survei extends BaseController
{
    protected $helpers = ['url', 'form', 'kos'];

    /** Survei paling jauh bisa diajukan untuk berapa hari ke depan. */
    private const MAKS_HARI_KE_DEPAN = 60;

    /**
     * GET /katalog/{id}/survei
     */
    public function form(int $idKamar)
    {
        $kamar = $this->kamarAtau404($idKamar);

        if ($kamar['status_kamar'] !== 'kosong') {
            return redirect()->to(site_url('katalog/' . $idKamar))
                ->with('gagal', 'Kamar ini sudah terisi, jadi tidak bisa diajukan survei.');
        }

        $hariIni = kos_now();

        return view('survei/form', [
            'judul'      => 'Ajukan Survei Kamar ' . $kamar['nomor_kamar'],
            'kamar'      => $kamar,
            'foto'       => $this->fotoUtama($idKamar),
            'errors'     => session('errors') ?? [],
            'tglMin'     => $hariIni->format('Y-m-d'),
            'tglMaks'    => $hariIni->modify('+' . self::MAKS_HARI_KE_DEPAN . ' days')->format('Y-m-d'),
        ]);
    }

    /**
     * POST /katalog/{id}/survei
     */
    public function simpan(int $idKamar)
    {
        $kamar = $this->kamarAtau404($idKamar);

        if ($kamar['status_kamar'] !== 'kosong') {
            return redirect()->to(site_url('katalog/' . $idKamar))
                ->with('gagal', 'Kamar ini sudah terisi, jadi tidak bisa diajukan survei.');
        }

        $data = [
            'nama_calon'     => trim(preg_replace('/\s+/', ' ', (string) $this->request->getPost('nama_calon'))),
            'no_hp'          => normalisasi_no_hp($this->request->getPost('no_hp')),
            'tanggal_survei' => trim((string) $this->request->getPost('tanggal_survei')),
            'waktu_survei'   => substr(trim((string) $this->request->getPost('waktu_survei')), 0, 5),
        ];

        $aturan = [
            'nama_calon' => [
                'label' => 'Nama lengkap',
                'rules' => ['required', 'min_length[3]', 'max_length[100]', "regex_match[/^[\\p{L} .'\\-]+$/u]"],
                'errors' => [
                    'required'    => 'Nama lengkap wajib diisi.',
                    'min_length'  => 'Nama lengkap minimal 3 huruf.',
                    'max_length'  => 'Nama lengkap maksimal 100 karakter.',
                    'regex_match' => 'Nama hanya boleh berisi huruf, spasi, titik, petik, atau tanda hubung.',
                ],
            ],
            'no_hp' => [
                'label' => 'Nomor HP',
                'rules' => ['required', 'regex_match[/^08[0-9]{8,11}$/]'],
                'errors' => [
                    'required'    => 'Nomor HP wajib diisi.',
                    'regex_match' => 'Nomor HP harus diawali 08 dan berisi 10 sampai 13 angka.',
                ],
            ],
            'tanggal_survei' => [
                'label' => 'Tanggal survei',
                'rules' => ['required', 'valid_date[Y-m-d]'],
                'errors' => [
                    'required'   => 'Tanggal survei wajib diisi.',
                    'valid_date' => 'Format tanggal survei tidak valid.',
                ],
            ],
            'waktu_survei' => [
                'label' => 'Jam survei',
                'rules' => ['required', 'regex_match[/^([01][0-9]|2[0-3]):[0-5][0-9]$/]'],
                'errors' => [
                    'required'    => 'Jam survei wajib diisi.',
                    'regex_match' => 'Format jam survei tidak valid (contoh 10:00).',
                ],
            ],
        ];

        $errors = [];
        if (! $this->validateData($data, $aturan)) {
            $errors = $this->validator->getErrors();
        }

        // Tanggal & jam tidak boleh lampau (dihitung dengan waktu WIB).
        if (! isset($errors['tanggal_survei']) && ! isset($errors['waktu_survei'])) {
            $sekarang = kos_now();
            $jadwal   = \DateTimeImmutable::createFromFormat(
                'Y-m-d H:i',
                $data['tanggal_survei'] . ' ' . $data['waktu_survei'],
                new \DateTimeZone('Asia/Jakarta')
            );
            $batasAkhir = $sekarang->setTime(23, 59)->modify('+' . self::MAKS_HARI_KE_DEPAN . ' days');

            if ($data['tanggal_survei'] < $sekarang->format('Y-m-d')) {
                $errors['tanggal_survei'] = 'Tanggal survei tidak boleh tanggal yang sudah lewat.';
            } elseif ($jadwal <= $sekarang) {
                $errors['waktu_survei'] = 'Jam survei hari ini sudah lewat. Pilih jam yang lebih lambat.';
            } elseif ($jadwal > $batasAkhir) {
                $errors['tanggal_survei'] = 'Tanggal survei paling lambat ' . self::MAKS_HARI_KE_DEPAN . ' hari dari hari ini.';
            }
        }

        if ($errors !== []) {
            return redirect()->back()->withInput()->with('errors', $errors);
        }

        $model = model(SurveiKamarModel::class);
        $waktu = $data['waktu_survei'] . ':00';

        // Cegah data ganda jika tombol ditekan dua kali.
        $lama = $model->cariDuplikat($idKamar, $data['no_hp'], $data['tanggal_survei'], $waktu);
        if ($lama !== null) {
            $idSurvei = (int) $lama['id_survei'];
        } else {
            $idSurvei = (int) $model->insert([
                'id_kamar'          => $idKamar,
                'nama_calon'        => $data['nama_calon'],
                'no_hp'             => $data['no_hp'],
                'tanggal_pengajuan' => kos_now()->format('Y-m-d H:i:s'),
                'tanggal_survei'    => $data['tanggal_survei'],
                'waktu_survei'      => $waktu,
            ]);
        }

        // Hanya pengaju sendiri yang boleh melihat halaman konfirmasinya.
        $milikSaya   = (array) session('survei_saya');
        $milikSaya[] = $idSurvei;
        session()->set('survei_saya', array_values(array_unique($milikSaya)));

        return redirect()->to(site_url('katalog/survei/' . $idSurvei));
    }

    /**
     * GET /katalog/survei/{id}
     */
    public function konfirmasi(int $idSurvei)
    {
        if (! in_array($idSurvei, (array) session('survei_saya'), true)) {
            return redirect()->to(site_url('katalog'))
                ->with('gagal', 'Halaman konfirmasi survei hanya bisa dibuka oleh pengaju.');
        }

        $survei = model(SurveiKamarModel::class)->find($idSurvei);
        if ($survei === null) {
            throw PageNotFoundException::forPageNotFound('Data survei tidak ditemukan.');
        }

        $kamar = model(KamarModel::class)->find($survei['id_kamar']);
        $info  = model(InfoKosModel::class)->ambil();

        $jam   = substr($survei['waktu_survei'], 0, 5);
        $pesan = 'Halo, saya ' . $survei['nama_calon'] . ' ingin survei Kamar ' . $kamar['nomor_kamar']
            . ' pada ' . tanggal_panjang($survei['tanggal_survei'], true) . ' pukul ' . str_replace(':', '.', $jam)
            . ' WIB. Nomor HP saya ' . $survei['no_hp'] . '.';

        return view('survei/konfirmasi', [
            'judul'  => 'Pengajuan Survei Tersimpan',
            'survei' => $survei,
            'kamar'  => $kamar,
            'foto'   => $this->fotoUtama((int) $kamar['id_kamar']),
            'jam'    => $jam,
            'pesan'  => $pesan,
            'waLink' => $info['no_whatsapp'] !== '' ? wa_link($info['no_whatsapp'], $pesan) : null,
        ]);
    }

    private function kamarAtau404(int $idKamar): array
    {
        $kamar = model(KamarModel::class)->find($idKamar);
        if ($kamar === null) {
            throw PageNotFoundException::forPageNotFound('Kamar tidak ditemukan.');
        }

        return $kamar;
    }

    private function fotoUtama(int $idKamar): ?string
    {
        $foto = model(\App\Models\MediaKamarModel::class)
            ->where(['id_kamar' => $idKamar, 'jenis' => 'foto'])
            ->orderBy('id_media', 'ASC')
            ->first();

        return $foto['file'] ?? null;
    }
}
