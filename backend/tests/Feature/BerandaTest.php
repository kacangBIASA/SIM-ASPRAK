<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use App\Models\Presensi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** UC01 langkah 5: isi beranda per peran (wireframe 2–4). */
class BerandaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-20 09:00');
    }

    private function presensi(Pengguna $asisten, $kelas, string $tanggal): void
    {
        Presensi::create([
            'id_asisten' => $asisten->id_pengguna, 'id_kelas' => $kelas->id_kelas, 'tanggal' => $tanggal,
            'kegiatan' => 'Modul 1', 'jam_masuk' => '08:00:00', 'jam_keluar' => '10:30:00', 'foto_kegiatan' => 'x.jpg',
        ]);
    }

    public function test_beranda_asisten(): void
    {
        [$asisten, $kelas] = $this->asistenDenganKelas();
        $this->presensi($asisten, $kelas, '2026-11-18');
        $this->presensi($asisten, $kelas, '2026-11-11');
        $this->presensi($asisten, $kelas, '2026-10-28'); // bulan lalu
        $this->masukSebagai($asisten);

        $this->getJson('/api/beranda')
            ->assertOk()
            ->assertJsonPath('statistik', [2, '5 Jam', 1])
            ->assertJsonCount(3, 'baris')
            ->assertJsonPath('baris.0', ['2026-11-18', 'SI3104 · Praktikum SI3104 (IS-05-01)', '08:00', '10:30']);
    }

    public function test_beranda_dosen(): void
    {
        [, $kelas] = $this->asistenDenganKelas();
        $this->tugaskan(Pengguna::factory()->asisten()->create(), $kelas);
        $this->buatKelas(kodeKelas: 'IS-05-02'); // dosen lain
        $this->masukSebagai($kelas->dosen);

        $this->getJson('/api/beranda')
            ->assertOk()
            ->assertJsonPath('statistik', [1, 2, 2])
            ->assertJsonPath('baris', [['SI3104 · Praktikum SI3104 (IS-05-01)', 2, '0/2 terkirim']]);
    }

    public function test_beranda_laboran(): void
    {
        [$asisten, $kelas] = $this->asistenDenganKelas();
        $this->buatKelas(kodeKelas: 'IS-05-02');
        $this->presensi($asisten, $kelas, '2026-11-18');
        $this->masukSebagai(Pengguna::factory()->laboran()->create());

        $this->getJson('/api/beranda')
            ->assertOk()
            ->assertJsonPath('statistik', [1, 1, 1]) // 1 asisten aktif, 1 mata kuliah (2 kelas), 1 presensi
            ->assertJsonCount(2, 'baris')
            ->assertJsonPath('baris.0.1', $kelas->dosen->nama);
    }

    public function test_beranda_tanpa_login_ditolak(): void
    {
        $this->getJson('/api/beranda')->assertStatus(401);
    }
}
