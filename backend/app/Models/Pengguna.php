<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Pengguna extends Authenticatable
{
    use HasApiTokens, HasFactory;

    public const PERAN_MAHASISWA = 'mahasiswa';
    public const PERAN_ASISTEN = 'asisten';
    public const PERAN_DOSEN = 'dosen';
    public const PERAN_ADMIN = 'admin';

    protected $table = 'pengguna';
    protected $primaryKey = 'id_pengguna';
    public $timestamps = false;

    protected $authPasswordName = 'kata_sandi';

    protected $fillable = ['nama', 'username', 'email', 'kata_sandi', 'peran', 'nomor_induk'];

    protected $hidden = ['kata_sandi'];

    protected function casts(): array
    {
        return ['kata_sandi' => 'hashed'];
    }

    public function getRememberTokenName()
    {
        return null;
    }

    public function segarkanPeran(): void
    {
        if (! in_array($this->peran, [self::PERAN_MAHASISWA, self::PERAN_ASISTEN], true)) {
            return;
        }

        $mulai = Periode::berjalan()->tanggal_mulai->toDateString();
        $aktif = $this->kelasDitugaskan()->whereHas('praktikum.periode', fn ($q) => $q->whereDate('tanggal_mulai', '>=', $mulai))->exists();
        $peran = $aktif ? self::PERAN_ASISTEN : self::PERAN_MAHASISWA;

        if ($peran !== $this->peran) {
            $this->update(['peran' => $peran]);
        }
    }

    public function kelasDitugaskan(): BelongsToMany
    {
        return $this->belongsToMany(KelasPraktikum::class, 'penugasan_asisten', 'id_asisten', 'id_kelas');
    }
}
