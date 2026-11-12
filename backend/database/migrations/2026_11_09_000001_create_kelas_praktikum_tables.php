<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US05 Mendaftarkan kelas praktikum, US06 Menetapkan asisten, US11 Nomor surat sertifikat.
 * Periode (semester) dan periode BAP dibentuk otomatis oleh sistem (KF-05.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periode', function (Blueprint $table) {
            $table->bigIncrements('id_periode');
            $table->string('nama_periode', 50)->unique();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->string('nomor_surat', 50)->nullable()->unique(); // US11: satu nomor surat sertifikat per periode
        });

        Schema::create('periode_bap', function (Blueprint $table) {
            $table->bigIncrements('id_periode_bap');
            $table->foreignId('id_periode')->constrained('periode', 'id_periode')->cascadeOnDelete();
            $table->string('nama_periode_bap', 50);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
        });

        // Mata kuliah praktikum per periode; kelas dengan kode MK sama digabung ke sini (KF-10).
        Schema::create('praktikum', function (Blueprint $table) {
            $table->bigIncrements('id_praktikum');
            $table->foreignId('id_periode')->constrained('periode', 'id_periode');
            $table->string('kode_praktikum', 20);
            $table->string('nama_praktikum', 100);
            $table->string('prodi', 50);
            $table->unique(['id_periode', 'kode_praktikum']);
        });

        // Kelas praktikum; dosen yang mendaftarkan = dosen pengampu kelas (KF-09).
        Schema::create('kelas_praktikum', function (Blueprint $table) {
            $table->bigIncrements('id_kelas');
            $table->foreignId('id_praktikum')->constrained('praktikum', 'id_praktikum')->cascadeOnDelete();
            $table->foreignId('id_dosen')->constrained('pengguna', 'id_pengguna');
            $table->string('kode_kelas', 20);
            $table->unique(['id_praktikum', 'kode_kelas']); // KF-10: kode kelas tidak boleh ganda dalam satu praktikum
        });

        // KF-11: PK (id_praktikum, id_asisten) → satu asisten hanya di satu kelas per praktikum, dijaga basis data.
        Schema::create('penugasan_asisten', function (Blueprint $table) {
            $table->foreignId('id_praktikum')->constrained('praktikum', 'id_praktikum')->cascadeOnDelete();
            $table->foreignId('id_asisten')->constrained('pengguna', 'id_pengguna')->cascadeOnDelete();
            $table->foreignId('id_kelas')->constrained('kelas_praktikum', 'id_kelas')->cascadeOnDelete();
            $table->primary(['id_praktikum', 'id_asisten']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penugasan_asisten');
        Schema::dropIfExists('kelas_praktikum');
        Schema::dropIfExists('praktikum');
        Schema::dropIfExists('periode_bap');
        Schema::dropIfExists('periode');
    }
};
