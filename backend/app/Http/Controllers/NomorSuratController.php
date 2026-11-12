<?php

namespace App\Http\Controllers;

use App\Models\KelasPraktikum;
use App\Models\Periode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/*** UC10 Mengisi nomor surat sertifikat — KF-17.*/
class NomorSuratController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json($this->isi(Periode::berjalan()));
    }

    public function simpan(Request $request): JsonResponse
    {
        $periode = Periode::berjalan();

        $data = $request->validate([
            'nomor_surat' => ['required', 'string', 'max:50',
                Rule::unique('periode', 'nomor_surat')->ignore($periode->id_periode, 'id_periode')],
        ], [
            'nomor_surat.required' => 'Nomor surat wajib diisi.',
            'nomor_surat.max' => 'Nomor surat maksimal 50 karakter.',
            'nomor_surat.unique' => 'Nomor surat sudah digunakan pada periode lain.',
        ]);

        $periode->update(['nomor_surat' => trim($data['nomor_surat'])]);

        return response()->json(['message' => 'Nomor surat berhasil disimpan.'] + $this->isi($periode));
    }

    /** Daftar asisten ditampilkan setelah nomor surat tersimpan.**/
    private function isi(Periode $periode): array
    {
        $asisten = $periode->nomor_surat === null ? [] : KelasPraktikum::with('praktikum', 'asisten')
            ->whereHas('praktikum', fn ($q) => $q->where('id_periode', $periode->id_periode))
            ->get()
            ->flatMap(fn (KelasPraktikum $k) => $k->asisten->map(fn ($a) => [
                'id_asisten' => $a->id_pengguna,
                'nama' => $a->nama,
                'nim' => $a->nomor_induk,
                'praktikum' => "{$k->praktikum->kode_praktikum} · {$k->praktikum->nama_praktikum}",
                'kelas' => $k->kode_kelas,
            ]))
            ->sortBy(['nama', 'praktikum'])
            ->values();

        return ['periode' => $periode->nama_periode, 'nomor_surat' => $periode->nomor_surat, 'asisten' => $asisten];
    }
}
