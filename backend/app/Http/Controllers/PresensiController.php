<?php

namespace App\Http\Controllers;

use App\Models\KelasPraktikum;
use App\Models\Pengguna;
use App\Models\Periode;
use App\Models\Presensi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** UC02 Mencatat presensi kegiatan — KF-03, KF-04. */
class PresensiController extends Controller
{
    public const MAKS_FOTO_KB = 2048;

    /** Basic course langkah 2: tanggal, identitas asisten, dan kelas terisi otomatis dari penugasan (KF-03). */
    public function form(Request $request): JsonResponse
    {
        $asisten = $request->user();

        return response()->json([
            'tanggal' => now()->toDateString(),
            'asisten' => ['nama' => $asisten->nama, 'nomor_induk' => $asisten->nomor_induk],
            // Satu pilihan per praktikum; asisten hanya punya satu kelas per praktikum (KF-11).
            'praktikum' => $this->kelasAktif($asisten)->map(fn (KelasPraktikum $k) => [
                'id_kelas' => $k->id_kelas,
                'kode_praktikum' => $k->praktikum->kode_praktikum,
                'nama_praktikum' => $k->praktikum->nama_praktikum,
                'kode_kelas' => $k->kode_kelas,
            ])->values(),
            'maks_foto_kb' => self::MAKS_FOTO_KB,
        ]);
    }

    /** Wireframe 5: riwayat presensi asisten pada periode berjalan; dapat dicari menurut praktikum, modul, atau tanggal. */
    public function riwayat(Request $request): JsonResponse
    {
        $periode = Periode::berjalan();
        $halaman = Presensi::with('kelas.praktikum')
            ->where('id_asisten', $request->user()->id_pengguna)
            ->whereHas('kelas.praktikum', fn ($q) => $q->where('id_periode', $periode->id_periode))
            ->when($this->pola(), fn ($q, $pola) => $q->where(fn ($w) => $w->whereRaw('LOWER(kegiatan) LIKE ?', [$pola])
                ->orWhereRaw('CAST(tanggal AS VARCHAR) LIKE ?', [$pola])
                ->orWhereHas('kelas.praktikum', fn ($p) => $p->whereRaw('LOWER(nama_praktikum) LIKE ?', [$pola])->orWhereRaw('LOWER(kode_praktikum) LIKE ?', [$pola]))))
            ->when($this->urut(Presensi::kolomUrut()), fn ($q, $o) => $q->orderByRaw($o))
            ->orderByDesc('tanggal')->orderByDesc('jam_masuk')
            ->paginate($this->perHalaman());

        return response()->json([
            'data' => $halaman->getCollection()->map(fn (Presensi $p) => [
                'id_presensi' => $p->id_presensi,
                'tanggal' => $p->tanggal->toDateString(),
                'praktikum' => "{$p->kelas->praktikum->kode_praktikum} · {$p->kelas->praktikum->nama_praktikum}",
                'modul' => $p->kegiatan,
                'jam_masuk' => substr($p->jam_masuk, 0, 5),
                'jam_keluar' => substr($p->jam_keluar, 0, 5),
            ]),
            'halaman' => $this->halaman($halaman),
        ]);
    }

    /** Wireframe 5 (tombol Lihat): foto kegiatan milik asisten sendiri, sebagai data URL agar dapat dipasang di <img> tanpa header token. */
    public function foto(Request $request, Presensi $presensi): JsonResponse
    {
        abort_if((int) $presensi->id_asisten !== (int) $request->user()->id_pengguna, 403, 'Anda tidak memiliki akses ke foto ini.');
        $url = $presensi->fotoDataUrl();
        abort_unless($url, 404, 'Foto kegiatan tidak ditemukan.');

        return response()->json(['url' => $url]);
    }

    /** Basic course langkah 3–5, alternate course 4a & 4b (KF-04). */
    public function store(Request $request): JsonResponse
    {
        $asisten = $request->user();

        $data = $request->validate([
            'id_kelas' => ['required', Rule::in($this->kelasAktif($asisten)->pluck('id_kelas'))],
            'kegiatan' => ['required', 'string', 'max:150'],
            'jam_masuk' => ['required', 'date_format:H:i'],
            'jam_keluar' => ['required', 'date_format:H:i', 'after:jam_masuk'],
            'foto_kegiatan' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:'.self::MAKS_FOTO_KB],
        ], [
            'id_kelas.required' => 'Praktikum wajib dipilih.',
            'id_kelas.in' => 'Praktikum tidak valid atau bukan penugasan Anda pada periode berjalan.',
            'kegiatan.required' => 'Modul praktikum wajib diisi.',
            'kegiatan.max' => 'Modul praktikum maksimal 150 karakter.',
            'jam_masuk.required' => 'Jam masuk wajib diisi.',
            'jam_masuk.date_format' => 'Format jam masuk harus JJ:MM.',
            'jam_keluar.required' => 'Jam keluar wajib diisi.',
            'jam_keluar.date_format' => 'Format jam keluar harus JJ:MM.',
            'jam_keluar.after' => 'Jam keluar harus lebih akhir dari jam masuk.',
            'foto_kegiatan.required' => 'Foto kegiatan wajib diunggah.',
            'foto_kegiatan.file' => 'Foto kegiatan gagal diunggah.',
            'foto_kegiatan.mimes' => 'Format foto harus JPG atau PNG.',
            'foto_kegiatan.max' => 'Ukuran foto maksimal 2 MB.',
        ]);

        $tanggal = now()->toDateString();
        $jamMasuk = $data['jam_masuk'].':00';
        $jamKeluar = $data['jam_keluar'].':00';

        $bertumpuk = Presensi::where('id_asisten', $asisten->id_pengguna)
            ->whereDate('tanggal', $tanggal)
            ->where('jam_masuk', '<', $jamKeluar)
            ->where('jam_keluar', '>', $jamMasuk)
            ->exists();

        if ($bertumpuk) {
            throw ValidationException::withMessages([
                'jam_masuk' => 'Waktu presensi bertumpuk dengan presensi lain yang sudah Anda catat hari ini.',
            ]);
        }

        $path = Storage::disk(config('filesystems.upload_disk'))
            ->putFile('presensi/'.$asisten->id_pengguna, $request->file('foto_kegiatan'));

        Presensi::create([
            'id_asisten' => $asisten->id_pengguna,
            'id_kelas' => $data['id_kelas'],
            'tanggal' => $tanggal,
            'kegiatan' => trim($data['kegiatan']),
            'jam_masuk' => $jamMasuk,
            'jam_keluar' => $jamKeluar,
            'foto_kegiatan' => $path,
        ]);

        return response()->json(['message' => 'Presensi berhasil disimpan.'], 201);
    }

    private function kelasAktif(Pengguna $asisten): Collection
    {
        $periode = Periode::berjalan();

        return $asisten->kelasDitugaskan()
            ->whereHas('praktikum', fn ($q) => $q->where('id_periode', $periode->id_periode))
            ->with('praktikum')
            ->get()
            ->sortBy('praktikum.nama_praktikum')
            ->values();
    }
}
