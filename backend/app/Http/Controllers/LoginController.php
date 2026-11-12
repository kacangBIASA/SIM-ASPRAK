<?php

namespace App\Http\Controllers;

use App\Models\Pengguna;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** UC01 Login — KF-01, KF-02. */
class LoginController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        // Basic course langkah 4 / alternate 3a: isian wajib diisi.
        $data = $request->validate([
            'login' => ['required', 'string'],
            'kata_sandi' => ['required', 'string'],
        ], [
            'login.required' => 'Email atau username wajib diisi.',
            'kata_sandi.required' => 'Kata sandi wajib diisi.',
        ]);

        $kolom = str_contains($data['login'], '@') ? 'email' : 'username';
        $pengguna = Pengguna::where($kolom, $data['login'])->first();

        // Alternate 4a: kredensial tidak sesuai.
        if (! $pengguna || ! Hash::check($data['kata_sandi'], $pengguna->kata_sandi)) {
            throw ValidationException::withMessages([
                'login' => 'Email atau username atau kata sandi salah.',
            ]);
        }

        $token = $pengguna->createToken('sim-asprak')->plainTextToken;

        // Basic course langkah 5: kembalikan peran agar frontend menampilkan menu sesuai peran.
        return response()->json([
            'token' => $token,
            'pengguna' => $this->profil($pengguna),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['pengguna' => $this->profil($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil keluar.']);
    }

    private function profil(Pengguna $p): array
    {
        return [
            'id_pengguna' => $p->id_pengguna,
            'nama' => $p->nama,
            'email' => $p->email,
            'peran' => $p->peran,
            'nomor_induk' => $p->nomor_induk,
        ];
    }
}
