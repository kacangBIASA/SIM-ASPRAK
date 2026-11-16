<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Presensi extends Model
{
    protected $table = 'presensi';
    protected $primaryKey = 'id_presensi';
    public $timestamps = false;

    protected $fillable = ['id_asisten', 'id_kelas', 'tanggal', 'kegiatan', 'jam_masuk', 'jam_keluar', 'foto_kegiatan'];

    protected $hidden = ['foto_kegiatan'];

    protected function casts(): array
    {
        return ['tanggal' => 'date:Y-m-d'];
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(KelasPraktikum::class, 'id_kelas', 'id_kelas');
    }

    public function fotoDataUrl(): ?string
    {
        $disk = Storage::disk(config('filesystems.upload_disk'));
        if (! $disk->exists($this->foto_kegiatan)) {
            return null;
        }
        $mime = $disk->mimeType($this->foto_kegiatan) ?: 'image/jpeg';

        return "data:$mime;base64,".base64_encode($disk->get($this->foto_kegiatan));
    }

    public static function kolomUrut(): array
    {
        return [
            'tanggal' => 'presensi.tanggal', 'jam_masuk' => 'presensi.jam_masuk', 'jam_keluar' => 'presensi.jam_keluar', 'modul' => 'presensi.kegiatan',
            'praktikum' => '(select p.nama_praktikum from kelas_praktikum k join praktikum p on p.id_praktikum = k.id_praktikum where k.id_kelas = presensi.id_kelas)',
            'kelas' => '(select k.kode_kelas from kelas_praktikum k where k.id_kelas = presensi.id_kelas)',
        ];
    }

    public function durasiMenit(): int
    {
        $menit = fn (string $jam) => (int) substr($jam, 0, 2) * 60 + (int) substr($jam, 3, 2);

        return $menit($this->jam_keluar) - $menit($this->jam_masuk);
    }

    public function hari(): string
    {
        return ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][$this->tanggal->dayOfWeek];
    }

    public static function formatJam(int $menit): string
    {
        return str_replace('.', ',', (string) round($menit / 60, 2)).' Jam';
    }
}
