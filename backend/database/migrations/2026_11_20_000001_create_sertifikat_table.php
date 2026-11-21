<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** US12: sertifikat yang diterbitkan laboran dari penilaian terkirim — satu sertifikat per penilaian (KF-19, KF-20). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sertifikat', function (Blueprint $table) {
            $table->id('id_sertifikat');
            $table->foreignId('id_penilaian')->unique()->constrained('penilaian', 'id_penilaian')->cascadeOnDelete();
            $table->foreignId('id_laboran')->constrained('pengguna', 'id_pengguna');
            $table->string('nomor_surat', 50); // salinan saat terbit, agar sertifikat lama tidak berubah bila nomor periode diganti
            $table->date('tanggal_terbit');
            $table->string('berkas_sertifikat', 255)->nullable(); // PDF yang dihasilkan saat terbit
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sertifikat');
    }
};
