<?php

namespace Tests;

use App\Models\KelasPraktikum;
use App\Models\Pengguna;
use App\Models\Periode;
use App\Models\Praktikum;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    protected function masukSebagai(Pengguna $pengguna): Pengguna
    {
        Sanctum::actingAs($pengguna);

        return $pengguna;
    }

    /** Kelas baru pada periode tertentu (default: periode berjalan) dengan dosen pengampu baru. */
    protected function buatKelas(?Periode $periode = null, string $kodeMk = 'SI3104', string $kodeKelas = 'IS-05-01'): KelasPraktikum
    {
        $praktikum = Praktikum::firstOrCreate(
            ['id_periode' => ($periode ?? Periode::berjalan())->id_periode, 'kode_praktikum' => $kodeMk],
            ['nama_praktikum' => "Praktikum $kodeMk", 'prodi' => 'Sistem Informasi'],
        );

        return $praktikum->kelas()->create(['id_dosen' => Pengguna::factory()->dosen()->create()->id_pengguna, 'kode_kelas' => $kodeKelas]);
    }

    protected function tugaskan(Pengguna $asisten, KelasPraktikum $kelas): void
    {
        $kelas->asisten()->attach($asisten->id_pengguna, ['id_praktikum' => $kelas->id_praktikum]);
    }

    /** @return array{Pengguna, KelasPraktikum} asisten yang ditugaskan pada kelas baru di periode berjalan. */
    protected function asistenDenganKelas(): array
    {
        $asisten = Pengguna::factory()->asisten()->create();
        $kelas = $this->buatKelas();
        $this->tugaskan($asisten, $kelas);

        return [$asisten, $kelas];
    }
}
