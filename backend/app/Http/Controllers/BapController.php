<?php

namespace App\Http\Controllers;

use App\Models\Bap;
use App\Models\KelasPraktikum;
use App\Models\Periode;
use App\Models\PeriodeBap;
use App\Models\Presensi;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** UC03 Mengunduh BAP — KF-05, KF-06, KF-07. */
class BapController extends Controller
{
    /** Basic course langkah 2 (KF-05): pilihan praktikum (kelas yang ditugaskan) dan pilihan periode BAP. */
    public function pilihan(Request $request): JsonResponse
    {
        $berjalan = Periode::berjalan();
        $kelas = $request->user()->kelasDitugaskan()->whereHas('praktikum.periode', fn ($q) => $q->whereDate('tanggal_mulai', '<=', now()->toDateString()))
            ->with('praktikum.periode')->get()
            ->sortBy([fn ($a, $b) => $b->praktikum->id_periode <=> $a->praktikum->id_periode, 'praktikum.nama_praktikum']);

        $praktikum = $kelas->map(fn (KelasPraktikum $k) => [
            'id_kelas' => $k->id_kelas,
            'id_periode' => $k->praktikum->id_periode,
            'label' => "{$k->praktikum->kode_praktikum} · {$k->praktikum->nama_praktikum} ({$k->kode_kelas})"
                .($k->praktikum->id_periode === $berjalan->id_periode ? '' : " — {$k->praktikum->periode->nama_periode}"),
        ])->values();

        $periodeBap = PeriodeBap::whereIn('id_periode', $kelas->pluck('praktikum.id_periode'))
            ->whereDate('tanggal_mulai', '<=', now()->toDateString())
            ->orderByDesc('tanggal_mulai')
            ->get(['id_periode_bap', 'id_periode', 'nama_periode_bap']);

        return response()->json(['praktikum' => $praktikum, 'periode_bap' => $periodeBap]);
    }

    /** Basic course langkah 3–5 (KF-06): susun BAP dari presensi pada periode itu, lalu kirim pratinjau. */
    public function susun(Request $request): JsonResponse
    {
        $asisten = $request->user();

        $data = $request->validate([
            'id_kelas' => ['required', Rule::in($asisten->kelasDitugaskan()->pluck('kelas_praktikum.id_kelas'))],
            'tanggal_mulai' => ['required', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
        ], [
            'id_kelas.required' => 'Praktikum wajib dipilih.',
            'id_kelas.in' => 'Praktikum tidak valid atau bukan penugasan Anda.',
            'tanggal_mulai.required' => 'Tanggal mulai wajib diisi.',
            'tanggal_selesai.required' => 'Tanggal selesai wajib diisi.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        ]);

        $kelas = KelasPraktikum::with('praktikum')->findOrFail($data['id_kelas']);
        $periodeBap = PeriodeBap::where('id_periode', $kelas->praktikum->id_periode)
            ->whereDate('tanggal_mulai', '<=', $data['tanggal_selesai'])->whereDate('tanggal_selesai', '>=', $data['tanggal_selesai'])->first();

        if (! $periodeBap) {
            throw ValidationException::withMessages(['tanggal_selesai' => 'Tanggal selesai di luar semester praktikum ini.']);
        }

        $presensi = Presensi::where('id_asisten', $asisten->id_pengguna)
            ->where('id_kelas', $kelas->id_kelas)
            ->whereBetween('tanggal', [$data['tanggal_mulai'], $data['tanggal_selesai']])
            ->pluck('id_presensi');

        if ($presensi->isEmpty()) {
            return response()->json(['message' => 'Data presensi pada rentang tanggal ini belum tersedia. Silakan ubah tanggal.'], 422);
        }

        $idBapIni = Bap::where(['id_asisten' => $asisten->id_pengguna, 'id_kelas' => $kelas->id_kelas, 'tanggal_mulai' => $data['tanggal_mulai'], 'tanggal_selesai' => $data['tanggal_selesai']])->value('id_bap');
        $pindah = Presensi::whereIn('id_presensi', $presensi)->whereNotNull('id_bap')->where('id_bap', '!=', $idBapIni ?? 0)->count();

        $bap = DB::transaction(function () use ($asisten, $kelas, $periodeBap, $presensi, $data) {
            $bap = Bap::updateOrCreate(
                ['id_asisten' => $asisten->id_pengguna, 'id_kelas' => $kelas->id_kelas, 'tanggal_mulai' => $data['tanggal_mulai'], 'tanggal_selesai' => $data['tanggal_selesai']],
                ['id_periode_bap' => $periodeBap->id_periode_bap, 'tanggal_dibuat' => now()->toDateString()],
            );
            Presensi::whereIn('id_presensi', $presensi)->update(['id_bap' => $bap->id_bap]);

            return $bap;
        });

        return response()->json([
            'data' => $this->isi($bap),
            'peringatan' => $pindah ? "$pindah presensi pada rentang ini sebelumnya sudah masuk BAP lain dan kini dipindahkan ke BAP ini." : null,
        ]);
    }

    public function tautan(Request $request, Bap $bap): JsonResponse
    {
        if ((int) $bap->id_asisten !== (int) $request->user()->id_pengguna) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke BAP ini.'], 403);
        }

        return response()->json([
            'url' => URL::temporarySignedRoute('bap.berkas', now()->addMinutes(5), ['bap' => $bap->id_bap]),
            'nama_berkas' => $this->namaBerkas($bap),
        ]);
    }

    /** Basic course langkah 7 (KF-07): berkas PDF A4 yang dapat dicetak; hanya lewat tautan bertanda tangan. */
    public function berkas(Bap $bap): Response
    {
        return Pdf::loadView('pdf.bap', ['bap' => $this->isi($bap)])
            ->setPaper('a4')
            ->download($this->namaBerkas($bap));
    }

    private function isi(Bap $bap): array
    {
        $bap->load('asisten', 'kelas.praktikum', 'kelas.dosen', 'presensi');
        $presensi = $bap->presensi;
        $praktikum = $bap->kelas->praktikum;
        $bulan = fn ($t) => Periode::BULAN[$t->month - 1];

        return [
            'id_bap' => $bap->id_bap,
            'nama' => $bap->asisten->nama,
            'nim' => $bap->asisten->nomor_induk,
            'bulan' => $presensi->map(fn ($p) => $bulan($p->tanggal))->unique()->implode(' - '),
            'prodi' => $bap->kelas->prodi,
            'kode_mk' => $praktikum->kode_praktikum,
            'nama_mk' => $praktikum->nama_praktikum,
            'tahun' => $presensi->last()->tanggal->year,
            'total_jam' => Presensi::formatJam($presensi->sum(fn ($p) => $p->durasiMenit())),
            'baris' => $presensi->map(fn (Presensi $p) => [
                'hari' => $p->hari(),
                'tanggal' => $p->tanggal->day.' '.$bulan($p->tanggal).' '.$p->tanggal->year,
                'kelas' => $bap->kelas->kode_kelas,
                'jam_masuk' => substr($p->jam_masuk, 0, 5),
                'jam_keluar' => substr($p->jam_keluar, 0, 5),
                'jumlah_jam' => Presensi::formatJam($p->durasiMenit()),
                'modul' => $p->kegiatan,
            ])->values(),
            'tanggal_dibuat' => $bap->tanggal_dibuat->format('j/n/Y'),
            'dosen' => $bap->kelas->dosen->nama, // KF-06: dosen pengampu kelas tempat asisten ditugaskan
        ];
    }

    private function namaBerkas(Bap $bap): string
    {
        return sprintf('BAP_%s_%s_%s.pdf',
            $bap->asisten->nomor_induk ?? $bap->id_asisten,
            $bap->kelas->praktikum->kode_praktikum,
            $bap->tanggal_mulai->format('Ymd').'-'.$bap->tanggal_selesai->format('Ymd'),
        );
    }
}
