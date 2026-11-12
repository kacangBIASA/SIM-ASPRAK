<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/** Periode = semester. Dibentuk otomatis oleh sistem (KF-05) lewat berjalan(). */
class Periode extends Model
{
    public const BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    protected $table = 'periode';
    protected $primaryKey = 'id_periode';
    public $timestamps = false;

    protected $fillable = ['nama_periode', 'tanggal_mulai', 'tanggal_selesai', 'nomor_surat'];

    protected function casts(): array
    {
        return ['tanggal_mulai' => 'date:Y-m-d', 'tanggal_selesai' => 'date:Y-m-d'];
    }

    public function periodeBap(): HasMany
    {
        return $this->hasMany(PeriodeBap::class, 'id_periode', 'id_periode')->orderBy('tanggal_mulai');
    }

    /**
     * Semester yang mencakup hari ini; dibuat beserta periode BAP-nya bila belum ada.
     * Ganjil = 1 Agustus – 31 Januari, Genap = 1 Februari – 31 Juli.
     */
    public static function berjalan(): self
    {
        $t = now();
        $tahun = $t->month >= 8 ? $t->year : $t->year - 1; // tahun awal tahun ajaran
        [$nama, $mulai, $selesai] = $t->month >= 2 && $t->month <= 7
            ? ["Genap $tahun/".($tahun + 1), Carbon::create($tahun + 1, 2, 1), Carbon::create($tahun + 1, 7, 31)]
            : ["Ganjil $tahun/".($tahun + 1), Carbon::create($tahun, 8, 1), Carbon::create($tahun + 1, 1, 31)];

        return self::where('nama_periode', $nama)->first() ?? DB::transaction(function () use ($nama, $mulai, $selesai) {
            $periode = self::create(['nama_periode' => $nama, 'tanggal_mulai' => $mulai, 'tanggal_selesai' => $selesai]);
            $periode->buatPeriodeBap();

            return $periode;
        });
    }

    /**
     * Periode BAP mengikuti jadwal laboratorium: tanggal 26 bulan sebelumnya s.d. tanggal 25.
     * Periode pertama dimulai di awal semester, periode terakhir ditutup di akhir semester.
     */
    private function buatPeriodeBap(): void
    {
        $mulai = $this->tanggal_mulai->copy();
        $akhir = $this->tanggal_selesai->copy();

        while ($mulai->lte($akhir)) {
            $selesai = $mulai->day <= 25 ? $mulai->copy()->day(25) : $mulai->copy()->addMonthNoOverflow()->day(25);
            if ($selesai->copy()->addMonthNoOverflow()->gt($akhir)) {
                $selesai = $akhir->copy();
            }

            $this->periodeBap()->create([
                'nama_periode_bap' => 'BAP '.self::BULAN[$selesai->month - 1].' '.$selesai->year,
                'tanggal_mulai' => $mulai->toDateString(),
                'tanggal_selesai' => $selesai->toDateString(),
            ]);

            $mulai = $selesai->copy()->addDay();
        }
    }
}
