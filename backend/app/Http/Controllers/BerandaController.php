<?php

namespace App\Http\Controllers;

use App\Models\KelasPraktikum;
use App\Models\Pengguna;
use App\Models\Periode;
use App\Models\Praktikum;
use App\Models\Presensi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** UC01 langkah 5: isi beranda per peran (wireframe 2–4) — tiga angka statistik + satu tabel. */
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
            Pengguna::PERAN_ADMIN => $this->admin($periode, $bulanIni),
        });
    }

    /** Wireframe 2: kehadiran & jam bulan ini, praktikum yang ditugaskan, presensi terbaru (dapat dicari, berpaginasi). */
    private function asisten(Pengguna $asisten, Periode $periode, array $bulanIni): array
    {
        $presensiBulanIni = Presensi::where('id_asisten', $asisten->id_pengguna)->whereBetween('tanggal', $bulanIni)->get();
        $halaman = Presensi::with('kelas.praktikum')
            ->where('id_asisten', $asisten->id_pengguna)
            ->when($this->pola(), fn ($q, $pola) => $q->where(fn ($w) => $w->whereRaw('CAST(presensi.tanggal AS VARCHAR) LIKE ?', [$pola])
                ->orWhereHas('kelas.praktikum', fn ($p) => $p->whereRaw('LOWER(nama_praktikum) LIKE ?', [$pola])->orWhereRaw('LOWER(kode_praktikum) LIKE ?', [$pola]))))
            ->when($this->urut(Presensi::kolomUrut()), fn ($q, $o) => $q->orderByRaw($o))
            ->orderByDesc('tanggal')->orderByDesc('jam_masuk')
            ->paginate($this->perHalaman());

        return [
            'statistik' => [
                $presensiBulanIni->count(),
                Presensi::formatJam($presensiBulanIni->sum(fn ($p) => $p->durasiMenit())),
                $asisten->kelasDitugaskan()->whereHas('praktikum', fn ($q) => $q->where('id_periode', $periode->id_periode))->count(),
            ],
            'baris' => $halaman->getCollection()->map(fn (Presensi $p) => [
                'tanggal' => $p->tanggal->toDateString(),
                'praktikum' => "{$p->kelas->praktikum->kode_praktikum} · {$p->kelas->praktikum->nama_praktikum}",
                'kelas' => $p->kelas->kode_kelas,
                'jam_masuk' => substr($p->jam_masuk, 0, 5),
                'jam_keluar' => substr($p->jam_keluar, 0, 5),
            ]),
            'halaman' => $this->halaman($halaman),
        ];
    }

    /** Wireframe 3: kelas yang diampu pada periode berjalan beserta jumlah asisten dan status penilaiannya. */
    private function dosen(Pengguna $dosen, Periode $periode): array
    {
        $semua = $this->kelasBerjalan($periode, $dosen->id_pengguna);
        $jumlahAsisten = $semua->sum('asisten_count');
        $halaman = $this->kelasHalaman($periode, $dosen->id_pengguna)
            ->paginate($this->perHalaman());

        return [
            'statistik' => [
                $semua->pluck('id_praktikum')->unique()->count(),
                $jumlahAsisten,
                // shortcut: tabel penilaian baru dibuat di iterasi 2 (US08–US10), jadi semua asisten masih belum terkirim.
                $jumlahAsisten,
            ],
            'baris' => $halaman->getCollection()->map(fn (KelasPraktikum $k) => $this->barisKelas($k) + [
                'jumlah_asisten' => $k->asisten_count,
                'status' => 'belum', // shortcut: penilaian baru ada di iterasi 2
            ]),
            'halaman' => $this->halaman($halaman),
        ];
    }

    /** Wireframe 4: asisten aktif, praktikum berjalan, presensi bulan ini, daftar kelas berjalan. */
    private function admin(Periode $periode, array $bulanIni): array
    {
        $halaman = $this->kelasHalaman($periode)->paginate($this->perHalaman());

        return [
            'statistik' => [
                Pengguna::whereHas('kelasDitugaskan.praktikum', fn ($q) => $q->where('id_periode', $periode->id_periode))->count(),
                Praktikum::where('id_periode', $periode->id_periode)->count(),
                Presensi::whereBetween('tanggal', $bulanIni)->count(),
            ],
            'praktikum_opsi' => Praktikum::where('id_periode', $periode->id_periode)->orderBy('kode_praktikum')->get()
                ->map(fn (Praktikum $p) => ['id' => $p->id_praktikum, 'nama' => "{$p->kode_praktikum} · {$p->nama_praktikum}"]),
            'prodi_opsi' => KelasPraktikum::whereHas('praktikum', fn ($q) => $q->where('id_periode', $periode->id_periode))->distinct()->orderBy('prodi')->pluck('prodi')
                ->map(fn ($p) => ['id' => $p, 'nama' => $p]),
            'baris' => $halaman->getCollection()->map(fn (KelasPraktikum $k) => $this->barisKelas($k) + ['prodi' => $k->prodi, 'dosen' => $k->dosen->nama, 'jumlah_asisten' => $k->asisten_count]),
            'halaman' => $this->halaman($halaman),
        ];
    }

    private function barisKelas(KelasPraktikum $k): array
    {
        return ['kode_mk' => $k->praktikum->kode_praktikum, 'praktikum' => $k->praktikum->nama_praktikum, 'kelas' => $k->kode_kelas];
    }

    private function kelasHalaman(Periode $periode, ?int $idDosen = null)
    {
        return KelasPraktikum::select('kelas_praktikum.*')->with('praktikum', 'dosen')->withCount('asisten')
            ->join('praktikum', 'praktikum.id_praktikum', '=', 'kelas_praktikum.id_praktikum')
            ->join('pengguna as dosen', 'dosen.id_pengguna', '=', 'kelas_praktikum.id_dosen')
            ->where('praktikum.id_periode', $periode->id_periode)
            ->when($idDosen, fn ($q) => $q->where('kelas_praktikum.id_dosen', $idDosen))
            ->when(request()->query('praktikum'), fn ($q, $id) => $q->where('kelas_praktikum.id_praktikum', (int) $id))
            ->when(request()->query('prodi'), fn ($q, $prodi) => $q->where('kelas_praktikum.prodi', $prodi))
            ->when($this->pola(), fn ($q, $pola) => $q->where(fn ($w) => $w->whereRaw('LOWER(praktikum.kode_praktikum) LIKE ?', [$pola])
                ->orWhereRaw('LOWER(praktikum.nama_praktikum) LIKE ?', [$pola])->orWhereRaw('LOWER(kelas_praktikum.kode_kelas) LIKE ?', [$pola])
                ->orWhereRaw('LOWER(dosen.nama) LIKE ?', [$pola])))
            ->when($this->urut([
                'kode_mk' => 'praktikum.kode_praktikum', 'praktikum' => 'praktikum.nama_praktikum', 'kelas' => 'kelas_praktikum.kode_kelas', 'prodi' => 'kelas_praktikum.prodi',
                'dosen' => 'dosen.nama', 'jumlah_asisten' => '(select count(*) from penugasan_asisten pa where pa.id_kelas = kelas_praktikum.id_kelas)',
            ]), fn ($q, $o) => $q->orderByRaw($o))
            ->orderBy('praktikum.nama_praktikum')->orderBy('kelas_praktikum.kode_kelas');
    }

    private function kelasBerjalan(Periode $periode, ?int $idDosen = null)
    {
        return KelasPraktikum::with('praktikum', 'dosen')->withCount('asisten')
            ->whereHas('praktikum', fn ($q) => $q->where('id_periode', $periode->id_periode))
            ->when($idDosen, fn ($q) => $q->where('id_dosen', $idDosen))
            ->get();
    }
}
