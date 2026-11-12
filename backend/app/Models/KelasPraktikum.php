<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Kelas praktikum; dosen yang mendaftarkan menjadi dosen pengampu kelas (KF-09). */
class KelasPraktikum extends Model
{
    protected $table = 'kelas_praktikum';
    protected $primaryKey = 'id_kelas';
    public $timestamps = false;

    protected $fillable = ['id_praktikum', 'id_dosen', 'kode_kelas'];

    public function praktikum(): BelongsTo
    {
        return $this->belongsTo(Praktikum::class, 'id_praktikum', 'id_praktikum');
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'id_dosen', 'id_pengguna');
    }

    /** Asisten yang ditetapkan pada kelas ini (tabel penugasan_asisten). */
    public function asisten(): BelongsToMany
    {
        return $this->belongsToMany(Pengguna::class, 'penugasan_asisten', 'id_kelas', 'id_asisten');
    }
}
