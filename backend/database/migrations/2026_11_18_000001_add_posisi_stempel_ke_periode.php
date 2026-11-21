<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** US10: posisi stempel pada sertifikat, dalam persen terhadap lebar/tinggi halaman. Null = posisi bawaan. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periode', function (Blueprint $table) {
            $table->float('stempel_x')->nullable();
            $table->float('stempel_y')->nullable();
            $table->float('stempel_ukuran')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('periode', fn (Blueprint $table) => $table->dropColumn(['stempel_x', 'stempel_y', 'stempel_ukuran']));
    }
};
