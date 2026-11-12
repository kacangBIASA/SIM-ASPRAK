<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use App\Models\Periode;
use App\Models\Presensi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** UC02 Mencatat presensi kegiatan — KF-03, KF-04. */
class PresensiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.upload_disk'));
    }

    private function isianValid(int $idKelas, array $ubah = []): array
    {
        return array_merge([
            'id_kelas' => $idKelas,
            'kegiatan' => 'Modul 2',
            'jam_masuk' => '08:00',
            'jam_keluar' => '10:00',
            'foto_kegiatan' => UploadedFile::fake()->image('kegiatan.jpg')->size(500),
        ], $ubah);
    }

    public function test_form_terisi_otomatis_tanggal_identitas_dan_kelas(): void
    {
        [$asisten, $kelas] = $this->asistenDenganKelas();
        $this->masukSebagai($asisten);

        $this->getJson('/api/presensi/form')
            ->assertOk()
            ->assertJsonPath('tanggal', now()->toDateString())
            ->assertJsonPath('asisten.nama', $asisten->nama)
            ->assertJsonPath('praktikum.0.id_kelas', $kelas->id_kelas)
            ->assertJsonPath('praktikum.0.kode_kelas', 'IS-05-01');
    }

    public function test_form_hanya_menampilkan_penugasan_periode_berjalan(): void
    {
        [$asisten] = $this->asistenDenganKelas();
        $lampau = Periode::create(['nama_periode' => 'Genap 2020/2021', 'tanggal_mulai' => '2021-02-01', 'tanggal_selesai' => '2021-07-31']);
        $this->tugaskan($asisten, $this->buatKelas($lampau));
        $this->masukSebagai($asisten);

        $this->getJson('/api/presensi/form')->assertOk()->assertJsonCount(1, 'praktikum');
    }

    public function test_presensi_berhasil_disimpan_beserta_foto(): void
    {
        [$asisten, $kelas] = $this->asistenDenganKelas();
        $this->masukSebagai($asisten);

        $this->postJson('/api/presensi', $this->isianValid($kelas->id_kelas))
            ->assertCreated()
            ->assertJsonPath('message', 'Presensi berhasil disimpan.');

        $presensi = Presensi::firstOrFail();
        $this->assertSame(now()->toDateString(), $presensi->tanggal->toDateString());
        $this->assertEquals($asisten->id_pengguna, $presensi->id_asisten);
        $this->assertEquals($kelas->id_kelas, $presensi->id_kelas);
        Storage::disk(config('filesystems.upload_disk'))->assertExists($presensi->foto_kegiatan);
    }

    public function test_alternate_4a_isian_belum_lengkap(): void
    {
        [$asisten] = $this->asistenDenganKelas();
        $this->masukSebagai($asisten);

        $this->postJson('/api/presensi', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id_kelas', 'kegiatan', 'jam_masuk', 'jam_keluar', 'foto_kegiatan']);
        $this->assertDatabaseCount('presensi', 0);
    }

    public function test_alternate_4a_jam_keluar_lebih_awal_dari_jam_masuk(): void
    {
        [$asisten, $kelas] = $this->asistenDenganKelas();
        $this->masukSebagai($asisten);

        $this->postJson('/api/presensi', $this->isianValid($kelas->id_kelas, ['jam_masuk' => '10:00', 'jam_keluar' => '09:00']))
            ->assertStatus(422)
            ->assertJsonPath('errors.jam_keluar.0', 'Jam keluar harus lebih akhir dari jam masuk.');
    }

    public function test_alternate_4b_format_foto_tidak_sesuai(): void
    {
        [$asisten, $kelas] = $this->asistenDenganKelas();
        $this->masukSebagai($asisten);

        $this->postJson('/api/presensi', $this->isianValid($kelas->id_kelas, [
            'foto_kegiatan' => UploadedFile::fake()->create('kegiatan.pdf', 100, 'application/pdf'),
        ]))->assertStatus(422)->assertJsonPath('errors.foto_kegiatan.0', 'Format foto harus JPG atau PNG.');
    }

    public function test_alternate_4b_ukuran_foto_melebihi_batas(): void
    {
        [$asisten, $kelas] = $this->asistenDenganKelas();
        $this->masukSebagai($asisten);

        $this->postJson('/api/presensi', $this->isianValid($kelas->id_kelas, [
            'foto_kegiatan' => UploadedFile::fake()->image('besar.jpg')->size(3000),
        ]))->assertStatus(422)->assertJsonPath('errors.foto_kegiatan.0', 'Ukuran foto maksimal 2 MB.');
    }

    public function test_waktu_presensi_bertumpuk_ditolak(): void
    {
        [$asisten, $kelas] = $this->asistenDenganKelas();
        $kelasLain = $this->buatKelas(kodeMk: 'SI3105', kodeKelas: 'IS-05-02');
        $this->tugaskan($asisten, $kelasLain);
        $this->masukSebagai($asisten);

        $this->postJson('/api/presensi', $this->isianValid($kelas->id_kelas))->assertCreated(); // 08:00–10:00
        $this->postJson('/api/presensi', $this->isianValid($kelasLain->id_kelas, ['jam_masuk' => '09:00', 'jam_keluar' => '11:00']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('jam_masuk');

        // Bersambung tepat di jam keluar tetap boleh.
        $this->postJson('/api/presensi', $this->isianValid($kelasLain->id_kelas, ['jam_masuk' => '10:00', 'jam_keluar' => '12:00']))
            ->assertCreated();
    }

    public function test_kelas_yang_sama_hanya_sekali_presensi_per_hari(): void
    {
        [$asisten, $kelas] = $this->asistenDenganKelas();
        $this->masukSebagai($asisten);

        $this->postJson('/api/presensi', $this->isianValid($kelas->id_kelas))->assertCreated(); // 08:00–10:00
        $this->postJson('/api/presensi', $this->isianValid($kelas->id_kelas, ['jam_masuk' => '13:00', 'jam_keluar' => '15:00']))
            ->assertStatus(422)
            ->assertJsonPath('errors.id_kelas.0', 'Anda sudah mencatat presensi untuk praktikum ini hari ini.');
        $this->assertDatabaseCount('presensi', 1);
    }

    public function test_tidak_dapat_presensi_pada_kelas_yang_bukan_tugasnya(): void
    {
        [$asisten] = $this->asistenDenganKelas();
        $lain = $this->buatKelas(kodeKelas: 'IS-05-09');
        $this->masukSebagai($asisten);

        $this->postJson('/api/presensi', $this->isianValid($lain->id_kelas))
            ->assertStatus(422)->assertJsonValidationErrors('id_kelas');
    }

    public function test_selain_asisten_tidak_dapat_mengakses_presensi(): void
    {
        $this->masukSebagai(Pengguna::factory()->dosen()->create());

        $this->getJson('/api/presensi/form')->assertStatus(403);
    }
}
