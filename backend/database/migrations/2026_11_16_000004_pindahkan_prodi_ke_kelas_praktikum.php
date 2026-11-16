<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kelas_praktikum', fn (Blueprint $table) => $table->string('prodi', 50)->nullable());
        DB::statement('UPDATE kelas_praktikum SET prodi = (SELECT p.prodi FROM praktikum p WHERE p.id_praktikum = kelas_praktikum.id_praktikum)');
        Schema::table('praktikum', fn (Blueprint $table) => $table->dropColumn('prodi'));
    }

    public function down(): void
    {
        Schema::table('praktikum', fn (Blueprint $table) => $table->string('prodi', 50)->nullable());
        DB::statement('UPDATE praktikum SET prodi = (SELECT k.prodi FROM kelas_praktikum k WHERE k.id_praktikum = praktikum.id_praktikum ORDER BY k.id_kelas LIMIT 1)');
        Schema::table('kelas_praktikum', fn (Blueprint $table) => $table->dropColumn('prodi'));
    }
};
