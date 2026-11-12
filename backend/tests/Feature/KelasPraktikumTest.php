<?php

namespace Tests\Feature;

use App\Models\KelasPraktikum;
use App\Models\Pengguna;
use App\Models\Periode;
use App\Models\Praktikum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** UC05 Mendaftarkan kelas praktikum (KF-09, KF-10) dan UC06 Menetapkan asisten (KF-11). */
class KelasPraktikumTest extends TestCase
{
    use RefreshDatabase;

    private function isian(array $ubah = []): array
    {
        return array_merge([
            'kode_praktikum' => 'si3104', 'nama_praktikum' => 'Praktikum Data Warehouse',
            'prodi' => 'Sistem Informasi', 'kode_kelas' => 'is-05-01',
        ], $ubah);
    }

    public function test_periode_dan_periode_bap_dibentuk_otomatis(): void
    {
        $this->travelTo('2026-11-09');
        $periode = Periode::berjalan();

        $this->assertSame('Ganjil 2026/2027', $periode->nama_periode);
        $this->assertSame('2026-08-01', $periode->tanggal_mulai->toDateString());
        $this->assertSame('2027-01-31', $periode->tanggal_selesai->toDateString());
        $bap = $periode->periodeBap;
        $this->assertSame(['BAP Agustus 2026', '2026-08-01', '2026-08-25'], [$bap[0]->nama_periode_bap, $bap[0]->tanggal_mulai->toDateString(), $bap[0]->tanggal_selesai->toDateString()]);
        $this->assertSame(['BAP Januari 2027', '2026-12-26', '2027-01-31'], [$bap->last()->nama_periode_bap, $bap->last()->tanggal_mulai->toDateString(), $bap->last()->tanggal_selesai->toDateString()]);
        $this->assertCount(6, $bap);

        $this->assertTrue(Periode::berjalan()->is($periode)); // tidak dibuat ganda
        $this->travelTo('2027-03-01');
        $this->assertSame('Genap 2026/2027', Periode::berjalan()->nama_periode);
    }

    public function test_daftar_kelas_hanya_milik_dosen_pada_periode_berjalan(): void
    {
        $milik = $this->buatKelas();
        $this->buatKelas(kodeKelas: 'IS-05-02'); // dosen lain
        $lampau = Periode::create(['nama_periode' => 'Genap 2020/2021', 'tanggal_mulai' => '2021-02-01', 'tanggal_selesai' => '2021-07-31']);
        Praktikum::create(['id_periode' => $lampau->id_periode, 'kode_praktikum' => 'X1', 'nama_praktikum' => 'Lama', 'prodi' => 'Informatika'])
            ->kelas()->create(['id_dosen' => $milik->id_dosen, 'kode_kelas' => 'A']);
        $this->tugaskan(Pengguna::factory()->asisten()->create(), $milik);
        $this->masukSebagai($milik->dosen);

        $this->getJson('/api/kelas-praktikum')
            ->assertOk()
            ->assertJsonPath('periode', Periode::berjalan()->nama_periode)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_kelas', $milik->id_kelas)
            ->assertJsonPath('data.0.jumlah_asisten', 1);
    }

    public function test_kelas_berhasil_didaftarkan_dengan_dosen_sebagai_pengampu(): void
    {
        $dosen = $this->masukSebagai(Pengguna::factory()->dosen()->create());

        $this->postJson('/api/kelas-praktikum', $this->isian())
            ->assertCreated()
            ->assertJsonPath('message', 'Kelas berhasil didaftarkan.')
            ->assertJsonPath('data.kode_praktikum', 'SI3104')
            ->assertJsonPath('data.kode_kelas', 'IS-05-01');

        $kelas = KelasPraktikum::sole();
        $this->assertEquals($dosen->id_pengguna, $kelas->id_dosen);
        $this->assertEquals(Periode::berjalan()->id_periode, $kelas->praktikum->id_periode);
    }

    public function test_kode_mk_sama_digabung_ke_praktikum_yang_sama(): void
    {
        $this->buatKelas(kodeMk: 'SI3104', kodeKelas: 'IS-05-01');
        $this->masukSebagai(Pengguna::factory()->dosen()->create());

        $this->postJson('/api/kelas-praktikum', $this->isian(['kode_kelas' => 'IS-05-02']))
            ->assertCreated()
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'digabungkan'));

        $this->assertDatabaseCount('praktikum', 1);
        $this->assertDatabaseCount('kelas_praktikum', 2);
    }

    public function test_alternate_6a_isian_belum_lengkap(): void
    {
        $this->masukSebagai(Pengguna::factory()->dosen()->create());

        $this->postJson('/api/kelas-praktikum', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['kode_praktikum', 'nama_praktikum', 'prodi', 'kode_kelas']);
        $this->assertDatabaseCount('praktikum', 0);
    }

    public function test_alternate_6b_kode_kelas_sudah_terdaftar(): void
    {
        $this->buatKelas(kodeMk: 'SI3104', kodeKelas: 'IS-05-01');
        $this->masukSebagai(Pengguna::factory()->dosen()->create());

        $this->postJson('/api/kelas-praktikum', $this->isian())
            ->assertStatus(422)
            ->assertJsonPath('errors.kode_kelas.0', 'Kelas IS-05-01 sudah terdaftar pada mata kuliah SI3104.');
        $this->assertDatabaseCount('kelas_praktikum', 1);
    }

    public function test_selain_dosen_ditolak(): void
    {
        $this->masukSebagai(Pengguna::factory()->asisten()->create());

        $this->getJson('/api/kelas-praktikum')->assertStatus(403);
    }

    // UC06

    public function test_dosen_mencari_dan_menetapkan_asisten(): void
    {
        $kelas = $this->buatKelas();
        $asisten = Pengguna::factory()->asisten()->create(['nama' => 'Budi Santoso', 'nomor_induk' => '1204230099']);
        $this->masukSebagai($kelas->dosen);

        $this->getJson("/api/kelas-praktikum/{$kelas->id_kelas}/asisten/cari?q=santoso")->assertOk()->assertJsonPath('data.0.id_pengguna', $asisten->id_pengguna);
        $this->getJson("/api/kelas-praktikum/{$kelas->id_kelas}/asisten/cari?q=1204230099")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/kelas-praktikum/{$kelas->id_kelas}/asisten/cari?q=tidakada")->assertOk()->assertJsonCount(0, 'data');

        $this->postJson("/api/kelas-praktikum/{$kelas->id_kelas}/asisten", ['id_asisten' => $asisten->id_pengguna])->assertCreated();
        $this->getJson("/api/kelas-praktikum/{$kelas->id_kelas}/asisten")->assertOk()->assertJsonPath('data.0.nama', 'Budi Santoso');
    }

    public function test_alternate_8a_asisten_sudah_di_kelas_lain_pada_praktikum_sama(): void
    {
        $kelasA = $this->buatKelas(kodeKelas: 'IS-05-01');
        $kelasB = $this->buatKelas(kodeMk: 'SI3104', kodeKelas: 'IS-05-02');
        $asisten = Pengguna::factory()->asisten()->create();
        $this->tugaskan($asisten, $kelasA);
        $this->masukSebagai($kelasB->dosen);

        $this->postJson("/api/kelas-praktikum/{$kelasB->id_kelas}/asisten", ['id_asisten' => $asisten->id_pengguna])
            ->assertStatus(422)
            ->assertJsonPath('errors.id_asisten.0', 'Asisten sudah ditugaskan pada kelas lain dalam mata kuliah praktikum yang sama.');
        $this->assertDatabaseCount('penugasan_asisten', 1);
    }

    public function test_dosen_tidak_dapat_mengelola_kelas_dosen_lain(): void
    {
        $kelas = $this->buatKelas();
        $this->masukSebagai(Pengguna::factory()->dosen()->create());

        $this->getJson("/api/kelas-praktikum/{$kelas->id_kelas}/asisten")->assertNotFound();
        $this->postJson("/api/kelas-praktikum/{$kelas->id_kelas}/asisten", ['id_asisten' => Pengguna::factory()->asisten()->create()->id_pengguna])->assertNotFound();
    }

    public function test_hanya_akun_asisten_yang_dapat_ditetapkan(): void
    {
        $kelas = $this->buatKelas();
        $this->masukSebagai($kelas->dosen);

        $this->postJson("/api/kelas-praktikum/{$kelas->id_kelas}/asisten", ['id_asisten' => $kelas->id_dosen])->assertStatus(422);
        $this->postJson("/api/kelas-praktikum/{$kelas->id_kelas}/asisten", [])->assertStatus(422);
    }
}
