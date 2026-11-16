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
        ], [
            'nomor_surat.required' => 'Nomor surat wajib diisi.',
            'nomor_surat.max' => 'Nomor surat maksimal 50 karakter.',
            'nomor_surat.unique' => 'Nomor surat sudah digunakan pada periode lain.',
        ]);

        $periode->update(['nomor_surat' => trim($data['nomor_surat'])]);

        return response()->json(['message' => 'Nomor surat berhasil disimpan.'] + $this->isi($periode));
    }

    private function isi(Periode $periode): array
    {
        [$asisten, $halaman] = $this->daftarAsisten($periode);

        return [
            'periode' => $periode->nama_periode,
            'id_periode' => $periode->id_periode,
            'periode_opsi' => Periode::opsi(false),
            'nomor_surat' => $periode->nomor_surat,
            'praktikum_opsi' => DB::table('praktikum')->where('id_periode', $periode->id_periode)->orderBy('kode_praktikum')
                ->get(['id_praktikum', 'kode_praktikum', 'nama_praktikum'])
                ->map(fn ($p) => ['id' => $p->id_praktikum, 'nama' => "{$p->kode_praktikum} · {$p->nama_praktikum}"])->all(),
            'asisten' => $asisten,
            'halaman' => $halaman,
        ];
    }

    private function daftarAsisten(Periode $periode): array
    {
        $halaman = DB::table('penugasan_asisten as pa')
            ->join('pengguna as a', 'a.id_pengguna', '=', 'pa.id_asisten')
            ->join('kelas_praktikum as k', 'k.id_kelas', '=', 'pa.id_kelas')
            ->join('praktikum as p', 'p.id_praktikum', '=', 'k.id_praktikum')
            ->where('p.id_periode', $periode->id_periode)
            ->when(request('praktikum'), fn ($q, $id) => $q->where('p.id_praktikum', (int) $id))
            ->when($this->pola(), fn ($q, $pola) => $q->where(fn ($w) => $w->where('a.nomor_induk', 'like', $pola)->orWhereRaw('LOWER(a.nama) LIKE ?', [$pola])))
            ->when($this->urut([
                'nim' => 'a.nomor_induk', 'nama' => 'a.nama', 'praktikum' => 'p.nama_praktikum',
            ]), fn ($q, $o) => $q->orderByRaw($o))
            ->orderBy('a.nama')->orderBy('p.kode_praktikum')->orderBy('pa.id_asisten')
            ->select('pa.id_asisten', 'pa.id_kelas', 'a.nama', 'a.nomor_induk', 'p.kode_praktikum', 'p.nama_praktikum', 'k.kode_kelas')
            ->paginate($this->perHalaman());

        $baris = $halaman->getCollection()->map(function ($r) {
            return [
                'id_asisten' => $r->id_asisten,
                'nama' => $r->nama,
                'nim' => $r->nomor_induk,
                'praktikum' => "{$r->kode_praktikum} · {$r->nama_praktikum}",
                'kelas' => $r->kode_kelas,
            ];
        })->all();

        return [$baris, $this->halaman($halaman)];
    }
}
