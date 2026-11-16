<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use App\Models\Periode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** UC10 Mengisi nomor surat sertifikat — KF-17. */
class NomorSuratTest extends TestCase
{
    use RefreshDatabase;

    public function test_isian_periode_berjalan_dengan_nomor_surat_kosong(): void
    {
        $this->asistenDenganKelas();
        $this->masukSebagai(Pengguna::factory()->admin()->create());

        $this->getJson('/api/sertifikat/nomor-surat')
            ->assertOk()
            ->assertJsonPath('periode', Periode::berjalan()->nama_periode)
            ->assertJsonPath('nomor_surat', null)
            ->assertJsonCount(1, 'asisten');
    }

    public function test_nomor_surat_disimpan_lalu_daftar_asisten_muncul(): void
    {
        [$asisten] = $this->asistenDenganKelas();
        $this->masukSebagai(Pengguna::factory()->admin()->create());

        $this->postJson('/api/sertifikat/nomor-surat', ['nomor_surat' => ' 123/LAB/2026 '])
            ->assertOk()
            ->assertJsonPath('message', 'Nomor surat berhasil disimpan.')
            ->assertJsonPath('nomor_surat', '123/LAB/2026')
            ->assertJsonPath('asisten.0.nama', $asisten->nama);

        $this->postJson('/api/sertifikat/nomor-surat', ['nomor_surat' => '124/LAB/2026'])->assertOk();
        $this->assertSame('124/LAB/2026', Periode::berjalan()->nomor_surat);
    }

    public function test_laboran_dapat_memilih_periode_sebelumnya(): void
    {
        $this->travelTo('2026-09-01');
        $lama = Periode::berjalan();
        $this->travelTo('2027-03-01');
        $this->masukSebagai(Pengguna::factory()->admin()->create());

        $this->getJson('/api/sertifikat/nomor-surat')->assertJsonPath('periode_opsi.0.nama', "{$lama->nama_periode} (sebelumnya)");
        $this->postJson('/api/sertifikat/nomor-surat', ['nomor_surat' => '7/LAB', 'periode' => $lama->id_periode])
            ->assertOk()->assertJsonPath('periode', $lama->nama_periode);
        $this->assertSame('7/LAB', $lama->fresh()->nomor_surat);
        $this->assertNull(Periode::berjalan()->nomor_surat);
        $this->postJson('/api/sertifikat/nomor-surat', ['nomor_surat' => '8/LAB', 'periode' => 9999])->assertStatus(422);
    }

    public function test_alternate_6a_nomor_surat_kosong(): void
    {
        $this->masukSebagai(Pengguna::factory()->admin()->create());

        $this->postJson('/api/sertifikat/nomor-surat', ['nomor_surat' => ''])
            ->assertStatus(422)->assertJsonPath('errors.nomor_surat.0', 'Nomor surat wajib diisi.');
    }

    public function test_alternate_6a_nomor_surat_sudah_digunakan(): void
    {
        Periode::create(['nama_periode' => 'Genap 2020/2021', 'tanggal_mulai' => '2021-02-01', 'tanggal_selesai' => '2021-07-31', 'nomor_surat' => '001/LAB']);
        $this->masukSebagai(Pengguna::factory()->admin()->create());

        $this->postJson('/api/sertifikat/nomor-surat', ['nomor_surat' => '001/LAB'])
            ->assertStatus(422)->assertJsonPath('errors.nomor_surat.0', 'Nomor surat sudah digunakan pada periode lain.');
    }

    public function test_selain_laboran_ditolak(): void
    {
        $this->masukSebagai(Pengguna::factory()->dosen()->create());

        $this->getJson('/api/sertifikat/nomor-surat')->assertStatus(403);
    }
}
