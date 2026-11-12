<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Pengguna extends Authenticatable
{
    use HasApiTokens, HasFactory;

    public const PERAN_ASISTEN = 'asisten';
    public const PERAN_DOSEN = 'dosen';
    public const PERAN_LABORAN = 'laboran';

    protected $table = 'pengguna';
    protected $primaryKey = 'id_pengguna';
    public $timestamps = false;

    /** Kolom kata sandi memakai nama `kata_sandi`, bukan `password`. */
    protected $authPasswordName = 'kata_sandi';

    protected $fillable = ['nama', 'username', 'email', 'kata_sandi', 'peran', 'nomor_induk'];

    protected $hidden = ['kata_sandi'];

    protected function casts(): array
    {
        // KNF-02: kata sandi disimpan sebagai hash, bukan teks biasa.
        return ['kata_sandi' => 'hashed'];
    }

    public function getRememberTokenName()
    {
        return null; // autentikasi berbasis token API, tanpa remember token
    }

    /** Kelas praktikum tempat asisten ditugaskan (tabel penugasan_asisten). */
    public function kelasDitugaskan(): BelongsToMany
    {
        return $this->belongsToMany(KelasPraktikum::class, 'penugasan_asisten', 'id_asisten', 'id_kelas');
    }
}
