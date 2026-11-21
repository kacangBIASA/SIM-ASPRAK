<?php

namespace App\Http\Controllers;

use App\Models\Periode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * UC10 Mengisi nomor surat sertifikat — KF-17.
 * Satu nomor surat dipakai seluruh sertifikat asisten pada periode yang sama (wireframe 10).
 */
class NomorSuratController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json($this->isi(Periode::pilih(request('periode'), false)));
    }

    public function simpan(Request $request): JsonResponse
    {
        $periode = Periode::pilih(request('periode'), false);

        $data = $request->validate([
            'nomor_surat' => ['required', 'string', 'max:50',
                Rule::unique('periode', 'nomor_surat')->ignore($periode->id_periode, 'id_periode')],
            'nama_penandatangan' => ['nullable', 'string', 'max:100'],
            'nip_penandatangan' => ['nullable', 'string', 'max:30'],
        ], [
            'nomor_surat.required' => 'Nomor surat wajib diisi.',
            'nomor_surat.max' => 'Nomor surat maksimal 50 karakter.',
            'nomor_surat.unique' => 'Nomor surat sudah digunakan pada periode lain.',
            'nama_penandatangan.max' => 'Nama penandatangan maksimal 100 karakter.',
            'nip_penandatangan.max' => 'NIP maksimal 30 karakter.',
        ]);

        $periode->update([
            'nomor_surat' => trim($data['nomor_surat']),
            'nama_penandatangan' => trim($data['nama_penandatangan'] ?? '') ?: null,
            'nip_penandatangan' => trim($data['nip_penandatangan'] ?? '') ?: null,
        ]);

        $t = now();
        $pesan = "Nomor surat dan data penandatangan tersimpan pada {$t->day} ".Periode::BULAN[$t->month - 1]." {$t->year}.";

        return response()->json(['message' => $pesan] + $this->isi($periode));
    }

    private function isi(Periode $periode): array
    {
        return [
            'periode' => $periode->nama_periode,
            'id_periode' => $periode->id_periode,
            'periode_opsi' => Periode::opsi(false),
            'nomor_surat' => $periode->nomor_surat,
            'nama_penandatangan' => $periode->nama_penandatangan,
            'nip_penandatangan' => $periode->nip_penandatangan,
        ];
    }
}
