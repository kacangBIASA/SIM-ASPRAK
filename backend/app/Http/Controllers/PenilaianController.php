<?php

namespace App\Http\Controllers;

use App\Models\KelasPraktikum;
use App\Models\KomponenPenilaian;
use App\Models\Pengguna;
use App\Models\Penilaian;
use App\Models\Periode;
use App\Models\Presensi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** UC05–UC07 (US07–US09): dosen memilih praktikum, melihat asisten, mengisi nilai, menyimpan draft, dan mengirim penilaian — KF-12–KF-16. */
class PenilaianController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $periode = Periode::berjalan();
        $kelas = KelasPraktikum::select('kelas_praktikum.*')->with('praktikum')->withCount('asisten')
            ->join('praktikum', 'praktikum.id_praktikum', '=', 'kelas_praktikum.id_praktikum')
            ->where('kelas_praktikum.id_dosen', $request->user()->id_pengguna)
            ->where('praktikum.id_periode', $periode->id_periode)
            ->orderBy('praktikum.nama_praktikum')->orderBy('kelas_praktikum.kode_kelas')
            ->get();

        $dipilih = $request->query('kelas') ? $kelas->firstWhere('id_kelas', (int) $request->query('kelas')) : $kelas->first();
        abort_if($request->query('kelas') && ! $dipilih, 403, 'Anda bukan pengampu kelas ini.');

        $terkirim = Penilaian::whereIn('id_kelas', $kelas->pluck('id_kelas'))->where('status', Penilaian::TERKIRIM)
            ->selectRaw('id_kelas, count(*) as jumlah')->groupBy('id_kelas')->pluck('jumlah', 'id_kelas');

        $halaman = $dipilih?->asisten()
            ->when($this->pola(), fn ($q, $pola) => $q->where(fn ($w) => $w->where('nomor_induk', 'like', $pola)->orWhereRaw('LOWER(nama) LIKE ?', [$pola])))
            ->when($this->urut(['nim' => 'pengguna.nomor_induk', 'nama' => 'pengguna.nama']), fn ($q, $o) => $q->orderByRaw($o))
            ->orderBy('nama')->orderBy('pengguna.id_pengguna')->select(['pengguna.id_pengguna', 'nama', 'nomor_induk'])->paginate($this->perHalaman());

        $penilaian = $halaman
            ? Penilaian::with('komponen')->where('id_kelas', $dipilih->id_kelas)->whereIn('id_asisten', $halaman->getCollection()->pluck('id_pengguna'))->get()->keyBy('id_asisten')
            : collect();

        $hadir = $halaman
            ? Presensi::where('id_kelas', $dipilih->id_kelas)->whereIn('id_asisten', $halaman->getCollection()->pluck('id_pengguna'))
                ->selectRaw('id_asisten, count(*) as jumlah')->groupBy('id_asisten')->pluck('jumlah', 'id_asisten')
            : collect();

        return response()->json([
            'periode' => $periode->nama_periode,
            'kelas' => $kelas->map(fn (KelasPraktikum $k) => [
                'id_kelas' => $k->id_kelas,
                'nama' => $k->praktikum->nama_praktikum,
                'info' => "{$k->praktikum->kode_praktikum} · {$k->prodi} · {$k->kode_kelas} · {$k->asisten_count} asisten",
                'prodi' => $k->prodi,
                'kelas' => $k->kode_kelas,
                'jumlah_asisten' => $k->asisten_count,
                'terkirim' => (int) ($terkirim[$k->id_kelas] ?? 0),
            ]),
            'id_kelas' => $dipilih?->id_kelas,
            'judul' => $dipilih ? "{$dipilih->praktikum->nama_praktikum} {$dipilih->kode_kelas} · {$dipilih->prodi}" : null,
            'asisten' => $halaman?->getCollection()->map(function ($a) use ($penilaian, $hadir) {
                $p = $penilaian->get($a->id_pengguna);

                return [
                    'id_asisten' => $a->id_pengguna, 'nim' => $a->nomor_induk, 'nama' => $a->nama,
                    'hadir' => (int) ($hadir[$a->id_pengguna] ?? 0),
                    'status' => $p?->status ?? 'belum',
                    'rataan' => $p?->status === Penilaian::TERKIRIM ? Penilaian::hitung($p->nilai())['akhir'] : null,
                ];
            })->values() ?? [],
            'halaman' => $halaman ? $this->halaman($halaman) : null,
        ]);
    }

    /** UC05 langkah 5–6: form penilaian beserta isian draft, atau mode baca saja bila sudah terkirim (6a). */
    public function show(Request $request, KelasPraktikum $kelas, Pengguna $asisten): JsonResponse
    {
        $this->otorisasi($request, $kelas, $asisten);

        return response()->json($this->isi($kelas, $asisten));
    }

    /** Wireframe 8: presensi asisten pada kelas ini, hanya baca (dosen melihat dasar penilaian). */
    public function presensi(Request $request, KelasPraktikum $kelas, Pengguna $asisten): JsonResponse
    {
        $this->otorisasi($request, $kelas, $asisten);

        return response()->json([
            'asisten' => $asisten->nama,
            'praktikum' => $kelas->praktikum->nama_praktikum,
            'kelas' => $kelas->kode_kelas,
            'data' => Presensi::where(['id_kelas' => $kelas->id_kelas, 'id_asisten' => $asisten->id_pengguna])->orderBy('tanggal')->orderBy('jam_masuk')->get()
                ->map(fn (Presensi $p) => [
                    'id_presensi' => $p->id_presensi, 'tanggal' => $p->tanggal->toDateString(), 'modul' => $p->kegiatan,
                    'jam_masuk' => substr($p->jam_masuk, 0, 5), 'jam_keluar' => substr($p->jam_keluar, 0, 5),
                ]),
        ]);
    }

    /** Foto kegiatan satu presensi, hanya untuk dosen pengampu kelas presensi tersebut. */
    public function foto(Request $request, Presensi $presensi): JsonResponse
    {
        $this->otorisasi($request, $presensi->kelas, $presensi->asisten);
        $url = $presensi->fotoDataUrl();
        abort_unless($url, 404, 'Foto kegiatan tidak ditemukan.');

        return response()->json(['url' => $url]);
    }

    /** UC06: simpan draft; isian boleh belum lengkap. */
    public function draft(Request $request, KelasPraktikum $kelas, Pengguna $asisten): JsonResponse
    {
        return $this->proses($request, $kelas, $asisten, false);
    }

    /** UC07: kirim penilaian; seluruh komponen wajib terisi (1a). */
    public function kirim(Request $request, KelasPraktikum $kelas, Pengguna $asisten): JsonResponse
    {
        return $this->proses($request, $kelas, $asisten, true);
    }

    private function proses(Request $request, KelasPraktikum $kelas, Pengguna $asisten, bool $kirim): JsonResponse
    {
        $this->otorisasi($request, $kelas, $asisten);
        $ada = Penilaian::where(['id_kelas' => $kelas->id_kelas, 'id_asisten' => $asisten->id_pengguna])->first();
        abort_if($ada?->status === Penilaian::TERKIRIM, 409, 'Penilaian sudah terkirim dan tidak dapat diubah.');

        $aturan = ['nilai' => ['required', 'array']];
        foreach (KomponenPenilaian::pluck('id_komponen') as $id) {
            $aturan["nilai.$id"] = [$kirim ? 'required' : 'nullable', 'integer', 'between:0,100'];
        }
        $data = $request->validate($aturan, [
            'nilai.required' => 'Data nilai wajib dikirim.',
            'nilai.*.required' => 'Nilai wajib diisi.',
            'nilai.*.integer' => 'Nilai harus berupa angka bulat.',
            'nilai.*.between' => 'Nilai harus antara 0 sampai 100.',
        ]);

        DB::transaction(function () use ($kelas, $asisten, $data, $kirim) {
            $p = Penilaian::updateOrCreate(
                ['id_kelas' => $kelas->id_kelas, 'id_asisten' => $asisten->id_pengguna],
                $kirim ? ['status' => Penilaian::TERKIRIM, 'dikirim_pada' => now()] : ['status' => Penilaian::DRAFT],
            );
            $p->komponen()->sync(collect($data['nilai'])->reject(fn ($n) => $n === null)->map(fn ($n) => ['nilai' => $n])->all());
        });

        return response()->json($this->isi($kelas, $asisten) + [
            'message' => $kirim ? 'Penilaian berhasil dikirim.' : 'Draft penilaian berhasil disimpan.',
        ]);
    }

    private function isi(KelasPraktikum $kelas, Pengguna $asisten): array
    {
        $p = Penilaian::with('komponen')->where(['id_kelas' => $kelas->id_kelas, 'id_asisten' => $asisten->id_pengguna])->first();
        $nilai = $p?->nilai() ?? [];
        $hitung = Penilaian::hitung($nilai);
        $komponen = KomponenPenilaian::orderBy('urut')->get()->groupBy('kategori');
        $kelas->loadMissing('praktikum');

        return [
            'asisten' => ['id_asisten' => $asisten->id_pengguna, 'nama' => $asisten->nama, 'nim' => $asisten->nomor_induk],
            'praktikum' => $kelas->praktikum->nama_praktikum,
            'kelas' => $kelas->kode_kelas,
            'status' => $p?->status ?? 'belum',
            'dikirim_pada' => $p?->dikirim_pada?->toDateString(),
            'kategori' => collect(KomponenPenilaian::KATEGORI)->map(fn ($nama, $kode) => [
                'kode' => $kode,
                'nama' => $nama,
                'komponen' => $komponen[$kode]->map(fn ($c) => ['id' => $c->id_komponen, 'nama' => $c->nama, 'nilai' => $nilai[$c->id_komponen] ?? null])->values(),
                'rataan' => $hitung['kategori'][$kode],
            ])->values(),
            'rataan_akhir' => $hitung['akhir'],
        ];
    }

    /** Hanya dosen pengampu kelas pada periode berjalan, dan hanya untuk asisten yang ditugaskan pada kelas itu. */
    private function otorisasi(Request $request, KelasPraktikum $kelas, Pengguna $asisten): void
    {
        abort_unless((int) $kelas->id_dosen === (int) $request->user()->id_pengguna, 403, 'Anda bukan pengampu kelas ini.');
        abort_unless($kelas->praktikum->id_periode === Periode::berjalan()->id_periode, 403, 'Kelas ini bukan pada periode berjalan.');
        abort_unless($kelas->asisten()->where('pengguna.id_pengguna', $asisten->id_pengguna)->exists(), 404, 'Asisten tidak terdaftar pada kelas ini.');
    }
}
