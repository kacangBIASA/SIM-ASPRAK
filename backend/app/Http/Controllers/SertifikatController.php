<?php

namespace App\Http\Controllers;

use App\Models\KelasPraktikum;
use App\Models\Pengguna;
use App\Models\Penilaian;
use App\Models\Periode;
use App\Models\Sertifikat;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** US12 / UC09 Menerbitkan sertifikat dari penilaian terkirim — KF-19, KF-20. */
class SertifikatController extends Controller
{
    /** Halaman A4 mendatar (pt), sama dengan contoh sertifikat laboratorium; PDF dan pratinjau editor stempel memakai ukuran ini. */
    private const HALAMAN_PT = [841.89, 595.28];
    /** Tanda tangan: titik pusat (pt) di atas garis nama; kotak membesar/mengecil seimbang ke segala arah dari titik ini, tinggi = lebar × rasio. Pratinjau editor memakai angka yang sama. */
    private const TTD_PUSAT_X = 655.0;
    private const TTD_PUSAT_Y = 493.0;
    private const TTD_RASIO = 58 / 220;

    /** Wireframe 7: syarat penerbitan periode berjalan dan daftar asisten beserta status penilaian dan sertifikatnya. */
    public function index(): JsonResponse
    {
        $periode = Periode::berjalan();
        $halaman = DB::table('penugasan_asisten as pa')
            ->join('pengguna as a', 'a.id_pengguna', '=', 'pa.id_asisten')
            ->join('kelas_praktikum as k', 'k.id_kelas', '=', 'pa.id_kelas')
            ->join('praktikum as p', 'p.id_praktikum', '=', 'k.id_praktikum')
            ->leftJoin('penilaian as n', fn ($j) => $j->on('n.id_kelas', '=', 'pa.id_kelas')->on('n.id_asisten', '=', 'pa.id_asisten'))
            ->leftJoin('sertifikat as s', 's.id_penilaian', '=', 'n.id_penilaian')
            ->where('p.id_periode', $periode->id_periode)
            ->when(request('penilaian'), fn ($q, $st) => $st === 'belum' ? $q->whereNull('n.status') : $q->where('n.status', $st))
            ->when(request('sertifikat'), fn ($q, $st) => $st === 'terbit' ? $q->whereNotNull('s.id_sertifikat') : $q->whereNull('s.id_sertifikat'))
            ->when($this->pola(), fn ($q, $pola) => $q->where(fn ($w) => $w->where('a.nomor_induk', 'like', $pola)->orWhereRaw('LOWER(a.nama) LIKE ?', [$pola])))
            ->when($this->urut([
                'nim' => 'a.nomor_induk', 'nama' => 'a.nama', 'praktikum' => 'p.nama_praktikum', 'prodi' => 'k.prodi',
                // belum dinilai < draft < terkirim; belum terbit < terbit
                'penilaian' => "CASE n.status WHEN 'terkirim' THEN 2 WHEN 'draft' THEN 1 ELSE 0 END",
                'sertifikat' => 'CASE WHEN s.id_sertifikat IS NULL THEN 0 ELSE 1 END',
            ]), fn ($q, $o) => $q->orderByRaw($o))
            ->orderBy('a.nama')->orderBy('p.kode_praktikum')->orderBy('pa.id_asisten')
            ->select('pa.id_asisten', 'pa.id_kelas', 'a.nama', 'a.nomor_induk', 'p.nama_praktikum', 'k.prodi', 'n.status as status_penilaian', 's.id_sertifikat')
            ->paginate($this->perHalaman());

        return response()->json([
            'periode' => $periode->nama_periode,
            'syarat' => $this->syarat($periode),
            'baris' => $halaman->getCollection()->map(fn ($r) => [
                'id_asisten' => $r->id_asisten,
                'id_kelas' => $r->id_kelas,
                'nim' => $r->nomor_induk,
                'nama' => $r->nama,
                'praktikum' => $r->nama_praktikum,
                'prodi' => $r->prodi,
                'penilaian' => $r->status_penilaian ?? 'belum',
                'id_sertifikat' => $r->id_sertifikat,
            ])->all(),
            'halaman' => $this->halaman($halaman),
        ]);
    }

    /** KF-19: hanya untuk penilaian terkirim, dan bila nomor surat, tanda tangan, serta stempel periode sudah terisi. */
    public function terbitkan(Request $request, KelasPraktikum $kelas, Pengguna $asisten): JsonResponse
    {
        $periode = Periode::berjalan();
        abort_unless($kelas->praktikum->id_periode === $periode->id_periode, 422, 'Sertifikat hanya dapat diterbitkan untuk periode berjalan.');
        $penilaian = Penilaian::with('komponen', 'sertifikat')->where(['id_kelas' => $kelas->id_kelas, 'id_asisten' => $asisten->id_pengguna])->first();
        abort_unless($penilaian?->status === Penilaian::TERKIRIM, 422, 'Sertifikat hanya dapat diterbitkan untuk asisten yang penilaiannya sudah terkirim.');
        abort_if($penilaian->sertifikat, 409, 'Sertifikat asisten ini sudah diterbitkan.');
        $syarat = $this->syarat($periode);
        abort_unless($syarat['nomor_surat'] && $syarat['tanda_tangan'] && $syarat['stempel'], 422, 'Nomor surat, tanda tangan, dan stempel digital periode ini harus terisi sebelum sertifikat diterbitkan.');

        $sertifikat = DB::transaction(function () use ($request, $penilaian, $periode) {
            $s = Sertifikat::create(['id_penilaian' => $penilaian->id_penilaian, 'id_laboran' => $request->user()->id_pengguna, 'nomor_surat' => $periode->nomor_surat, 'tanggal_terbit' => now()->toDateString()]);
            $path = "sertifikat/{$s->id_sertifikat}.pdf";
            Storage::disk(config('filesystems.upload_disk'))->put($path, $this->susunPdf($s, $penilaian, $periode));
            $s->update(['berkas_sertifikat' => $path]);

            return $s;
        });

        return response()->json(['message' => "Sertifikat {$asisten->nama} berhasil diterbitkan.", 'id_sertifikat' => $sertifikat->id_sertifikat], 201);
    }

    /** Tautan bertanda tangan 5 menit, seperti BAP. */
    public function tautan(Sertifikat $sertifikat): JsonResponse
    {
        return response()->json(['url' => URL::temporarySignedRoute('sertifikat.berkas', now()->addMinutes(5), ['sertifikat' => $sertifikat->id_sertifikat])]);
    }

    /**
     * Laboran melihatnya di tab baru (inline); asisten mengunduhnya (?unduh=1, ikut ditandatangani sehingga tidak dapat diubah).
     * PDF dibuat ulang dari pengaturan periode saat ini (nomor surat, penandatangan, tanda tangan, stempel, posisi), jadi perubahan
     * laboran setelah penerbitan ikut tampil. Bila berkas pengaturan hilang, dipakai PDF yang tersimpan saat terbit.
     */
    public function berkas(Sertifikat $sertifikat): StreamedResponse
    {
        $disk = Storage::disk(config('filesystems.upload_disk'));
        $this->segarkanBerkas($sertifikat);
        abort_unless($sertifikat->berkas_sertifikat && $disk->exists($sertifikat->berkas_sertifikat), 404, 'Berkas sertifikat tidak ditemukan.');

        return $disk->response($sertifikat->berkas_sertifikat, $this->namaBerkas($sertifikat), ['Content-Type' => 'application/pdf'], request()->boolean('unduh') ? 'attachment' : 'inline');
    }

    private function segarkanBerkas(Sertifikat $s): void
    {
        $disk = Storage::disk(config('filesystems.upload_disk'));
        $penilaian = $s->penilaian()->with('komponen', 'asisten', 'kelas.praktikum.periode')->first();
        $periode = $penilaian->kelas->praktikum->periode;
        if (! $this->syarat($periode)['tanda_tangan'] || ! $this->syarat($periode)['stempel']) {
            return;
        }
        $s->nomor_surat = $periode->nomor_surat ?: $s->nomor_surat;
        $path = $s->berkas_sertifikat ?: "sertifikat/{$s->id_sertifikat}.pdf";
        $disk->put($path, $this->susunPdf($s, $penilaian, $periode));
        $s->berkas_sertifikat = $path;
        $s->save();
    }


    private function namaBerkas(Sertifikat $s): string
    {
        $p = $s->penilaian()->with('asisten', 'kelas.praktikum')->first();

        return sprintf('Sertifikat_%s_%s.pdf', $p->asisten->nomor_induk ?? $p->id_asisten, $p->kelas->praktikum->kode_praktikum);
    }

    /** @return array{nomor_surat: bool, tanda_tangan: bool, stempel: bool, penilaian_terkirim: bool, lengkap: bool} */
    private function syarat(Periode $periode): array
    {
        $disk = Storage::disk(config('filesystems.upload_disk'));
        $syarat = [
            'nomor_surat' => filled($periode->nomor_surat),
            'tanda_tangan' => (bool) ($periode->path_tanda_tangan && $disk->exists($periode->path_tanda_tangan)),
            'stempel' => (bool) ($periode->path_stempel && $disk->exists($periode->path_stempel)),
            // Syarat per asisten (penilaian terkirim) ditampilkan pada tabel; di sini cukup bila sudah ada yang terkirim pada periode ini.
            'penilaian_terkirim' => Penilaian::where('status', Penilaian::TERKIRIM)->whereHas('kelas.praktikum', fn ($q) => $q->where('id_periode', $periode->id_periode))->exists(),
        ];

        return $syarat + ['lengkap' => ! in_array(false, $syarat, true)];
    }

    /** KF-20: nama, nilai, tanggal, periode, dan nomor surat dari data penilaian. */
    private function susunPdf(Sertifikat $s, Penilaian $penilaian, Periode $periode): string
    {
        $disk = Storage::disk(config('filesystems.upload_disk'));
        $rataan = Penilaian::hitung($penilaian->nilai())['akhir'];
        $t = $s->tanggal_terbit;
        $konfig = config('services.sertifikat');

        $praktikum = $penilaian->kelas->praktikum;

        return Pdf::loadView('pdf.sertifikat', [
            'nomor_surat' => $s->nomor_surat,
            'kode' => sprintf('D%04dLABTUS%03d%d', $s->id_sertifikat, $s->id_sertifikat % 1000, $t->year), // shortcut: pola kode ditiru dari satu contoh sertifikat
            'nama' => $penilaian->asisten->nama,
            'praktikum' => preg_replace('/^Praktikum\s+/i', '', $praktikum->nama_praktikum),
            'prodi' => $penilaian->kelas->prodi,
            'periode' => str_replace('/', '-', $periode->nama_periode),
            'nilai' => (int) round($rataan),
            'predikat' => Penilaian::predikat($rataan),
            'kota' => $konfig['kota'],
            'tanggal' => $t->day.' '.Periode::BULAN[$t->month - 1].' '.$t->year,
            'nama_penandatangan' => $periode->nama_penandatangan ?: $konfig['nama'],
            'nip_penandatangan' => $periode->nip_penandatangan ?: $konfig['nip'],
            'tanda_tangan' => $this->tandaTangan($periode, $disk->get($periode->path_tanda_tangan)),
            'stempel' => $this->stempel($periode, $disk->get($periode->path_stempel)),
        ])->setPaper('a4', 'landscape')->output();
    }

    private function tandaTangan(Periode $periode, string $isi): array
    {
        $lebar = ($periode->ttd_ukuran ?? PengesahanController::TTD_BAWAAN) / 100 * self::HALAMAN_PT[0];
        $tinggi = $lebar * self::TTD_RASIO;

        return $this->letakkan($isi, self::TTD_PUSAT_X - $lebar / 2, self::TTD_PUSAT_Y - $tinggi / 2, $lebar, $tinggi);
    }

    /** Stempel pada kotak persegi di posisi yang diatur laboran (persen halaman, sama dengan editor), gambar dimuat utuh di dalamnya. */
    private function stempel(Periode $periode, string $isi): array
    {
        $baku = PengesahanController::POSISI_BAWAAN;
        $sisi = ($periode->stempel_ukuran ?? $baku['ukuran']) / 100 * self::HALAMAN_PT[0];

        return $this->letakkan($isi, ($periode->stempel_x ?? $baku['x']) / 100 * self::HALAMAN_PT[0], ($periode->stempel_y ?? $baku['y']) / 100 * self::HALAMAN_PT[1], $sisi, $sisi);
    }

    /** Gambar (data URI) dimuat utuh dalam kotak (kiri, atas, lebar, tinggi; pt), terpusat — setara object-fit: contain. */
    private function letakkan(string $isi, float $kiri, float $atas, float $lebar, float $tinggi): array
    {
        [$w, $h, $tipe] = getimagesizefromstring($isi);
        $skala = min($lebar / $w, $tinggi / $h);

        return [
            'src' => 'data:'.image_type_to_mime_type($tipe).';base64,'.base64_encode($isi),
            'lebar' => round($w * $skala, 2), 'tinggi' => round($h * $skala, 2),
            'kiri' => round($kiri + ($lebar - $w * $skala) / 2, 2), 'atas' => round($atas + ($tinggi - $h * $skala) / 2, 2),
        ];
    }
}
