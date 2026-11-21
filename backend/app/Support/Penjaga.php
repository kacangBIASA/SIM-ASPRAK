<?php

namespace App\Support;

/** shortcut: deteksi perintah memakai daftar pola tetap (bukan pemahaman maksud) sehingga huruf yang dipisah spasi */
class Penjaga
{
    private const POLA_PERINTAH = [
        '/\b(?:abaikan|lupakan|kesampingkan|hiraukan)\s+(?:semua\s+|seluruh\s+|segala\s+)?(?:instruksi|perintah|aturan|petunjuk|arahan)/u',
        '/\b(?:ignore|disregard|forget|override)\b.{0,30}\b(?:instructions?|rules?|prompts?|guidelines?)\b/u',
        '/\b(?:system|sistem)\s+prompt\b|\bprompt\s+sistem\b|\bprompt\s+(?:awal|rahasia|tersembunyi)\b/u',
        '/\b(?:tampilkan|tunjukkan|ungkapkan|bocorkan|sebutkan|reveal|show|print|repeat)\b.{0,25}\b(?:prompt|instruksi|instructions?)\b/u',
        '/\b(?:jailbreak|developer\s+mode|mode\s+pengembang|dan\s+mode|do\s+anything\s+now)\b/u',
        '/\b(?:berpura-?\s*pura|pura-?\s*pura)\s+(?:menjadi|sebagai|jadi)\b|\bpretend\s+(?:to\s+be|you\s+are)\b/u',
        '/\b(?:kamu|anda|kau)\s+(?:sekarang|kini)\s+(?:adalah|menjadi|bukan)\b|\byou\s+are\s+now\b/u',
        '/\b(?:ubah|ganti)\s+(?:peran|aturan|instruksi)\s+(?:kamu|anda|mu)\b/u',
    ];

    public static function normal(string $teks): string
    {
        if (class_exists(\Normalizer::class)) {
            $teks = \Normalizer::normalize($teks, \Normalizer::FORM_KC) ?: $teks;
        }
        $teks = preg_replace('/[\p{Cc}\p{Cf}]/u', ' ', $teks) ?? $teks;

        return trim(preg_replace('/\s+/u', ' ', mb_strtolower($teks)));
    }

    public static function menyerupaiPerintah(string $teks): bool
    {
        $normal = self::normal($teks);
        foreach (self::POLA_PERINTAH as $pola) {
            if (preg_match($pola, $normal)) {
                return true;
            }
        }

        return false;
    }

    public static function bersihkan(string $teks): string
    {
        $teks = preg_replace('/[\p{Cc}\p{Cf}]/u', ' ', $teks) ?? $teks;

        return trim(preg_replace('~</?\s*(?:dokumen|pertanyaan|system|assistant|user)\s*/?>~iu', ' ', $teks));
    }

    public static function saring(string $jawaban, string $konteks): string
    {
        $jawaban = preg_replace(['/\*\*|__|`/u', '/^\s{0,3}#{1,6}\s+/mu', '/^(\s*)\*\s+/mu', '/\s*\[\d+\]/u', '/\s*\((?:sumber|source)\s*:[^)]*\)/iu', '/\s*\([^()]*\.(?:pdf|docx|txt|md)\)/iu'], ['', '', '$1- ', '', '', ''], $jawaban);

        $teksKonteks = mb_strtolower($konteks);
        $angkaKonteks = preg_replace('/\D/', '', $konteks);

        $jawaban = preg_replace('/\[([^\]]*)\]\([^)]*\)/u', '$1', $jawaban);

        $jawaban = preg_replace_callback('~(?:https?://|www\.)[^\s<>")\]]+~iu',
            fn ($m) => str_contains($teksKonteks, mb_strtolower(rtrim($m[0], '.,;:'))) ? $m[0] : '[tautan dihapus]', $jawaban);

        $jawaban = preg_replace_callback('/[\w.+-]+@[\w-]+(?:\.[\w-]+)+/u',
            fn ($m) => str_contains($teksKonteks, mb_strtolower($m[0])) ? $m[0] : '[email dihapus]', $jawaban);

        return preg_replace_callback('/(?<![\w\/])(?:\+62|62|0)[\s().-]*\d(?:[\s().-]*\d){7,12}(?!\d)/',
            function ($m) use ($angkaKonteks) {
                $angka = preg_replace('/\D/', '', $m[0]);

                return str_contains($angkaKonteks, $angka) ? $m[0] : '[nomor dihapus]';
            }, $jawaban);
    }
}
