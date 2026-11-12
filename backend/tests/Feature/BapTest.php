<?php

namespace Tests\Feature;

use App\Models\Bap;
use App\Models\Pengguna;
use App\Models\Periode;
use App\Models\Presensi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** UC03 */
class BapTest extends TestCase
{
    use RefreshDatabase;

    private function catat($asisten, $kelas, string $tanggal, string $masuk = '13:30:00', string $keluar = '16:30:00'): Presensi
    {
        return Presensi::create([
            'id_asisten' => $asisten->id_pengguna, 'id_kelas' => $kelas->id_kelas, 'tanggal' => $tanggal,
            'kegiatan' => 'Responsi Tugas Besar', 'jam_masuk' => $masuk, 'jam_keluar' => $keluar, 'foto_kegiatan' => 'x.jpg',
        ]);
    }

    private function periodeBap(string $nama): int
    {
        return Periode::berjalan()->periodeBap->firstWhere('nama_periode_bap', $nama)->id_periode_bap;
    }

    public function test_pilihan_memuat_praktikum_dan_periode_bap(): void
    {
        $this->travelTo('2026-12-10');
        [$asisten, $kelas] = $this->asistenDenganKelas();
        $this->masukSebagai($asisten);

        $this->getJson('/api/bap/pilihan')->assertOk()
            ->assertJsonPath('praktikum.0.id_kelas', $kelas->id_kelas)
            ->assertJsonCount(6, 'periode_bap')
            ->assertJsonPath('periode_bap.0.nama_periode_bap', 'BAP Agustus 2026');
    }

    public function test_bap_disusun_dari_presensi_dengan_dosen_pengampu(): void
    {
        $this->travelTo('2027-01-08');
        [$asisten, $kelas] = $this->asistenDenganKelas();
        $this->catat($asisten, $kelas, '2026-12-17');
        $this->catat($asisten, $kelas, '2026-12-24');
        $this->catat($asisten, $kelas, '2026-12-25');
        $this->catat($asisten, $kelas, '2026-12-26');
        $this->masukSebagai($asisten);
        $isian = ['id_kelas' => $kelas->id_kelas, 'id_periode_bap' => $this->periodeBap('BAP Desember 2026')];

        $r = $this->postJson('/api/bap', $isian)->assertOk();

        $r->assertJsonPath('bulan', 'Desember')->assertJsonPath('tahun', 2026)
            ->assertJsonPath('total_jam', '9 Jam')
            ->assertJsonPath('dosen', $kelas->dosen->nama)
            ->assertJsonPath('baris.0.hari', 'Kamis')->assertJsonPath('baris.0.tanggal', '17 Desember 2026')
            ->assertJsonCount(3, 'baris');
        $this->assertDatabaseHas('bap', ['id_bap' => $r['id_bap'], 'id_asisten' => $asisten->id_pengguna]);
        $this->assertSame(3, Presensi::where('id_bap', $r['id_bap'])->count());

       
        $this->postJson('/api/bap', $isian)->assertOk();
        $this->assertDatabaseCount('bap', 1);
    }

    public function test_alternate_4a_tanpa_presensi(): void
    {
        [$asisten, $kelas] = $this->asistenDenganKelas();
        $this->masukSebagai($asisten);

        $this->postJson('/api/bap', ['id_kelas' => $kelas->id_kelas, 'id_periode_bap' => Periode::berjalan()->periodeBap->first()->id_periode_bap])
            ->assertStatus(422)
            ->assertJsonPath('errors.id_periode_bap.0', 'Data presensi belum tersedia pada praktikum dan periode BAP yang dipilih.');
        $this->assertDatabaseCount('bap', 0);
    }

    public function test_kelas_bukan_penugasan_ditolak(): void
    {
        [$asisten] = $this->asistenDenganKelas();
        $lain = $this->buatKelas(kodeMk: 'SI9999');
        $this->masukSebagai($asisten);

        $this->postJson('/api/bap', ['id_kelas' => $lain->id_kelas, 'id_periode_bap' => Periode::berjalan()->periodeBap->first()->id_periode_bap])
            ->assertStatus(422)->assertJsonValidationErrors('id_kelas');
    }

    public function test_unduh_menghasilkan_pdf_lewat_tautan_bertanda_tangan(): void
    {
        $this->travelTo('2027-01-08');
        [$asisten, $kelas] = $this->asistenDenganKelas();
        $this->catat($asisten, $kelas, '2026-12-17');
        $this->masukSebagai($asisten);
        $id = $this->postJson('/api/bap', ['id_kelas' => $kelas->id_kelas, 'id_periode_bap' => $this->periodeBap('BAP Desember 2026')])['id_bap'];

        $url = $this->getJson("/api/bap/$id/unduh")->assertOk()->json('url');
        $res = $this->get($url)->assertOk();
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->get("/api/bap/$id/berkas")->assertForbidden();
    }

    public function test_bap_asisten_lain_tidak_dapat_diunduh(): void
    {
        $this->travelTo('2027-01-08');
        [$asisten, $kelas] = $this->asistenDenganKelas();
        $bap = Bap::create(['id_asisten' => $asisten->id_pengguna, 'id_kelas' => $kelas->id_kelas, 'id_periode_bap' => $this->periodeBap('BAP Desember 2026'), 'tanggal_dibuat' => '2027-01-08']);
        $lain = Pengguna::factory()->asisten()->create();
        $this->masukSebagai($lain);

        $this->getJson("/api/bap/{$bap->id_bap}/unduh")->assertNotFound();
    }
}
