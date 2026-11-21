<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Dokumen sumber pengetahuan chatbot yang diunggah laboran (KF-21). */
class DokumenChatbot extends Model
{
    protected $table = 'dokumen_chatbot';
    protected $primaryKey = 'id_dokumen';
    public $timestamps = false;

    protected $fillable = ['nama_berkas', 'path', 'id_laboran', 'tanggal_unggah'];

    protected $hidden = ['path'];

    protected function casts(): array
    {
        return ['tanggal_unggah' => 'datetime'];
    }

    public function potongan(): HasMany
    {
        return $this->hasMany(PotonganDokumen::class, 'id_dokumen', 'id_dokumen');
    }
}
