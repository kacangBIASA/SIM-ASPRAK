<?php

namespace Tests\Feature;

use App\Models\KelasPraktikum;
use App\Models\Pengguna;
use App\Models\Periode;
use App\Models\PeriodeBap;
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
        $this->assertSame('2026-09-14', $periode->tanggal_mulai->toDateString());
        $this->assertSame('2027-01-23', $periode->tanggal_selesai->toDateString());
        $bap = $periode->periodeBap;
        $this->assertSame(['BAP September 2026', '2026-09-14', '2026-09-25'], [$bap[0]->nama_periode_bap, $bap[0]->tanggal_mulai->toDateString(), $bap[0]->tanggal_selesai->toDateString()]);
        $this->assertSame(['BAP Januari 2027', '2026-12-26', '2027-01-23'], [$bap->last()->nama_periode_bap, $bap->last()->tanggal_mulai->toDateString(), $bap->last()->tanggal_selesai->toDateString()]);
        $this->assertCount(5, $bap);

        $this->assertTrue(Periode::berjalan()->is($periode));
        $this->travelTo('2027-03-01');
        $this->assertSame('Genap 2026/2027', Periode::berjalan()->nama_periode);
    }

    public function test_dosen_menyiapkan_kelas_untuk_periode_berikutnya_sebelum_kuliah_dimulai(): void
    {
        $this->travelTo('2026-08-20');
        $this->masukSebagai(Pengguna::factory()->dosen()->create());
        $berikut = $this->getJson('/api/kelas-praktikum')->assertJsonPath('periode', 'Genap 2025/2026')
            ->assertJsonPath('periode_opsi.1.nama', 'Ganjil 2026/2027 (berikutnya)')->json('periode_opsi.1.id');

        $this->postJson('/api/kelas-praktikum', $this->isian(['periode' => $berikut]))->assertCreated();
        $this->assertSame('Ganjil 2026/2027', Praktikum::firstOrFail()->periode->nama_periode);
        $this->getJson("/api/kelas-praktikum?periode=$berikut")->assertJsonCount(1, 'data');
        $this->getJson('/api/kelas-praktikum')->assertJsonCount(0, 'data');

        $this->postJson('/api/kelas-praktikum', $this->isian(['periode' => 9999]))->assertStatus(422);

        $this->travelTo('2026-09-14');
        $this->assertSame($berikut, Periode::berjalan()->id_periode);
    }

    public function test_pergantian_semester_mengikuti_kalender_pendidikan(): void
    {
        $kasus = [
            ['2023-09-17', 'Genap 2022/2023'], ['2023-09-18', 'Ganjil 2023/2024', '2023-09-18'],
            ['2024-02-18', 'Ganjil 2023/2024'], ['2024-02-19', 'Genap 2023/2024', '2024-02-19'],
            ['2024-09-16', 'Ganjil 2024/2025', '2024-09-16'],
            ['2025-02-16', 'Ganjil 2024/2025'], ['2025-02-17', 'Genap 2024/2025', '2025-02-17'],
            ['2025-09-14', 'Genap 2024/2025'], ['2025-09-15', 'Ganjil 2025/2026', '2025-09-15', '2026-01-24'],
            ['2026-02-22', 'Ganjil 2025/2026'],
            ['2026-02-23', 'Genap 2025/2026', '2026-02-23', '2026-07-24'],
            ['2026-09-13', 'Genap 2025/2026'], ['2026-09-14', 'Ganjil 2026/2027', '2026-09-14', '2027-01-23'],
            ['2027-02-21', 'Ganjil 2026/2027'], ['2027-02-22', 'Genap 2026/2027', '2027-02-22'],
        ];
        foreach ($kasus as $k) {
            [$hari, $nama, $mulai, $selesai] = array_pad($k, 4, null);
            $this->travelTo($hari);
            $periode = Periode::berjalan();
            $this->assertSame($nama, $periode->nama_periode, $hari);
            if ($mulai) {
                $this->assertSame($mulai, $periode->tanggal_mulai->toDateString(), $hari);
                $this->assertTrue($periode->tanggal_mulai->isMonday(), $hari);
            }
            if ($selesai) {
                $this->assertSame($selesai, $periode->tanggal_selesai->toDateString(), $hari);
            }
        }
    }

    public function test_periode_lama_diselaraskan_tanpa_menghapus_periode_bap_yang_dipakai(): void
    {
        $this->travelTo('2026-11-09');
        $lama = Periode::create(['nama_periode' => 'Ganjil 2026/2027', 'tanggal_mulai' => '2026-08-01', 'tanggal_selesai' => '2027-01-31']);
        $agustus = $lama->periodeBap()->create(['nama_periode_bap' => 'BAP Agustus 2026', 'tanggal_mulai' => '2026-08-01', 'tanggal_selesai' => '2026-08-25']);
        $september = $lama->periodeBap()->create(['nama_periode_bap' => 'BAP September 2026', 'tanggal_mulai' => '2026-08-26', 'tanggal_selesai' => '2026-09-25']);

        $periode = Periode::berjalan();

        $this->assertTrue($periode->is($lama));
        $this->assertSame('2026-09-14', $periode->tanggal_mulai->toDateString());
        $this->assertNull(PeriodeBap::find($agustus->id_periode_bap));
        $this->assertSame('2026-09-14', PeriodeBap::find($september->id_periode_bap)->tanggal_mulai->toDateString());
        $this->assertCount(5, $periode->periodeBap);
    }

    public function test_daftar_kelas_hanya_milik_dosen_pada_periode_berjalan(): void
    {
        $milik = $this->buatKelas();
        $this->buatKelas(kodeKelas: 'IS-05-02');
        $lampau = Periode::create(['nama_periode' => 'Genap 2020/2021', 'tanggal_mulai' => '2021-02-01', 'tanggal_selesai' => '2021-07-31']);
        Praktikum::create(['id_periode' => $lampau->id_periode, 'kode_praktikum' => 'X1', 'nama_praktikum' => 'Lama'])
            ->kelas()->create(['id_dosen' => $milik->id_dosen, 'kode_kelas' => 'A', 'prodi' => 'Informatika']);
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

    public function test_cari_asisten_berdasarkan_nim_atau_nama(): void
    {
        Pengguna::factory()->asisten()->create(['nama' => 'Budi Santoso', 'nomor_induk' => '1204230111']);
        Pengguna::factory()->dosen()->create(['nama' => 'Budi Dosen']);
        $this->masukSebagai(Pengguna::factory()->dosen()->create());

        $this->getJson('/api/asisten?cari=budi')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nama', 'Budi Santoso');
        $this->getJson('/api/asisten?cari=0111')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/asisten?cari=tidakada')->assertStatus(404)->assertJsonPath('message', 'Asisten tidak ditemukan.');
    }

    public function test_detail_dan_penetapan_asisten(): void
    {
        $kelas = $this->buatKelas();
        $asisten = Pengguna::factory()->asisten()->create();
        $this->masukSebagai($kelas->dosen);

        $this->postJson("/api/kelas-praktikum/{$kelas->id_kelas}/asisten", ['id_asisten' => $asisten->id_pengguna])
            ->assertCreated()
            ->assertJsonPath('message', "{$asisten->nama} berhasil ditetapkan sebagai asisten kelas IS-05-01.");

        $this->getJson("/api/kelas-praktikum/{$kelas->id_kelas}")
            ->assertOk()
            ->assertJsonCount(1, 'data.asisten')
            ->assertJsonPath('data.asisten.0.nama', $asisten->nama);
    }

    public function test_alternate_8a_asisten_sudah_di_kelas_lain_pada_praktikum_sama(): void
    {
        $kelas1 = $this->buatKelas(kodeKelas: 'IS-05-01');
        $kelas2 = $this->buatKelas(kodeKelas: 'IS-05-02');
        $asisten = Pengguna::factory()->asisten()->create();
        $this->tugaskan($asisten, $kelas1);
        $this->masukSebagai($kelas2->dosen);

        $this->postJson("/api/kelas-praktikum/{$kelas2->id_kelas}/asisten", ['id_asisten' => $asisten->id_pengguna])
            ->assertStatus(422)
            ->assertJsonPath('errors.id_asisten.0', "{$asisten->nama} sudah ditugaskan pada kelas lain (IS-05-01) dalam mata kuliah ini.");

        $kelasLain = $this->buatKelas(kodeMk: 'SI3107');
        $this->masukSebagai($kelasLain->dosen);
        $this->postJson("/api/kelas-praktikum/{$kelasLain->id_kelas}/asisten", ['id_asisten' => $asisten->id_pengguna])->assertCreated();
    }

    public function test_mahasiswa_ditetapkan_dosen_otomatis_menjadi_asisten(): void
    {
        $kelas = $this->buatKelas();
        $mhs = Pengguna::factory()->mahasiswa()->create(['nama' => 'Calon Asisten']);
        $this->masukSebagai($mhs);
        $this->getJson('/api/presensi/riwayat')->assertStatus(403);

        $this->masukSebagai($kelas->dosen);
        $this->getJson('/api/asisten?cari=calon')->assertOk()->assertJsonPath('data.0.nama', 'Calon Asisten');
        $this->postJson("/api/kelas-praktikum/{$kelas->id_kelas}/asisten", ['id_asisten' => $mhs->id_pengguna])->assertCreated();

        $this->assertSame(Pengguna::PERAN_ASISTEN, $mhs->fresh()->peran);
        $this->masukSebagai($mhs->fresh());
        $this->getJson('/api/presensi/riwayat')->assertOk();
    }

    public function test_peran_asisten_berakhir_bersama_semesternya(): void
    {
        [$asisten] = $this->asistenDenganKelas();
        $this->masukSebagai($asisten);
        $this->getJson('/api/presensi/riwayat')->assertOk();

        $this->travelTo(now()->addYear());
        $this->getJson('/api/presensi/riwayat')->assertStatus(403);
        $this->assertSame(Pengguna::PERAN_MAHASISWA, $asisten->fresh()->peran);
    }

    public function test_satu_mata_kuliah_dipakai_beberapa_prodi(): void
    {
        $si = $this->buatKelas();
        $dosen = Pengguna::factory()->dosen()->create();
        $this->masukSebagai($dosen);

        $this->postJson('/api/kelas-praktikum', ['kode_praktikum' => 'SI3104', 'nama_praktikum' => 'Praktikum SI3104', 'prodi' => 'Informatika', 'kode_kelas' => 'IF-05-01'])
            ->assertCreated()->assertJsonPath('data.prodi', 'Informatika');

        $this->assertSame(1, Praktikum::where('kode_praktikum', 'SI3104')->count());
        $this->assertEqualsCanonicalizing(['Sistem Informasi', 'Informatika'], $si->praktikum->kelas()->pluck('prodi')->all());
    }

    public function test_hanya_asisten_yang_dapat_ditetapkan(): void
    {
        $kelas = $this->buatKelas();
        $this->masukSebagai($kelas->dosen);

        $this->postJson("/api/kelas-praktikum/{$kelas->id_kelas}/asisten", ['id_asisten' => Pengguna::factory()->dosen()->create()->id_pengguna])
            ->assertStatus(422)->assertJsonValidationErrors('id_asisten');
    }

    public function test_kelas_dosen_lain_tidak_dapat_dikelola(): void
    {
        $kelas = $this->buatKelas();
        $this->masukSebagai(Pengguna::factory()->dosen()->create());

        $this->getJson("/api/kelas-praktikum/{$kelas->id_kelas}")->assertStatus(403);
        $this->postJson("/api/kelas-praktikum/{$kelas->id_kelas}/asisten", ['id_asisten' => Pengguna::factory()->asisten()->create()->id_pengguna])
            ->assertStatus(403);
    }

    public function test_selain_dosen_ditolak(): void
    {
        $this->masukSebagai(Pengguna::factory()->asisten()->create());

        $this->getJson('/api/kelas-praktikum')->assertStatus(403);
    }
}
