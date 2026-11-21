<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Komponen penilaian kinerja asisten menurut templat laboratorium; kategori A (hasil kerja), B (sikap kerja), C (personal). */
class KomponenPenilaian extends Model
{
    public const KATEGORI = ['A' => 'Hasil kerja praktikum', 'B' => 'Sikap kerja', 'C' => 'Personal'];

    protected $table = 'komponen_penilaian';
    protected $primaryKey = 'id_komponen';
    public $timestamps = false;
}
