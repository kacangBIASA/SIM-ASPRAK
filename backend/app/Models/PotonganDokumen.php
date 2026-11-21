<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bagian dokumen chatbot beserta vektornya (KF-22). */
class PotonganDokumen extends Model
{
    protected $table = 'potongan_dokumen';
    protected $primaryKey = 'id_potongan';
    public $timestamps = false;

    protected $fillable = ['id_dokumen', 'isi', 'vektor'];

    protected function casts(): array
    {
        return ['vektor' => 'array'];
    }

    public function dokumen(): BelongsTo
    {
        return $this->belongsTo(DokumenChatbot::class, 'id_dokumen', 'id_dokumen');
    }
}
