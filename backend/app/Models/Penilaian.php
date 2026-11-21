<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Penilaian kinerja satu asisten pada satu kelas (KF-12–KF-16). Status: draft atau terkirim. */
class Penilaian extends Model
{
    public const DRAFT = 'draft';
    public const TERKIRIM = 'terkirim';

    protected $table = 'penilaian';
    protected $primaryKey = 'id_penilaian';

    protected $fillable = ['id_kelas', 'id_asisten', 'status', 'dikirim_pada'];

    protected function casts(): array
    {
        return ['dikirim_pada' => 'datetime'];
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(KelasPraktikum::class, 'id_kelas', 'id_kelas');
    }

    public function asisten(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'id_asisten', 'id_pengguna');
    }

    public function sertifikat(): HasOne
    {
        return $this->hasOne(Sertifikat::class, 'id_penilaian', 'id_penilaian');
    }

    public function komponen(): BelongsToMany
    {
        return $this->belongsToMany(KomponenPenilaian::class, 'nilai_komponen', 'id_penilaian', 'id_komponen')->withPivot('nilai');
    }

    /** @return array<int,int> id_komponen => nilai */
    public function nilai(): array
    {
        return $this->komponen->mapWithKeys(fn ($k) => [$k->id_komponen => (int) $k->pivot->nilai])->all();
    }

    /**
     * KF-14, rumus templat laboratorium: rata-rata per kategori, lalu rataan akhir = rata-rata ketiga kategori
     * (tanpa pembulatan antara; hasil dibulatkan dua desimal). Kategori belum lengkap → null; rataan akhir null sampai ketiganya lengkap.
     *
     * @param  array<int,int>  $nilai  id_komponen => nilai
     * @return array{kategori: array<string,?float>, akhir: ?float}
     */
    public static function hitung(array $nilai): array
    {
        $rata = [];
        foreach (KomponenPenilaian::orderBy('urut')->get()->groupBy('kategori') as $k => $daftar) {
            $isi = $daftar->map(fn ($c) => $nilai[$c->id_komponen] ?? null);
            $rata[$k] = $isi->contains(null) ? null : $isi->avg();
        }
        $akhir = in_array(null, $rata, true) ? null : round(array_sum($rata) / count($rata), 2);

        return ['kategori' => array_map(fn ($r) => $r === null ? null : round($r, 2), $rata), 'akhir' => $akhir];
    }

    /** Predikat pada sertifikat, mis. 81 → "Baik Sekali" (contoh sertifikat laboratorium). */
    public static function predikat(float $nilai): string
    {
        // shortcut: batas predikat ditebak dari satu contoh sertifikat (81 = Baik Sekali); sesuaikan bila laboratorium punya tabel resmi.
        return match (true) {
            $nilai >= 90 => 'Istimewa',
            $nilai >= 80 => 'Baik Sekali',
            $nilai >= 70 => 'Baik',
            $nilai >= 60 => 'Cukup',
            default => 'Kurang',
        };
    }
}
