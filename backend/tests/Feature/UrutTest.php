<?php

namespace Tests\Feature;

use App\Models\Bap;
use App\Models\KelasPraktikum;
use App\Models\Pengguna;
use App\Models\Periode;
use App\Models\Presensi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UrutTest extends TestCase
{
    use RefreshDatabase;

    private function semuaKunci(string $url, array $kunci): void
    {
        foreach ($kunci as $k) {
            foreach (['asc', 'desc'] as $arah) {
                $this->getJson("$url".(str_contains($url, '?') ? '&' : '?')."urut=$k&arah=$arah")->assertOk();
            }
        }
        $this->getJson("$url".(str_contains($url, '?') ? '&' : '?').'urut=bukan_kolom;drop&arah=desc')->assertOk();
    }

    private function presensi(Pengguna $a, KelasPraktikum $k, string $tanggal, string $masuk): Presensi
    {
        return Presensi::create(['id_asisten' => $a->id_pengguna, 'id_kelas' => $k->id_kelas, 'tanggal' => $tanggal, 'kegiatan' => 'Modul 1',
            'jam_masuk' => "$masuk:00", 'jam_keluar' => '17:00:00', 'foto_kegiatan' => 'x.jpg']);
    }

    public function test_riwayat_presensi_dan_beranda_asisten(): void
    {
        [$asisten, $kelas] = $this->asistenDenganKelas();
        $this->presensi($asisten, $kelas, now()->toDateString(), '08:00');
        $this->presensi($asisten, $kelas, now()->toDateString(), '13:00');
        $this->masukSebagai($asisten);

        $this->getJson('/api/presensi/riwayat?urut=jam_masuk&arah=asc')->assertJsonPath('data.0.jam_masuk', '08:00');
        $this->getJson('/api/presensi/riwayat?urut=jam_masuk&arah=desc')->assertJsonPath('data.0.jam_masuk', '13:00');
        $kunci = ['tanggal', 'praktikum', 'modul', 'jam_masuk', 'jam_keluar', 'kelas'];
        $this->semuaKunci('/api/presensi/riwayat', $kunci);
        $this->semuaKunci('/api/beranda', $kunci);
    }

    public function test_beranda_dosen_dan_admin(): void
    {
        $kelas = $this->buatKelas();
        $this->buatKelas(kodeKelas: 'IS-05-02');
        $this->tugaskan(Pengguna::factory()->asisten()->create(), $kelas);

        $this->masukSebagai($kelas->dosen);
        $this->semuaKunci('/api/beranda', ['kode_mk', 'praktikum', 'kelas', 'jumlah_asisten']);

        $this->masukSebagai(Pengguna::factory()->admin()->create());
        $this->getJson('/api/beranda?urut=jumlah_asisten&arah=desc')->assertJsonPath('baris.0.kelas', 'IS-05-01');
        $this->getJson('/api/beranda?urut=kelas&arah=desc')->assertJsonPath('baris.0.kelas', 'IS-05-02');
        $this->semuaKunci('/api/beranda', ['kode_mk', 'praktikum', 'kelas', 'prodi', 'dosen', 'jumlah_asisten']);
    }

    public function test_kelas_praktikum_dan_asisten_kelas(): void
    {
        $kelas = $this->buatKelas();
        $this->tugaskan(Pengguna::factory()->asisten()->create(['nama' => 'Zed']), $kelas);
        $this->tugaskan(Pengguna::factory()->asisten()->create(['nama' => 'Abi']), $kelas);
        $this->masukSebagai($kelas->dosen);

        $this->semuaKunci('/api/kelas-praktikum', ['kode_mk', 'mata_kuliah', 'kelas', 'prodi', 'jumlah_asisten']);
        $this->getJson("/api/kelas-praktikum/{$kelas->id_kelas}?urut=nama&arah=desc")->assertJsonPath('data.asisten.0.nama', 'Zed');
        $this->semuaKunci("/api/kelas-praktikum/{$kelas->id_kelas}", ['nim', 'nama']);
    }

    public function test_nomor_surat(): void
    {
        $this->asistenDenganKelas();
        $this->masukSebagai(Pengguna::factory()->admin()->create());
        $this->semuaKunci('/api/sertifikat/nomor-surat', ['nim', 'nama', 'praktikum']);
    }
}
