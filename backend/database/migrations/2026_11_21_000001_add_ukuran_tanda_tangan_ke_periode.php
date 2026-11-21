<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Ukuran tanda tangan pada sertifikat (% lebar halaman), diatur laboran bersama posisi stempel. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periode', fn (Blueprint $table) => $table->float('ttd_ukuran')->nullable());
    }

    public function down(): void
    {
        Schema::table('periode', fn (Blueprint $table) => $table->dropColumn('ttd_ukuran'));
    }
};
