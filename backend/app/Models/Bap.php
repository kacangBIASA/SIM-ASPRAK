<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bap extends Model
{
    protected $table = 'bap';
    protected $primaryKey = 'id_bap';
    public $timestamps = false;

    protected $fillable = ['id_asisten', 'id_kelas', 'id_periode_bap', 'tanggal_mulai', 'tanggal_selesai', 'tanggal_dibuat'];

    protected function casts(): array
    {
        return ['tanggal_dibuat' => 'date:Y-m-d', 'tanggal_mulai' => 'date:Y-m-d', 'tanggal_selesai' => 'date:Y-m-d'];
    }

    public function asisten(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'id_asisten', 'id_pengguna');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(KelasPraktikum::class, 'id_kelas', 'id_kelas');
    }

    public function periodeBap(): BelongsTo
    {
        return $this->belongsTo(PeriodeBap::class, 'id_periode_bap', 'id_periode_bap');
    }

    public function presensi(): HasMany
    {
        return $this->hasMany(Presensi::class, 'id_bap', 'id_bap')->orderBy('tanggal')->orderBy('jam_masuk');
    }
}
