<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** US02 Mencatat presensi, US03 Mengunduh BAP. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bap', function (Blueprint $table) {
            $table->bigIncrements('id_bap');
            $table->foreignId('id_asisten')->constrained('pengguna', 'id_pengguna');
            $table->foreignId('id_kelas')->constrained('kelas_praktikum', 'id_kelas');
            $table->foreignId('id_periode_bap')->constrained('periode_bap', 'id_periode_bap');
            $table->date('tanggal_dibuat');
            $table->unique(['id_asisten', 'id_kelas', 'id_periode_bap']);
        });

        Schema::create('presensi', function (Blueprint $table) {
            $table->bigIncrements('id_presensi');
            $table->foreignId('id_asisten')->constrained('pengguna', 'id_pengguna');
            $table->foreignId('id_kelas')->constrained('kelas_praktikum', 'id_kelas'); // kelas terisi otomatis dari penugasan (KF-03)
            $table->foreignId('id_bap')->nullable()->constrained('bap', 'id_bap')->nullOnDelete(); // rekapitulasi US04
            $table->date('tanggal');
            $table->string('kegiatan', 150);
            $table->time('jam_masuk');
            $table->time('jam_keluar');
            $table->string('foto_kegiatan', 255); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presensi');
        Schema::dropIfExists('bap');
    }
};
