<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US01 Login — hanya tabel `pengguna` (Physical Data Model).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengguna', function (Blueprint $table) {
            $table->bigIncrements('id_pengguna');
            $table->string('nama', 100);
            $table->string('username', 100)->unique();
            $table->string('email', 100)->unique();
            $table->string('kata_sandi', 255);
            $table->string('peran', 10); // asisten | dosen | laboran
            $table->string('nomor_induk', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengguna');
    }
};
