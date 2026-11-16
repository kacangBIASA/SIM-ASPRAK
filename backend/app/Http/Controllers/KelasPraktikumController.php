<?php

namespace App\Http\Controllers;

use App\Models\KelasPraktikum;
use App\Models\Pengguna;
use App\Models\Periode;
use App\Models\Praktikum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** UC05 Mendaftarkan kelas praktikum (KF-09, KF-10) dan UC06 Menetapkan asisten (KF-11). */
class KelasPraktikumController extends Controller
{
    // shortcut: daftar prodi ditulis tetap; pindah ke tabel bila prodi perlu dikelola laboran.
    public const PRODI = [
        'Sistem Informasi', 'Informatika', 'Teknologi Informasi', 'Rekayasa Perangkat Lunak', 'Sains Data',
        'Teknik Elektro', 'Teknik Telekomunikasi', 'Teknik Komputer', 'Teknik Industri', 'Teknik Logistik', 'Bisnis Digital',
    ];

    /** UC05 langkah 2: kelas yang diampu dosen pada periode berjalan atau berikutnya (pilihan). */
    public function index(Request $request): JsonResponse
    {
        $periode = Periode::pilih($request->query('periode'), true);

        $halaman = KelasPraktikum::where('id_dosen', $request->user()->id_pengguna)
            ->join('praktikum', 'praktikum.id_praktikum', '=', 'kelas_praktikum.id_praktikum')
            ->select('kelas_praktikum.*')
            ->where('praktikum.id_periode', $periode->id_periode)
            ->when($this->pola(), fn ($q, $pola) => $q->where(fn ($w) => $w->whereRaw('LOWER(praktikum.kode_praktikum) LIKE ?', [$pola])
                ->orWhereRaw('LOWER(praktikum.nama_praktikum) LIKE ?', [$pola])->orWhereRaw('LOWER(kelas_praktikum.kode_kelas) LIKE ?', [$pola])))
            ->with('praktikum')->withCount('asisten')
            ->when($this->urut([
                'kode_mk' => 'praktikum.kode_praktikum', 'mata_kuliah' => 'praktikum.nama_praktikum', 'kelas' => 'kelas_praktikum.kode_kelas', 'prodi' => 'kelas_praktikum.prodi',
                'jumlah_asisten' => '(select count(*) from penugasan_asisten pa where pa.id_kelas = kelas_praktikum.id_kelas)',
            ]), fn ($q, $o) => $q->orderByRaw($o))
            ->orderBy('praktikum.kode_praktikum')->orderBy('kelas_praktikum.kode_kelas')
            ->paginate($this->perHalaman());
        $kelas = $halaman->getCollection()->map(fn ($k) => $this->ringkas($k) + ['jumlah_asisten' => $k->asisten_count]);

        return response()->json(['periode' => $periode->nama_periode, 'id_periode' => $periode->id_periode, 'periode_opsi' => Periode::opsi(true), 'prodi' => self::PRODI, 'data' => $kelas, 'halaman' => $this->halaman($halaman)]);
    }

    /** UC05 langkah 5–7, alternate course 6a & 6b. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kode_praktikum' => ['required', 'string', 'max:20'],
            'nama_praktikum' => ['required', 'string', 'max:100'],
            'prodi' => ['required', Rule::in(self::PRODI)],
            'kode_kelas' => ['required', 'string', 'max:20'],
        ], [
            'kode_praktikum.required' => 'Kode mata kuliah wajib diisi.',
            'kode_praktikum.max' => 'Kode mata kuliah maksimal 20 karakter.',
            'nama_praktikum.required' => 'Nama mata kuliah wajib diisi.',
            'nama_praktikum.max' => 'Nama mata kuliah maksimal 100 karakter.',
            'prodi.required' => 'Prodi wajib dipilih.',
            'prodi.in' => 'Prodi tidak valid.',
            'kode_kelas.required' => 'Kode kelas wajib diisi.',
            'kode_kelas.max' => 'Kode kelas maksimal 20 karakter.',
        ]);

        $periode = Periode::pilih($request->input('periode'), true);
        $kodeMk = strtoupper(trim($data['kode_praktikum']));
        $kodeKelas = strtoupper(trim($data['kode_kelas']));

        $kelas = DB::transaction(function () use ($request, $data, $periode, $kodeMk, $kodeKelas) {
            // KF-10: kode MK yang sudah terdaftar pada periode berjalan → kelas digabung ke praktikum itu.
            $praktikum = Praktikum::firstOrCreate(
                ['id_periode' => $periode->id_periode, 'kode_praktikum' => $kodeMk],
                ['nama_praktikum' => trim($data['nama_praktikum'])],
            );

            if ($praktikum->kelas()->where('kode_kelas', $kodeKelas)->exists()) {
                throw ValidationException::withMessages([
                    'kode_kelas' => "Kelas $kodeKelas sudah terdaftar pada mata kuliah $kodeMk.",
                ]);
            }

            return $praktikum->kelas()->create(['id_dosen' => $request->user()->id_pengguna, 'kode_kelas' => $kodeKelas, 'prodi' => $data['prodi']]);
        });

        $digabung = $kelas->praktikum->kelas()->count() > 1;

        return response()->json([
            'message' => $digabung
                ? "Kelas berhasil didaftarkan dan digabungkan ke mata kuliah $kodeMk · {$kelas->praktikum->nama_praktikum}."
                : 'Kelas berhasil didaftarkan.',
            'data' => $this->ringkas($kelas),
        ], 201);
    }

    /** UC06 langkah 3–4: detail kelas dan daftar asistennya. */
    public function show(Request $request, KelasPraktikum $kelas): JsonResponse
    {
        $this->pastikanPengampu($request, $kelas);

        $halaman = $kelas->asisten()
            ->when($this->pola(), fn ($q, $pola) => $q->where(fn ($w) => $w->where('nomor_induk', 'like', $pola)->orWhereRaw('LOWER(nama) LIKE ?', [$pola])))
            ->when($this->urut(['nim' => 'pengguna.nomor_induk', 'nama' => 'pengguna.nama']), fn ($q, $o) => $q->orderByRaw($o))
            ->orderBy('nama')->orderBy('pengguna.id_pengguna')->select(['pengguna.id_pengguna', 'nama', 'nomor_induk'])->paginate($this->perHalaman());

        return response()->json(['data' => $this->ringkas($kelas) + [
            'periode' => $kelas->praktikum->periode->nama_periode,
            'asisten' => $halaman->items(),
            'halaman' => $this->halaman($halaman),
        ]]);
    }

    /** UC06 langkah 5–6, alternate course 6a: cari akun asisten berdasarkan NIM atau nama. */
    public function cariAsisten(Request $request): JsonResponse
    {
        $cari = trim((string) $request->query('cari', $request->query('q', '')));
        if ($cari === '') {
            return response()->json(['message' => 'Isi NIM atau nama asisten yang dicari.'], 422);
        }

        $pola = '%'.mb_strtolower($cari).'%';
        $asisten = Pengguna::whereIn('peran', [Pengguna::PERAN_MAHASISWA, Pengguna::PERAN_ASISTEN])
            ->where(fn ($q) => $q->where('nomor_induk', 'like', $pola)->orWhereRaw('LOWER(nama) LIKE ?', [$pola]))
            ->orderBy('nama')->limit(20)
            ->get(['id_pengguna', 'nama', 'nomor_induk']);

        if ($asisten->isEmpty()) {
            return response()->json(['message' => 'Asisten tidak ditemukan.', 'data' => []], 404);
        }

        return response()->json(['data' => $asisten]);
    }

    /** UC06 langkah 7–8, alternate course 8a (KF-11). */
    public function tetapkanAsisten(Request $request, KelasPraktikum $kelas): JsonResponse
    {
        $this->pastikanPengampu($request, $kelas);

        $data = $request->validate([
            'id_asisten' => ['required', 'integer', Rule::exists('pengguna', 'id_pengguna')->whereIn('peran', [Pengguna::PERAN_MAHASISWA, Pengguna::PERAN_ASISTEN])],
        ], [
            'id_asisten.required' => 'Pilih asisten terlebih dahulu.',
            'id_asisten.exists' => 'Asisten tidak ditemukan.',
        ]);

        $asisten = Pengguna::findOrFail($data['id_asisten']);
        $kelasLain = KelasPraktikum::where('id_praktikum', $kelas->id_praktikum)
            ->whereHas('asisten', fn ($q) => $q->where('pengguna.id_pengguna', $asisten->id_pengguna))
            ->first();

        if ($kelasLain) {
            throw ValidationException::withMessages(['id_asisten' => $kelasLain->id_kelas === $kelas->id_kelas
                ? "{$asisten->nama} sudah ditetapkan pada kelas ini."
                : "{$asisten->nama} sudah ditugaskan pada kelas lain ({$kelasLain->kode_kelas}) dalam mata kuliah ini."]);
        }

        $kelas->asisten()->attach($asisten->id_pengguna, ['id_praktikum' => $kelas->id_praktikum]);
        $asisten->update(['peran' => Pengguna::PERAN_ASISTEN]);

        return response()->json(['message' => "{$asisten->nama} berhasil ditetapkan sebagai asisten kelas {$kelas->kode_kelas}."], 201);
    }

    private function pastikanPengampu(Request $request, KelasPraktikum $kelas): void
    {
        abort_if((int) $kelas->id_dosen !== (int) $request->user()->id_pengguna, 403, 'Anda bukan dosen pengampu kelas ini.');
    }

    private function ringkas(KelasPraktikum $k): array
    {
        return [
            'id_kelas' => $k->id_kelas,
            'kode_praktikum' => $k->praktikum->kode_praktikum,
            'nama_praktikum' => $k->praktikum->nama_praktikum,
            'prodi' => $k->prodi,
            'kode_kelas' => $k->kode_kelas,
        ];
    }
}
