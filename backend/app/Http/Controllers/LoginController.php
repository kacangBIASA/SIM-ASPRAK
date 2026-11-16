<?php

namespace App\Http\Controllers;

use App\Models\Pengguna;
use App\Models\Periode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/** UC01 Login — KF-01, KF-02. */
class LoginController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'kata_sandi' => ['required', 'string'],
        ], [
            'login.required' => 'Email atau username wajib diisi.',
            'kata_sandi.required' => 'Kata sandi wajib diisi.',
        ]);

        $kolom = str_contains($data['login'], '@') ? 'email' : 'username';
        $pengguna = Pengguna::where($kolom, $data['login'])->first();

        $kunci = 'login:'.($pengguna?->id_pengguna ?? mb_strtolower($data['login']));
        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            $menit = (int) ceil(RateLimiter::availableIn($kunci) / 60);
            throw ValidationException::withMessages(['login' => "Terlalu banyak percobaan gagal. Coba lagi dalam $menit menit."]);
        }

        if (! $pengguna || ! Hash::check($data['kata_sandi'], $pengguna->kata_sandi)) {
            RateLimiter::hit($kunci, 900);
            throw ValidationException::withMessages([
                'login' => 'Email atau username atau kata sandi salah.',
            ]);
        }

        RateLimiter::clear($kunci);
        $token = $pengguna->createToken('sim-asprak')->plainTextToken;

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
        $p->segarkanPeran();

        return [
            'id_pengguna' => $p->id_pengguna,
            'nama' => $p->nama,
            'email' => $p->email,
            'peran' => $p->peran,
            'nomor_induk' => $p->nomor_induk,
            'periode' => Periode::berjalan()->nama_periode,
        ];
    }
}
