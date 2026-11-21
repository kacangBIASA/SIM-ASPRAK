<?php

namespace App\Support;

use App\Models\PotonganDokumen;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Smalot\PdfParser\Parser;
use ZipArchive;

/** KF-22 & KF-23: memecah dokumen chatbot menjadi potongan, membuat vektor embedding-nya dengan Gemini, */
class Pencari
{
    private const PANJANG_POTONGAN = 1000;

    private const BATCH_GEMINI = 100;

    public static function teks(string $path, string $ekstensi): string
    {
        $teks = match (strtolower($ekstensi)) {
            'pdf' => (new Parser)->parseFile($path)->getText(),
            'docx' => self::teksDocx($path),
            default => file_get_contents($path),
        };

        return trim(preg_replace("/[ \t]+/", ' ', str_replace("\r", '', $teks)));
    }

    public static function potong(string $teks): array
    {
        $potongan = [];
        $kini = '';
        foreach (preg_split('/\n\s*\n|(?<=[.!?])\s+/u', $teks) as $bagian) {
            $bagian = trim($bagian);
            if ($bagian === '') {
                continue;
            }
            if ($kini !== '' && mb_strlen($kini) + mb_strlen($bagian) > self::PANJANG_POTONGAN) {
                $potongan[] = $kini;
                $kini = '';
            }
            $kini = trim("$kini $bagian");
        }
        if ($kini !== '') {
            $potongan[] = $kini;
        }

        return $potongan;
    }

    /** KF-22: embedding potongan dokumen, urutan sama dengan $potongan. */
    public static function embedDokumen(array $potongan): array
    {
        return self::embed($potongan, 'RETRIEVAL_DOCUMENT');
    }

    /** KF-23: potongan paling relevan dengan pertanyaan (skor ≥ ambang), skor tertinggi lebih dulu. */
    public static function cari(string $pertanyaan, int $jumlah = 3): Collection
    {
        if (! PotonganDokumen::exists()) {
            return collect();
        }

        $q = self::embed([$pertanyaan], 'RETRIEVAL_QUERY')[0];

        if (DB::getDriverName() === 'pgsql') {
            $v = '['.implode(',', $q).']';
            $baris = DB::select('SELECT id_potongan, 1 - (embedding <=> ?::vector) AS skor FROM potongan_dokumen WHERE embedding IS NOT NULL ORDER BY embedding <=> ?::vector LIMIT ?', [$v, $v, $jumlah]);
            $potongan = PotonganDokumen::with('dokumen')->findMany(array_column($baris, 'id_potongan'))->keyBy('id_potongan');

            return collect($baris)->map(fn ($b) => ['potongan' => $potongan[$b->id_potongan], 'skor' => (float) $b->skor])
                ->filter(fn ($r) => $r['skor'] >= config('services.gemini.ambang'))->values();
        }

        $semua = PotonganDokumen::with('dokumen')->get();

        return $semua->map(fn ($p) => ['potongan' => $p, 'skor' => self::kosinus($q, $p->vektor)])
            ->filter(fn ($r) => $r['skor'] >= config('services.gemini.ambang'))
            ->sortByDesc('skor')
            ->take($jumlah)
            ->values();
    }

    public static function kosinus(array $a, array $b): float
    {
        $dot = $na = $nb = 0.0;
        foreach ($a as $i => $x) {
            $y = $b[$i] ?? 0.0;
            $dot += $x * $y;
            $na += $x * $x;
            $nb += $y * $y;
        }

        return $na && $nb ? $dot / sqrt($na * $nb) : 0.0;
    }

    private static function embed(array $teks, string $tugas): array
    {
        $model = config('services.gemini.model');
        $hasil = [];

        foreach (array_chunk($teks, self::BATCH_GEMINI) as $kelompok) {
            $res = Http::withHeaders(['x-goog-api-key' => config('services.gemini.key')])->timeout(60)
                ->post(config('services.gemini.url')."/models/$model:batchEmbedContents", [
                    'requests' => array_map(fn ($t) => [
                        'model' => "models/$model",
                        'content' => ['parts' => [['text' => $t]]],
                        'taskType' => $tugas,
                        'outputDimensionality' => config('services.gemini.dimensi'),
                    ], $kelompok),
                ])->throw();

            foreach ($res->json('embeddings') ?? [] as $e) {
                $hasil[] = $e['values'];
            }
        }

        if (count($hasil) !== count($teks)) {
            throw new RuntimeException('Jumlah embedding dari Gemini tidak sesuai jumlah teks.');
        }

        return $hasil;
    }

    private static function teksDocx(string $path): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true || ($xml = $zip->getFromName('word/document.xml')) === false) {
            throw new RuntimeException('Berkas DOCX tidak dapat dibaca.');
        }
        $zip->close();

        return html_entity_decode(strip_tags(str_replace('</w:p>', "\n\n", $xml)));
    }
}
