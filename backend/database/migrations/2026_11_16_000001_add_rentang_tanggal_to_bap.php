<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bap', function (Blueprint $table) {
            $table->dropUnique(['id_asisten', 'id_kelas', 'id_periode_bap']);
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
        });

        foreach (['tanggal_mulai', 'tanggal_selesai'] as $kolom) {
            DB::statement("UPDATE bap SET $kolom = (SELECT pb.$kolom FROM periode_bap pb WHERE pb.id_periode_bap = bap.id_periode_bap)");
        }

        Schema::table('bap', fn (Blueprint $table) => $table->unique(['id_asisten', 'id_kelas', 'tanggal_mulai', 'tanggal_selesai']));
    }

    public function down(): void
    {
        Schema::table('bap', function (Blueprint $table) {
            $table->dropUnique(['id_asisten', 'id_kelas', 'tanggal_mulai', 'tanggal_selesai']);
            $table->dropColumn(['tanggal_mulai', 'tanggal_selesai']);
            $table->unique(['id_asisten', 'id_kelas', 'id_periode_bap']);
        });
    }
};
