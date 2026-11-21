<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** US14: dokumen sumber chatbot dan potongannya (KF-21, KF-22). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dokumen_chatbot', function (Blueprint $table) {
            $table->bigIncrements('id_dokumen');
            $table->string('nama_berkas', 255);
            $table->string('path', 255);
            $table->foreignId('id_laboran')->constrained('pengguna', 'id_pengguna');
            $table->timestamp('tanggal_unggah');
        });

        Schema::create('potongan_dokumen', function (Blueprint $table) {
            $table->bigIncrements('id_potongan');
            $table->foreignId('id_dokumen')->constrained('dokumen_chatbot', 'id_dokumen')->cascadeOnDelete();
            $table->text('isi');
            $table->json('vektor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('potongan_dokumen');
        Schema::dropIfExists('dokumen_chatbot');
    }
};
