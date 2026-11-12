<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeriodeBap extends Model
{
    protected $table = 'periode_bap';
    protected $primaryKey = 'id_periode_bap';
    public $timestamps = false;

    protected $fillable = ['id_periode', 'nama_periode_bap', 'tanggal_mulai', 'tanggal_selesai'];

    protected function casts(): array
    {
        return ['tanggal_mulai' => 'date:Y-m-d', 'tanggal_selesai' => 'date:Y-m-d'];
    }
}
