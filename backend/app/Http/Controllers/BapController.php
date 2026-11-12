<?php

namespace App\Http\Controllers;

use App\Models\Bap;
use App\Models\Periode;
use App\Models\Presensi;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/** UC03 */
class BapController extends Controller
{
    public function pilihan(Request $request): JsonResponse
    {
        $periode = Periode::berjalan();

        return response()->json([
            'periode' => $periode->nama_periode,
            'praktikum' => $this->kelasAktif($request, $periode)->map(fn ($k) => [
                'id_kelas' => $k->id_kelas,
                'kode_praktikum' => $k->praktikum->kode_praktikum,
                'nama_praktikum' => $k->praktikum->nama_praktikum,
                'kode_kelas' => $k->kode_kelas,
            ])->values(),
            'periode_bap' => $periode->periodeBap->map(fn ($p) => [
                'id_periode_bap' => $p->id_periode_bap,
                'nama_periode_bap' => $p->nama_periode_bap,
            ]),
        ]);
    }

    public function susun(Request $request): JsonResponse
    {
        $periode = Periode::berjalan();
        $asisten = $request->user();

        $data = $request->validate([
            'id_kelas' => ['required', Rule::in($this->kelasAktif($request, $periode)->pluck('id_kelas'))],
            'id_periode_bap' => ['required', Rule::in($periode->periodeBap->pluck('id_periode_bap'))],
        ], [
            'id_kelas.required' => 'Praktikum wajib dipilih.',
            'id_kelas.in' => 'Praktikum tidak valid atau bukan penugasan Anda pada periode berjalan.',
            'id_periode_bap.required' => 'Periode BAP wajib dipilih.',
            'id_periode_bap.in' => 'Periode BAP tidak valid.',
        ]);

        $periodeBap = $periode->periodeBap->firstWhere('id_periode_bap', (int) $data['id_periode_bap']);
        $presensi = Presensi::where('id_asisten', $asisten->id_pengguna)
            ->where('id_kelas', $data['id_kelas'])
            ->whereBetween('tanggal', [$periodeBap->tanggal_mulai->toDateString(), $periodeBap->tanggal_selesai->toDateString()])
            ->pluck('id_presensi');

        if ($presensi->isEmpty()) {
            throw ValidationException::withMessages([
                'id_periode_bap' => 'Data presensi belum tersedia pada praktikum dan periode BAP yang dipilih.',
            ]);
        }

        $bap = DB::transaction(function () use ($asisten, $data, $presensi) {
            $bap = Bap::updateOrCreate(
                ['id_asisten' => $asisten->id_pengguna, 'id_kelas' => $data['id_kelas'], 'id_periode_bap' => $data['id_periode_bap']],
                ['tanggal_dibuat' => now()->toDateString()],
            );
            Presensi::whereIn('id_presensi', $presensi)->update(['id_bap' => $bap->id_bap]);

            return $bap;
        });

        return response()->json(['id_bap' => $bap->id_bap] + $this->isi($bap));
    }

    public function unduh(Request $request, Bap $bap): JsonResponse
    {
        abort_unless($bap->id_asisten === $request->user()->id_pengguna, 404);

        return response()->json([
            'url' => URL::temporarySignedRoute('bap.berkas', now()->addMinutes(5), ['bap' => $bap->id_bap]),
            'nama_berkas' => $this->namaBerkas($this->isi($bap)),
        ]);
    }

    public function berkas(Bap $bap): Response
    {
        $isi = $this->isi($bap);

        return Pdf::loadView('pdf.bap', ['bap' => $isi])->setPaper('a4')->download($this->namaBerkas($isi));
    }

    private function kelasAktif(Request $request, Periode $periode)
    {
        return $request->user()->kelasDitugaskan()
            ->whereHas('praktikum', fn ($q) => $q->where('id_periode', $periode->id_periode))
            ->with('praktikum')->get()->sortBy('praktikum.nama_praktikum')->values();
    }

    private function isi(Bap $bap): array
    {
        $bap->load('asisten', 'kelas.praktikum', 'kelas.dosen', 'periodeBap', 'presensi');
        $akhir = $bap->periodeBap->tanggal_selesai;
        $tgl = fn ($d) => $d->day.' '.Periode::BULAN[$d->month - 1].' '.$d->year;

        return [
            'nama' => $bap->asisten->nama,
            'nim' => $bap->asisten->nomor_induk,
            'bulan' => Periode::BULAN[$akhir->month - 1],
            'tahun' => $akhir->year,
            'prodi' => $bap->kelas->praktikum->prodi,
            'kode_mk' => $bap->kelas->praktikum->kode_praktikum,
            'nama_mk' => $bap->kelas->praktikum->nama_praktikum,
            'total_jam' => Presensi::formatJam($bap->presensi->sum(fn ($p) => $p->durasiMenit())),
            'baris' => $bap->presensi->map(fn (Presensi $p) => [
                'hari' => $p->hari(),
                'tanggal' => $tgl($p->tanggal),
                'kelas' => $bap->kelas->kode_kelas,
                'jam_masuk' => substr($p->jam_masuk, 0, 5),
                'jam_keluar' => substr($p->jam_keluar, 0, 5),
                'jumlah_jam' => Presensi::formatJam($p->durasiMenit()),
                'modul' => $p->kegiatan,
            ])->values()->all(),
            'dosen' => $bap->kelas->dosen->nama,
            'tanggal_dibuat' => $bap->tanggal_dibuat->format('j/n/Y'),
        ];
    }

    private function namaBerkas(array $isi): string
    {
        return str_replace(' ', '_', "BAP {$isi['nama']} {$isi['kode_mk']} {$isi['bulan']} {$isi['tahun']}.pdf");
    }
}
