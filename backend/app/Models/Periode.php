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

    protected $fillable = ['nama_periode', 'tanggal_mulai', 'tanggal_selesai', 'nomor_surat', 'path_tanda_tangan', 'path_stempel', 'nama_penandatangan', 'nip_penandatangan', 'stempel_x', 'stempel_y', 'stempel_ukuran', 'ttd_ukuran'];

    protected function casts(): array
    {
        return ['tanggal_mulai' => 'date:Y-m-d', 'tanggal_selesai' => 'date:Y-m-d'];
    }

    public function periodeBap(): HasMany
    {
        return $this->hasMany(PeriodeBap::class, 'id_periode', 'id_periode')->orderBy('tanggal_mulai');
    }

    public static function berjalan(): self
    {
        $t = now()->startOfDay();
        foreach ([[$t->year, true], [$t->year - 1, false], [$t->year - 1, true]] as [$tahun, $ganjil]) {
            if (self::semester($tahun, $ganjil)[1]->lte($t)) {
                break;
            }
        }

        return self::pastikan($tahun, $ganjil);
    }

    public static function berikutnya(): self
    {
        [$tahun, $ganjil] = self::tahunSemester(self::berjalan());

        return $ganjil ? self::pastikan($tahun, false) : self::pastikan($tahun + 1, true);
    }

    public static function lalu(): self
    {
        [$tahun, $ganjil] = self::tahunSemester(self::berjalan());

        return $ganjil ? self::pastikan($tahun - 1, false) : self::pastikan($tahun, true);
    }

    public static function sebelumnya(): ?self
    {
        return self::where('tanggal_mulai', '<', self::berjalan()->tanggal_mulai)->orderByDesc('tanggal_mulai')->first();
    }

    public static function pilih(mixed $id, bool $maju): self
    {
        $berjalan = self::berjalan();
        $id = (int) $id;
        if (! $id || $id === $berjalan->id_periode) {
            return $berjalan;
        }

        $lain = $maju ? self::berikutnya() : self::sebelumnya();
        abort_unless($lain?->id_periode === $id, 422, 'Periode tidak valid.');

        return $lain;
    }

    public static function opsi(bool $maju): array
    {
        $lain = $maju ? self::berikutnya() : self::sebelumnya();
        $baris = fn (self $p, string $ket) => ['id' => $p->id_periode, 'nama' => "{$p->nama_periode} ($ket)", 'berjalan' => $ket === 'berjalan'];
        $berjalan = $baris(self::berjalan(), 'berjalan');

        return $maju ? [$berjalan, $baris($lain, 'berikutnya')] : ($lain ? [$baris($lain, 'sebelumnya'), $berjalan] : [$berjalan]);
    }

    private static function tahunSemester(self $p): array
    {
        [$jenis, $tahun] = explode(' ', $p->nama_periode);

        return [(int) $tahun, $jenis === 'Ganjil'];
    }

    private static function pastikan(int $tahun, bool $ganjil): self
    {
        [$nama, $mulai, $selesai] = self::semester($tahun, $ganjil);
        $periode = self::where('nama_periode', $nama)->first();
        if ($periode && $periode->tanggal_mulai->isSameDay($mulai) && $periode->tanggal_selesai->isSameDay($selesai)) {
            return $periode;
        }

        return DB::transaction(function () use ($periode, $nama, $mulai, $selesai) {
            $periode ??= new self(['nama_periode' => $nama]);
            $periode->fill(['tanggal_mulai' => $mulai, 'tanggal_selesai' => $selesai])->save();
            $periode->susunPeriodeBap();

            return $periode;
        });
    }

    /** shortcut: aturan ini hasil pengamatan, bukan rumus resmi; bila SK baru bergeser, ubah angka di sini. */
    private static function semester(int $tahun, bool $ganjil): array
    {
        $mulai = $ganjil ? Carbon::create($tahun, 9, 14) : Carbon::create($tahun + 1, 2, 17);
        if (! $mulai->isMonday()) {
            $mulai->next(Carbon::MONDAY);
        }

        return [($ganjil ? 'Ganjil' : 'Genap')." $tahun/".($tahun + 1), $mulai, $mulai->copy()->addDays($ganjil ? 131 : 151)];
    }

    private function susunPeriodeBap(): void
    {
        $mulai = $this->tanggal_mulai->copy();
        $akhir = $this->tanggal_selesai->copy();
        $nama = [];

        while ($mulai->lte($akhir)) {
            $selesai = $mulai->day <= 25 ? $mulai->copy()->day(25) : $mulai->copy()->addMonthNoOverflow()->day(25);
            if ($selesai->copy()->addDays(7)->gte($akhir)) {
                $selesai = $akhir->copy();
            }

            $nama[] = 'BAP '.self::BULAN[$selesai->month - 1].' '.$selesai->year;
            PeriodeBap::updateOrCreate(
                ['id_periode' => $this->id_periode, 'nama_periode_bap' => end($nama)],
                ['tanggal_mulai' => $mulai->toDateString(), 'tanggal_selesai' => $selesai->toDateString()],
            );

            $mulai = $selesai->copy()->addDay();
        }

        PeriodeBap::where('id_periode', $this->id_periode)->whereNotIn('nama_periode_bap', $nama)
            ->whereNotIn('id_periode_bap', Bap::select('id_periode_bap'))->delete();
    }
}
