<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Sertifikat asisten, diterbitkan laboran dari penilaian yang sudah terkirim (KF-19, KF-20). */
class Sertifikat extends Model
{
    protected $table = 'sertifikat';
    protected $primaryKey = 'id_sertifikat';

    protected $fillable = ['id_penilaian', 'id_laboran', 'nomor_surat', 'tanggal_terbit', 'berkas_sertifikat'];

    protected function casts(): array
    {
        return ['tanggal_terbit' => 'date:Y-m-d'];
    }

    public function penilaian(): BelongsTo
    {
        return $this->belongsTo(Penilaian::class, 'id_penilaian', 'id_penilaian');
    }
}
