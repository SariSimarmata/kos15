<?php

namespace App\Controllers;

use App\Models\PenggunaModel;
use CodeIgniter\HTTP\RedirectResponse;

class Auth extends BaseController
{
    /** Halaman tujuan setelah login sesuai peran. */
    public const TUJUAN = [
        'pemilik' => 'pemilik/dashboard',
        'penghuni' => 'penghuni/tagihan',
        'calon_penghuni' => 'katalog',
    ];

    public function login()
    {
        if (session('logged_in')) {
            return $this->arahkanSesuaiPeran((string) session('role'));
        }

        return view('auth/login', ['judul' => 'Masuk']);
    }

    public function attemptLogin(): RedirectResponse
    {
        $aturan = [
            'username' => 'required',
            'password' => 'required',
        ];
        $pesan = [
            'username' => ['required' => 'Username wajib diisi.'],
            'password' => ['required' => 'Kata sandi wajib diisi.'],
        ];

        if (!$this->validate($aturan, $pesan)) {
            return redirect()->back()->withInput()
                ->with('gagal', implode(' ', $this->validator->getErrors()));
        }

        $pengguna = model(PenggunaModel::class)
            ->where('username', $this->request->getPost('username'))
            ->first();

        $sandi = (string) $this->request->getPost('password');

        if ($pengguna === null || !password_verify($sandi, $pengguna['password'])) {
            return redirect()->back()->withInput()->with('gagal', 'Username atau kata sandi salah.');
        }

        session()->regenerate();
        session()->set([
            'id_pengguna' => $pengguna['id_pengguna'],
            'nama' => $pengguna['nama'],
            'role' => $pengguna['role'],
            'logged_in' => true,
        ]);

        // Kembali ke halaman yang tadi dibuka sebelum diminta login (diisi PeranFilter)
        $tujuan = session('redirect_url');
        session()->remove('redirect_url');
        if ($tujuan) {
            return redirect()->to($tujuan);
        }

        return $this->arahkanSesuaiPeran($pengguna['role']);
    }

    public function register()
    {
        if (session('logged_in')) {
            return $this->arahkanSesuaiPeran((string) session('role'));
        }

        return view('auth/register', [
            'judul'     => 'Daftar',
            'slogan'    => 'Tempat tinggal nyaman, tagihan tertib',
            'subSlogan' => 'Daftar untuk memesan kamar secara online',
        ]);
    }

    public function attemptRegister(): RedirectResponse
    {
        $aturan = [
            'nama' => 'required|min_length[3]|max_length[100]',
            'username' => 'required|alpha_numeric|min_length[4]|max_length[50]|is_unique[pengguna.username]',
            'no_hp' => 'required|regex_match[/^08[0-9]{8,12}$/]',
            'password' => 'required|min_length[8]|max_length[72]',
            'konfirmasi_password' => 'required|matches[password]',
        ];
        $pesan = [
            'nama' => [
                'required' => 'Nama wajib diisi.',
                'min_length' => 'Nama minimal 3 karakter.',
                'max_length' => 'Nama maksimal 100 karakter.',
            ],
            'username' => [
                'required' => 'Username wajib diisi.',
                'alpha_numeric' => 'Username hanya boleh berisi huruf dan angka.',
                'min_length' => 'Username minimal 4 karakter.',
                'max_length' => 'Username maksimal 50 karakter.',
                'is_unique' => 'Username sudah dipakai, pilih yang lain.',
            ],
            'no_hp' => [
                'required' => 'Nomor WhatsApp wajib diisi.',
                'regex_match' => 'Nomor WhatsApp harus diawali 08 dan terdiri dari 10 sampai 14 angka.',
            ],
            'password' => [
                'required' => 'Kata sandi wajib diisi.',
                'min_length' => 'Kata sandi minimal 8 karakter.',
                'max_length' => 'Kata sandi maksimal 72 karakter.',
            ],
            'konfirmasi_password' => [
                'required' => 'Konfirmasi kata sandi wajib diisi.',
                'matches' => 'Konfirmasi kata sandi tidak sama dengan kata sandi.',
            ],
        ];

        if (!$this->validate($aturan, $pesan)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        // Model sudah meng-hash kata sandi lewat callback beforeInsert.
        model(PenggunaModel::class)->insert([
            'nama' => $this->request->getPost('nama'),
            'username' => $this->request->getPost('username'),
            'no_hp' => $this->request->getPost('no_hp'),
            'password' => $this->request->getPost('password'),
            'role' => 'calon_penghuni',
        ]);

        return redirect()->to('login')->with('sukses', 'Registrasi berhasil. Silakan masuk.');
    }

    public function logout(): RedirectResponse
    {
        session()->destroy();

        return redirect()->to('login')->with('sukses', 'Anda sudah keluar.');
    }

    private function arahkanSesuaiPeran(string $role): RedirectResponse
    {
        return redirect()->to(self::TUJUAN[$role] ?? '/');
    }
}
