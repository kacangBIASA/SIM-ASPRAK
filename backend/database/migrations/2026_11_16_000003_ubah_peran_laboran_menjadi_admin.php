<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('pengguna')->where('peran', 'laboran')->update(['peran' => 'admin', 'nomor_induk' => null]);
    }

    public function down(): void
    {
        DB::table('pengguna')->where('peran', 'admin')->update(['peran' => 'laboran']);
    }
};
