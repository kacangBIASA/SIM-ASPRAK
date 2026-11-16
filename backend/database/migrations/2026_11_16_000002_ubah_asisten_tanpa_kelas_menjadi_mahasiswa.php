<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('pengguna')->where('peran', 'asisten')
            ->whereNotIn('id_pengguna', DB::table('penugasan_asisten')->select('id_asisten'))
            ->update(['peran' => 'mahasiswa']);
    }

    public function down(): void
    {
        DB::table('pengguna')->where('peran', 'mahasiswa')->update(['peran' => 'asisten']);
    }
};
