<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** US08–US09: komponen penilaian (templat laboratorium, kategori A/B/C: 3 + 4 + 4 komponen), penilaian per asisten per kelas, dan nilainya. */
return new class extends Migration
{
    // Komponen mengikuti Template Penilaian Asisten Praktikum.xlsx milik laboratorium.
    private const KOMPONEN = [
        'A' => ['Kualitas', 'Ketepatan Waktu dan Efisiensi', 'Kuantitas'],
        'B' => ['Disiplin Waktu', 'Kerjasama Tim', 'Etika Kerja', 'Inisiatif'],
        'C' => ['Attitude', 'Keinginan Untuk Maju', 'Tanggung Jawab', 'Terampil dan Tanggap'],
    ];

    public function up(): void
    {
        Schema::create('komponen_penilaian', function (Blueprint $table) {
            $table->id('id_komponen');
            $table->char('kategori', 1);
            $table->string('nama', 100);
            $table->unsignedSmallInteger('urut');
        });

        // Komponen diisi pengembang (batasan proposal), sehingga ikut migration agar selalu ada.
        $urut = 0;
        foreach (self::KOMPONEN as $kategori => $daftar) {
            foreach ($daftar as $nama) {
                DB::table('komponen_penilaian')->insert(['kategori' => $kategori, 'nama' => $nama, 'urut' => ++$urut]);
            }
        }

        Schema::create('penilaian', function (Blueprint $table) {
            $table->id('id_penilaian');
            $table->foreignId('id_kelas')->constrained('kelas_praktikum', 'id_kelas')->cascadeOnDelete();
            $table->foreignId('id_asisten')->constrained('pengguna', 'id_pengguna')->cascadeOnDelete();
            $table->string('status', 10)->default('draft'); // draft | terkirim
            $table->timestamp('dikirim_pada')->nullable();
            $table->timestamps();
            $table->unique(['id_kelas', 'id_asisten']);
        });

        Schema::create('nilai_komponen', function (Blueprint $table) {
            $table->foreignId('id_penilaian')->constrained('penilaian', 'id_penilaian')->cascadeOnDelete();
            $table->foreignId('id_komponen')->constrained('komponen_penilaian', 'id_komponen');
            $table->unsignedSmallInteger('nilai');
            $table->primary(['id_penilaian', 'id_komponen']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nilai_komponen');
        Schema::dropIfExists('penilaian');
        Schema::dropIfExists('komponen_penilaian');
    }
};
