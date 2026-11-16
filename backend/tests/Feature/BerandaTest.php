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
        $this->presensi($asisten, $kelas, '2026-10-28');
        $this->masukSebagai($asisten);

        $this->getJson('/api/beranda')
            ->assertOk()
            ->assertJsonPath('statistik', [2, '5 Jam', 1])
            ->assertJsonCount(3, 'baris')
            ->assertJsonPath('baris.0', ['tanggal' => '2026-11-18', 'praktikum' => 'SI3104 · Praktikum SI3104', 'kelas' => 'IS-05-01', 'jam_masuk' => '08:00', 'jam_keluar' => '10:30']);

        $this->getJson('/api/beranda?q=2026-10')->assertJsonCount(1, 'baris');
        $this->getJson('/api/beranda?q=tidak-ada')->assertJsonCount(0, 'baris');
    }

    public function test_beranda_dosen(): void
    {
        [, $kelas] = $this->asistenDenganKelas();
        $this->tugaskan(Pengguna::factory()->asisten()->create(), $kelas);
        $this->buatKelas(kodeKelas: 'IS-05-02');
        $this->masukSebagai($kelas->dosen);

        $this->getJson('/api/beranda')
            ->assertOk()
            ->assertJsonPath('statistik', [1, 2, 2])
            ->assertJsonPath('baris', [['kode_mk' => 'SI3104', 'praktikum' => 'Praktikum SI3104', 'kelas' => 'IS-05-01', 'jumlah_asisten' => 2, 'status' => 'belum']]);
    }

    public function test_beranda_laboran(): void
    {
        [$asisten, $kelas] = $this->asistenDenganKelas();
        $this->buatKelas(kodeKelas: 'IS-05-02', prodi: 'Informatika');
        $this->presensi($asisten, $kelas, '2026-11-18');
        $this->masukSebagai(Pengguna::factory()->admin()->create());

        $this->getJson('/api/beranda')
            ->assertOk()
            ->assertJsonPath('statistik', [1, 1, 1])
            ->assertJsonCount(2, 'baris')
            ->assertJsonPath('baris.0.dosen', $kelas->dosen->nama)->assertJsonPath('baris.0.jumlah_asisten', 1);

        $this->getJson('/api/beranda?q=IS-05-02')->assertJsonCount(1, 'baris');

        $this->getJson('/api/beranda')->assertJsonCount(1, 'praktikum_opsi');
        $this->getJson("/api/beranda?praktikum={$kelas->id_praktikum}")->assertJsonCount(2, 'baris');
        $this->getJson('/api/beranda')->assertJsonCount(2, 'prodi_opsi')->assertJsonPath('baris.1.prodi', 'Informatika');
        $this->getJson('/api/beranda?prodi=Informatika')->assertJsonCount(1, 'baris')->assertJsonPath('baris.0.kelas', 'IS-05-02');
        $this->getJson("/api/beranda?praktikum={$kelas->id_praktikum}&prodi=Sistem Informasi&q=IS-05-02")->assertJsonCount(0, 'baris');
        $this->getJson('/api/beranda?praktikum=999999')->assertJsonCount(0, 'baris');
    }

    public function test_beranda_tanpa_login_ditolak(): void
    {
        $this->getJson('/api/beranda')->assertStatus(401);
    }
}
