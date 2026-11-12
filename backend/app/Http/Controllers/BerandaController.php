<?php

namespace App\Http\Controllers;

use App\Models\KelasPraktikum;
use App\Models\Pengguna;
use App\Models\Periode;
use App\Models\Presensi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** UC01 langkah 5: isi beranda per peran tiga angka statistik + satu tabel. */
class BerandaController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $pengguna = $request->user();
        $periode = Periode::berjalan();
        $bulanIni = [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()];

        return response()->json(match ($pengguna->peran) {
            Pengguna::PERAN_ASISTEN => $this->asisten($pengguna, $periode, $bulanIni),
            Pengguna::PERAN_DOSEN => $this->dosen($pengguna, $periode),
            Pengguna::PERAN_LABORAN => $this->laboran($periode, $bulanIni),
        });
    }

    /** Peran Asisten: kehadiran & jam bulan ini, praktikum yang ditugaskan, presensi terakhir. */
    private function asisten(Pengguna $asisten, Periode $periode, array $bulanIni): array
    {
        $presensiBulanIni = Presensi::where('id_asisten', $asisten->id_pengguna)->whereBetween('tanggal', $bulanIni)->get();
        $terakhir = Presensi::with('kelas.praktikum')
            ->where('id_asisten', $asisten->id_pengguna)
            ->orderByDesc('tanggal')->orderByDesc('jam_masuk')
            ->limit(5)->get();

        return [
            'statistik' => [
                $presensiBulanIni->count(),
                Presensi::formatJam($presensiBulanIni->sum(fn ($p) => $p->durasiMenit())),
                $asisten->kelasDitugaskan()->whereHas('praktikum', fn ($q) => $q->where('id_periode', $periode->id_periode))->count(),
            ],
            'baris' => $terakhir->map(fn (Presensi $p) => [
                $p->tanggal->toDateString(),
                $this->namaKelas($p->kelas),
                substr($p->jam_masuk, 0, 5),
                substr($p->jam_keluar, 0, 5),
            ]),
        ];
    }

    /** Peran Dosen: kelas yang diampu pada periode berjalan beserta jumlah asisten. */
    private function dosen(Pengguna $dosen, Periode $periode): array
    {
        $kelas = $this->kelasBerjalan($periode, $dosen->id_pengguna);
        $jumlahAsisten = $kelas->sum('asisten_count');

        return [
            'statistik' => [
                $kelas->pluck('id_praktikum')->unique()->count(),
                $jumlahAsisten,
                // shortcut: tabel penilaian baru dibuat di iterasi 2 (US08–US10), jadi semua asisten masih belum terkirim.
                $jumlahAsisten,
            ],
            'baris' => $kelas->map(fn (KelasPraktikum $k) => [
                $this->namaKelas($k),
                $k->asisten_count,
                $k->asisten_count ? "0/{$k->asisten_count} terkirim" : '-',
            ]),
        ];
    }

    /** Peran Laboran/admin: asisten aktif, praktikum berjalan, presensi bulan ini, daftar kelas berjalan. */
    private function laboran(Periode $periode, array $bulanIni): array
    {
        $kelas = $this->kelasBerjalan($periode);

        return [
            'statistik' => [
                Pengguna::whereHas('kelasDitugaskan.praktikum', fn ($q) => $q->where('id_periode', $periode->id_periode))->count(),
                $kelas->pluck('id_praktikum')->unique()->count(),
                Presensi::whereBetween('tanggal', $bulanIni)->count(),
            ],
            'baris' => $kelas->map(fn (KelasPraktikum $k) => [$this->namaKelas($k), $k->dosen->nama, $k->asisten_count]),
        ];
    }

    private function kelasBerjalan(Periode $periode, ?int $idDosen = null)
    {
        return KelasPraktikum::with('praktikum', 'dosen')->withCount('asisten')
            ->whereHas('praktikum', fn ($q) => $q->where('id_periode', $periode->id_periode))
            ->when($idDosen, fn ($q) => $q->where('id_dosen', $idDosen))
            ->get()
            ->sortBy(['praktikum.nama_praktikum', 'kode_kelas'])
            ->values();
    }

    private function namaKelas(KelasPraktikum $k): string
    {
        return "{$k->praktikum->kode_praktikum} · {$k->praktikum->nama_praktikum} ({$k->kode_kelas})";
    }
}
