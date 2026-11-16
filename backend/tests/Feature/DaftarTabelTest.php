<?php

namespace Tests\Feature;

use App\Models\Bap;
use App\Models\Pengguna;
use App\Models\Periode;
use App\Models\Presensi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Riwayat presensi + foto, pencarian dan filter pada tabel (wireframe), ukuran halaman `per`. */
class DaftarTabelTest extends TestCase
{
    use RefreshDatabase;

    private function presensi(Pengguna $asisten, $kelas, string $foto = 'x.jpg', string $modul = 'Modul 1'): Presensi
    {
        return Presensi::create(['id_asisten' => $asisten->id_pengguna, 'id_kelas' => $kelas->id_kelas, 'tanggal' => now()->toDateString(),
            'kegiatan' => $modul, 'jam_masuk' => '08:00:00', 'jam_keluar' => '10:00:00', 'foto_kegiatan' => $foto]);
    }

    public function test_riwayat_presensi_hanya_milik_sendiri_dan_dapat_dicari(): void
    {
        [$asisten, $kelas] = $this->asistenDenganKelas();
        $this->presensi($asisten, $kelas, modul: 'Modul Basis Data');
        $this->presensi($asisten, $kelas, modul: 'Modul Jaringan');
        $lain = Pengguna::factory()->asisten()->create();
        $this->tugaskan($lain, $kelas);
        $this->presensi($lain, $kelas);
        $this->masukSebagai($asisten);

        $this->getJson('/api/presensi/riwayat')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('halaman.total', 2);
        $this->getJson('/api/presensi/riwayat?q=jaringan')->assertJsonCount(1, 'data')->assertJsonPath('data.0.modul', 'Modul Jaringan');
        $this->getJson('/api/presensi/riwayat?per=5&page=1')->assertJsonPath('halaman.per', 5)->assertJsonPath('halaman.ke', 2);
        $this->getJson('/api/presensi/riwayat?per=7')->assertJsonPath('halaman.per', 10);
    }

    public function test_foto_presensi_hanya_untuk_pemiliknya(): void
    {
        $disk = Storage::fake(config('filesystems.upload_disk'));
        [$asisten, $kelas] = $this->asistenDenganKelas();
        $p = $this->presensi($asisten, $kelas, $disk->putFile('presensi', UploadedFile::fake()->image('f.png')));
        $lain = Pengguna::factory()->asisten()->create();

        $this->masukSebagai($lain);
        $this->getJson("/api/presensi/{$p->id_presensi}/foto")->assertStatus(403);

        $this->masukSebagai($asisten);
        $this->assertStringStartsWith('data:image/png;base64,', $this->getJson("/api/presensi/{$p->id_presensi}/foto")->assertOk()->json('url'));
    }

    public function test_pencarian_kelas_praktikum_dosen_dan_asisten_kelas(): void
    {
        [$asisten, $kelas] = $this->asistenDenganKelas();
        $this->masukSebagai($kelas->dosen);

        $this->getJson('/api/kelas-praktikum?q=si3104')->assertJsonCount(1, 'data');
        $this->getJson('/api/kelas-praktikum?q=zzz')->assertJsonCount(0, 'data');
        $this->getJson("/api/kelas-praktikum/{$kelas->id_kelas}?q=".urlencode($asisten->nama))->assertJsonCount(1, 'data.asisten');
        $this->getJson("/api/kelas-praktikum/{$kelas->id_kelas}?q=zzz")->assertJsonCount(0, 'data.asisten');
    }

    public function test_profil_memuat_periode_aktif(): void
    {
        $this->masukSebagai(Pengguna::factory()->admin()->create());
        $this->getJson('/api/me')->assertJsonPath('pengguna.periode', Periode::berjalan()->nama_periode);
    }
}
