<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Mata kuliah praktikum pada satu periode; kelas dengan kode MK sama tergabung di sini (KF-10). */
class Praktikum extends Model
{
    protected $table = 'praktikum';
    protected $primaryKey = 'id_praktikum';
    public $timestamps = false;

    protected $fillable = ['id_periode', 'kode_praktikum', 'nama_praktikum'];

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class, 'id_periode', 'id_periode');
    }

    public function kelas(): HasMany
    {
        return $this->hasMany(KelasPraktikum::class, 'id_praktikum', 'id_praktikum');
    }
}
