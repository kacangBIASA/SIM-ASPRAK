<?php

namespace App\Http\Controllers;

use App\Models\KelasPraktikum;
use App\Models\Pengguna;
use App\Models\Periode;
use App\Models\Praktikum;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** UC05 Mendaftarkan kelas praktikum (KF-09, KF-10). */
class KelasPraktikumController extends Controller
{
    public const PRODI = [
        'Sistem Informasi', 'Informatika', 'Teknologi Informasi', 'Rekayasa Perangkat Lunak', 'Sains Data',
        'Teknik Elektro', 'Teknik Telekomunikasi', 'Teknik Komputer', 'Teknik Industri', 'Teknik Logistik', 'Bisnis Digital',
    ];

    /** UC05 langkah 2: kelas yang diampu dosen pada periode berjalan. */
    public function index(Request $request): JsonResponse
    {
        $periode = Periode::berjalan();

        $kelas = KelasPraktikum::where('id_dosen', $request->user()->id_pengguna)
            ->whereHas('praktikum', fn ($q) => $q->where('id_periode', $periode->id_periode))
            ->with('praktikum')->withCount('asisten')
            ->get()
            ->sortBy(fn ($k) => [$k->praktikum->kode_praktikum, $k->kode_kelas])
            ->map(fn ($k) => $this->ringkas($k) + ['jumlah_asisten' => $k->asisten_count])
            ->values();

        return response()->json(['periode' => $periode->nama_periode, 'prodi' => self::PRODI, 'data' => $kelas]);
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

        $periode = Periode::berjalan();
        $kodeMk = strtoupper(trim($data['kode_praktikum']));
        $kodeKelas = strtoupper(trim($data['kode_kelas']));

        $kelas = DB::transaction(function () use ($request, $data, $periode, $kodeMk, $kodeKelas) {
            // KF-10: kode MK yang sudah terdaftar pada periode berjalan → kelas digabung ke praktikum itu.
            $praktikum = Praktikum::firstOrCreate(
                ['id_periode' => $periode->id_periode, 'kode_praktikum' => $kodeMk],
                ['nama_praktikum' => trim($data['nama_praktikum']), 'prodi' => $data['prodi']],
            );

            // Alternate course 6b.
            if ($praktikum->kelas()->where('kode_kelas', $kodeKelas)->exists()) {
                throw ValidationException::withMessages([
                    'kode_kelas' => "Kelas $kodeKelas sudah terdaftar pada mata kuliah $kodeMk.",
                ]);
            }

            return $praktikum->kelas()->create(['id_dosen' => $request->user()->id_pengguna, 'kode_kelas' => $kodeKelas]);
        });

        $digabung = $kelas->praktikum->kelas()->count() > 1;

        return response()->json([
            'message' => $digabung
                ? "Kelas berhasil didaftarkan dan digabungkan ke mata kuliah $kodeMk · {$kelas->praktikum->nama_praktikum}."
                : 'Kelas berhasil didaftarkan.',
            'data' => $this->ringkas($kelas),
        ], 201);
    }

    /** UC06 */
    public function asisten(Request $request, KelasPraktikum $kelas): JsonResponse
    {
        $this->pastikanMilikDosen($request, $kelas);

        return response()->json([
            'kelas' => $this->ringkas($kelas->load('praktikum')),
            'data' => $kelas->asisten()->orderBy('nama')->get(['pengguna.id_pengguna', 'nama', 'nomor_induk']),
        ]);
    }

    /** UC06 */
    public function cariAsisten(Request $request, KelasPraktikum $kelas): JsonResponse
    {
        $this->pastikanMilikDosen($request, $kelas);
        $q = trim((string) $request->query('q'));

        $data = $q === '' ? [] : Pengguna::where('peran', Pengguna::PERAN_ASISTEN)
            ->where(fn ($w) => $w->where('nama', 'like', "%$q%")->orWhere('nomor_induk', 'like', "%$q%"))
            ->orderBy('nama')->limit(10)->get(['id_pengguna', 'nama', 'nomor_induk']);

        return response()->json(['data' => $data]);
    }

    /** UC06 */
    public function tugaskan(Request $request, KelasPraktikum $kelas): JsonResponse
    {
        $this->pastikanMilikDosen($request, $kelas);
        $data = $request->validate(
            ['id_asisten' => ['required', Rule::exists('pengguna', 'id_pengguna')->where('peran', Pengguna::PERAN_ASISTEN)]],
            ['id_asisten.required' => 'Asisten wajib dipilih.', 'id_asisten.exists' => 'Asisten tidak ditemukan.'],
        );

        try {
            $kelas->asisten()->attach($data['id_asisten'], ['id_praktikum' => $kelas->id_praktikum]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'id_asisten' => 'Asisten sudah ditugaskan pada kelas lain dalam mata kuliah praktikum yang sama.',
            ]);
        }

        return response()->json(['message' => 'Asisten berhasil ditetapkan.'], 201);
    }

    private function pastikanMilikDosen(Request $request, KelasPraktikum $kelas): void
    {
        abort_unless(
            $kelas->id_dosen === $request->user()->id_pengguna
                && $kelas->praktikum()->where('id_periode', Periode::berjalan()->id_periode)->exists(),
            404,
        );
    }

    private function ringkas(KelasPraktikum $k): array
    {
        return [
            'id_kelas' => $k->id_kelas,
            'kode_praktikum' => $k->praktikum->kode_praktikum,
            'nama_praktikum' => $k->praktikum->nama_praktikum,
            'prodi' => $k->praktikum->prodi,
            'kode_kelas' => $k->kode_kelas,
        ];
    }
}
