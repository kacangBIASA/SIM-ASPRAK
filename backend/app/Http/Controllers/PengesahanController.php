<?php

namespace App\Http\Controllers;

use App\Models\Periode;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Mengunggah tanda tangan dan stempel digital direktur per periode — KF-18 (US10). */
class PengesahanController extends Controller
{
    public const MAKS_KB = 2048;
    /** Posisi bawaan stempel (% lebar/tinggi halaman) dan rasio halaman A4 lanskap untuk menjaga stempel tetap di dalam kanvas. */
    public const POSISI_BAWAAN = ['x' => 58.0, 'y' => 74.0, 'ukuran' => 14.5];
    /** Ukuran tanda tangan (% lebar halaman): bawaan setara kotak 220 pt pada template PDF; rentang yang boleh diatur. */
    public const TTD_BAWAAN = 26.1;
    public const TTD_RENTANG = [12, 36];
    private const ASPEK = 584 / 413;
    private const KOLOM = ['tanda_tangan' => 'path_tanda_tangan', 'stempel' => 'path_stempel'];

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->isi(Periode::pilih($request->query('periode'), false)));
    }

    public function simpan(Request $request): JsonResponse
    {
        $periode = Periode::pilih($request->input('periode'), false);

        $aturan = ['file', 'mimes:jpg,jpeg,png', 'max:'.self::MAKS_KB];
        $request->validate([
            'tanda_tangan' => ['required_without:stempel', ...$aturan],
            'stempel' => ['required_without:tanda_tangan', ...$aturan],
        ], [
            'required_without' => 'Pilih berkas tanda tangan atau stempel.',
            'file' => 'Berkas gagal diunggah.',
            // Berkas di atas upload_max_filesize PHP ditolak sebelum aturan max berjalan, lalu jatuh ke aturan uploaded.
            'uploaded' => 'Berkas gagal diunggah. Ukuran gambar maksimal 2 MB.',
            'mimes' => 'Format gambar harus PNG atau JPG.',
            'max' => 'Ukuran gambar maksimal 2 MB.',
        ]);


        $disk = Storage::disk(config('filesystems.upload_disk'));
        foreach (self::KOLOM as $field => $kolom) {
            if (! $request->hasFile($field)) {
                continue;
            }
            $lama = $periode->{$kolom};
            $berkas = $request->file($field);
            $periode->{$kolom} = "pengesahan/{$periode->id_periode}/".Str::random(40).'.png'; // latar selalu dihapus, hasil PNG transparan
            $disk->put($periode->{$kolom}, $this->tanpaLatar($berkas->getRealPath()));
            $periode->save();
            if ($lama) {
                $disk->delete($lama);
            }
        }

        return response()->json(['message' => 'Tanda tangan dan stempel berhasil disimpan.'] + $this->isi($periode));
    }

    public function posisi(Request $request): JsonResponse
    {
        $periode = Periode::pilih($request->input('periode'), false);

        $d = $request->validate([
            'x' => ['required', 'numeric', 'between:0,100'],
            'y' => ['required', 'numeric', 'between:0,100'],
            'ukuran' => ['required', 'numeric', 'between:5,40'],
            'ttd_ukuran' => ['sometimes', 'numeric', 'between:'.implode(',', self::TTD_RENTANG)],
        ]);
        abort_if($d['x'] + $d['ukuran'] > 100.01 || $d['y'] + $d['ukuran'] * self::ASPEK > 100.01, 422, 'Stempel tidak boleh keluar dari area sertifikat.');

        $periode->update(['stempel_x' => $d['x'], 'stempel_y' => $d['y'], 'stempel_ukuran' => $d['ukuran'], 'ttd_ukuran' => $d['ttd_ukuran'] ?? $periode->ttd_ukuran]);

        return response()->json(['message' => 'Posisi stempel dan ukuran tanda tangan berhasil disimpan.'] + $this->isi($periode));
    }

    /** Gambar dikirim sebagai data URL agar dapat dipasang di <img> tanpa header token (pola sama dengan foto presensi). */
    /** Latar putih/terang dijadikan transparan (alfa bertahap di tepi agar goresan tidak bergerigi); warna goresan dipertahankan. */
    public function tanpaLatar(string $path): string
    {
        $asal = imagecreatefromstring(file_get_contents($path));
        [$w, $h] = [imagesx($asal), imagesy($asal)];
        $skala = min(1, 600 / max($w, $h)); // shortcut: sisi terpanjang 600 px; di proyek acuan unggahan besar membuat DomPDF kehabisan memori
        $img = imagecreatetruecolor(max(1, (int) ($w * $skala)), max(1, (int) ($h * $skala)));
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 255, 255, 127));
        imagecopyresampled($img, $asal, 0, 0, 0, 0, imagesx($img), imagesy($img), $w, $h);
        for ($y = 0; $y < imagesy($img); $y++) {
            for ($x = 0; $x < imagesx($img); $x++) {
                $c = imagecolorat($img, $x, $y);
                $terang = min(($c >> 16) & 255, ($c >> 8) & 255, $c & 255); // 255 = putih
                $a = (int) round(127 * max(0, min(1, ($terang - 190) / 55))); // 0 = buram, 127 = bening
                $a = max($a, ($c >> 24) & 127); // PNG yang sudah transparan: pikselnya sering hitam, jangan dibuat buram
                imagesetpixel($img, $x, $y, ($a << 24) | ($c & 0xFFFFFF));
            }
        }
        ob_start();
        imagepng($img);

        return ob_get_clean();
    }

    private function isi(Periode $periode): array
    {
        $disk = Storage::disk(config('filesystems.upload_disk'));
        $url = fn (?string $path) => $path && $disk->exists($path)
            ? 'data:'.($disk->mimeType($path) ?: 'image/png').';base64,'.base64_encode($disk->get($path))
            : null;

        $info = function (?string $path) use ($disk) {
            if (! $path || ! $disk->exists($path)) {
                return null;
            }
            $t = Carbon::createFromTimestamp($disk->lastModified($path));

            return [
                'jenis' => strtoupper(pathinfo($path, PATHINFO_EXTENSION)),
                'kb' => max(1, (int) round($disk->size($path) / 1024)),
                'tanggal' => $t->day.' '.substr(Periode::BULAN[$t->month - 1], 0, 3).' '.$t->year,
            ];
        };

        return [
            'id_periode' => $periode->id_periode,
            'nomor_surat' => $periode->nomor_surat,
            'tanda_tangan_info' => $info($periode->path_tanda_tangan),
            'stempel_info' => $info($periode->path_stempel),
            'posisi' => [
                'x' => $periode->stempel_x ?? self::POSISI_BAWAAN['x'],
                'y' => $periode->stempel_y ?? self::POSISI_BAWAAN['y'],
                'ukuran' => $periode->stempel_ukuran ?? self::POSISI_BAWAAN['ukuran'],
                'ttd_ukuran' => $periode->ttd_ukuran ?? self::TTD_BAWAAN,
            ],
            'periode' => $periode->nama_periode,
            'periode_opsi' => Periode::opsi(false),
            'tanda_tangan' => $url($periode->path_tanda_tangan),
            'stempel' => $url($periode->path_stempel),
            'nama_penandatangan' => $periode->nama_penandatangan,
            'nip_penandatangan' => $periode->nip_penandatangan,
            'maks_kb' => self::MAKS_KB,
        ];
    }
}
