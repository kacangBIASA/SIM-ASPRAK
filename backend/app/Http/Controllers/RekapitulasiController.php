<?php

namespace App\Http\Controllers;

use App\Models\Bap;
use App\Models\Pengguna;
use App\Models\Periode;
use App\Models\Praktikum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/** UC04 Melihat rekapitulasi kehadiran dalam BAP — KF-08. Dihitung dari presensi yang sudah masuk BAP, per asisten dan kelas. */
class RekapitulasiController extends Controller
{
    private const URUT = ['nim' => 'nim', 'nama' => 'nama', 'praktikum' => 'praktikum', 'prodi' => 'prodi', 'kelas' => 'kelas', 'hadir' => 'hadir', 'jam' => 'jam'];

    public function index(Request $request): JsonResponse
    {
        $periode = Periode::berjalan();
        // Rentang tanggal bebas seperti saat mencetak BAP; bawaannya batas periode BAP terbaru yang sudah dimulai.
        $bawaan = $periode->periodeBap()->whereDate('tanggal_mulai', '<=', now()->toDateString())->reorder('tanggal_mulai', 'desc')->first();
        $rentang = $request->validate([
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
        ], ['sampai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.']);
        $dari = $rentang['dari'] ?? $bawaan?->tanggal_mulai->toDateString() ?? $periode->tanggal_mulai->toDateString();
        $sampai = $rentang['sampai'] ?? $bawaan?->tanggal_selesai->toDateString() ?? $periode->tanggal_selesai->toDateString();
        $dalamRentang = fn ($q) => $q->whereBetween('tanggal', [$dari, $sampai]);

        // shortcut: dijumlahkan di PHP agar sama di PostgreSQL dan SQLite; pindah ke SQL bila data presensi membesar.
        $semua = Bap::with(['asisten', 'kelas.praktikum', 'presensi' => $dalamRentang])
            ->whereHas('presensi', $dalamRentang)
            ->whereHas('kelas.praktikum', fn ($q) => $q->where('id_periode', $periode->id_periode))
            ->when($request->query('praktikum'), fn ($q, $id) => $q->whereHas('kelas', fn ($k) => $k->where('id_praktikum', (int) $id)))
            ->get()
            ->groupBy(fn (Bap $b) => "{$b->id_asisten}-{$b->id_kelas}")
            ->map(function ($grup) {
                $b = $grup->first();
                $presensi = $grup->flatMap(fn (Bap $x) => $x->presensi);

                return [
                    'id_asisten' => $b->id_asisten,
                    'nim' => $b->asisten->nomor_induk,
                    'nama' => $b->asisten->nama,
                    'praktikum' => "{$b->kelas->praktikum->kode_praktikum} · {$b->kelas->praktikum->nama_praktikum}",
                    'prodi' => $b->kelas->prodi,
                    'kelas' => $b->kelas->kode_kelas,
                    'hadir' => $presensi->count(),
                    'jam' => round($presensi->sum(fn ($p) => $p->durasiMenit()) / 60, 2),
                ];
            })->values();

        $kata = mb_strtolower(trim((string) $request->query('q', '')));
        $baris = $semua->when($kata !== '', fn ($c) => $c->filter(fn ($r) => str_contains(mb_strtolower($r['nama']), $kata) || str_contains((string) $r['nim'], $kata)));
        $kolom = self::URUT[$request->query('urut')] ?? null;
        $baris = $baris->sortBy([[$kolom ?? 'nama', $kolom && $request->query('arah') === 'desc' ? 'desc' : 'asc'], ['praktikum', 'asc'], ['id_asisten', 'asc']])->values();

        $per = $this->perHalaman();
        $hal = max(1, (int) $request->query('page', 1));
        $halaman = new LengthAwarePaginator($baris->forPage($hal, $per)->values(), $baris->count(), $per, $hal);

        return response()->json([
            'statistik' => [
                Pengguna::whereHas('kelasDitugaskan.praktikum', fn ($q) => $q->where('id_periode', $periode->id_periode))->count(),
                $semua->sum('hadir'),
                round($semua->sum('jam'), 2),
            ],
            'praktikum_opsi' => Praktikum::where('id_periode', $periode->id_periode)->orderBy('kode_praktikum')->get()
                ->map(fn (Praktikum $p) => ['id' => $p->id_praktikum, 'nama' => "{$p->kode_praktikum} · {$p->nama_praktikum}"]),
            'rentang' => ['dari' => $dari, 'sampai' => $sampai],
            'baris' => $halaman->items(),
            'halaman' => $this->halaman($halaman),
        ]);
    }
}
