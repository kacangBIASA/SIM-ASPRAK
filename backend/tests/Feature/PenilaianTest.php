<?php

namespace Tests\Feature;

use App\Models\KomponenPenilaian;
use App\Models\Pengguna;
use App\Models\Presensi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** UC05 langkah 1–4 (iterasi 2): praktikum yang diampu dan daftar asistennya — KF-12. */
class PenilaianTest extends TestCase
{
    use RefreshDatabase;

    public function test_dosen_melihat_kelas_yang_diampu_dan_daftar_asisten_kelas_terpilih(): void
    {
        $dosen = Pengguna::factory()->dosen()->create();
        $k1 = $this->buatKelas();
        $k1->update(['id_dosen' => $dosen->id_pengguna]);
        $k2 = $this->buatKelas(kodeMk: 'SI3107');
        $k2->update(['id_dosen' => $dosen->id_pengguna]);
        $this->buatKelas(kodeMk: 'SI9999'); // kelas dosen lain tidak tampil
        $a = Pengguna::factory()->asisten()->create(['nama' => 'Aulia']);
        $b = Pengguna::factory()->asisten()->create(['nama' => 'Budi']);
        $this->tugaskan($a, $k1);
        $this->tugaskan($b, $k2);
        $this->masukSebagai($dosen);

        $this->getJson('/api/penilaian')->assertOk()
            ->assertJsonCount(2, 'kelas')
            ->assertJsonPath('id_kelas', $k1->id_kelas)
            ->assertJsonPath('asisten.0.nama', 'Aulia')
            ->assertJsonPath('asisten.0.status', 'belum')
            ->assertJsonPath('kelas.0.jumlah_asisten', 1);

        $this->getJson('/api/penilaian?kelas='.$k2->id_kelas)->assertJsonPath('asisten.0.nama', 'Budi');
        $this->getJson('/api/penilaian?q=zzz')->assertJsonCount(0, 'asisten');
    }

    public function test_kelas_milik_dosen_lain_ditolak(): void
    {
        $this->masukSebagai(Pengguna::factory()->dosen()->create());
        $lain = $this->buatKelas();

        $this->getJson('/api/penilaian?kelas='.$lain->id_kelas)->assertForbidden();
    }

    public function test_dosen_tanpa_kelas_mendapat_daftar_kosong(): void
    {
        $this->masukSebagai(Pengguna::factory()->dosen()->create());
        $this->getJson('/api/penilaian')->assertOk()->assertJsonPath('kelas', [])->assertJsonPath('id_kelas', null);
    }

    public function test_hanya_dosen(): void
    {
        $this->masukSebagai(Pengguna::factory()->admin()->create());
        $this->getJson('/api/penilaian')->assertForbidden();
    }

    /** @return array{0: Pengguna, 1: \App\Models\KelasPraktikum, 2: Pengguna} dosen, kelas, asisten */
    private function siapForm(): array
    {
        $dosen = Pengguna::factory()->dosen()->create();
        $kelas = $this->buatKelas();
        $kelas->update(['id_dosen' => $dosen->id_pengguna]);
        $asisten = Pengguna::factory()->asisten()->create();
        $this->tugaskan($asisten, $kelas);
        $this->masukSebagai($dosen);

        return [$dosen, $kelas, $asisten];
    }

    private function nilai(?int $isi = null, int $banyak = 11): array
    {
        return KomponenPenilaian::orderBy('urut')->pluck('id_komponen')->mapWithKeys(fn ($id, $i) => [$id => $i < $banyak ? $isi : null])->all();
    }

    public function test_form_menampilkan_tiga_kategori_dan_belum_ada_nilai(): void
    {
        [, $kelas, $asisten] = $this->siapForm();

        $this->getJson("/api/penilaian/{$kelas->id_kelas}/{$asisten->id_pengguna}")->assertOk()
            ->assertJsonPath('status', 'belum')
            ->assertJsonCount(3, 'kategori')
            ->assertJsonCount(3, 'kategori.0.komponen')->assertJsonCount(4, 'kategori.1.komponen')->assertJsonCount(4, 'kategori.2.komponen')
            ->assertJsonPath('kategori.0.komponen.0.nama', 'Kualitas')->assertJsonPath('kategori.0.komponen.1.nama', 'Ketepatan Waktu dan Efisiensi')
            ->assertJsonPath('rataan_akhir', null);
    }

    public function test_simpan_draft_lalu_dibuka_kembali_dengan_isian_sebelumnya(): void
    {
        [, $kelas, $asisten] = $this->siapForm();
        $url = "/api/penilaian/{$kelas->id_kelas}/{$asisten->id_pengguna}";

        $this->postJson("$url/draft", ['nilai' => $this->nilai(80, 3)])->assertOk()
            ->assertJsonPath('message', 'Draft penilaian berhasil disimpan.')->assertJsonPath('status', 'draft');

        $this->getJson($url)->assertJsonPath('kategori.0.komponen.2.nilai', 80)->assertJsonPath('kategori.1.komponen.0.nilai', null)
            ->assertJsonPath('kategori.0.rataan', 80)->assertJsonPath('rataan_akhir', null);
        $this->getJson('/api/penilaian')->assertJsonPath('asisten.0.status', 'draft')->assertJsonPath('asisten.0.rataan', null);
    }

    public function test_rata_rata_kategori_dan_rataan_akhir_dihitung_otomatis(): void
    {
        [, $kelas, $asisten] = $this->siapForm();
        $nilai = array_combine(array_keys($this->nilai()), [90, 88, 92, 86, 90, 88, 87, 85, 90, 88, 89]); // 3 + 4 + 4 komponen templat

        $r = $this->postJson("/api/penilaian/{$kelas->id_kelas}/{$asisten->id_pengguna}/kirim", ['nilai' => $nilai])->assertOk();

        $r->assertJsonPath('kategori.0.rataan', 90)->assertJsonPath('kategori.1.rataan', 87.75)->assertJsonPath('kategori.2.rataan', 88)->assertJsonPath('rataan_akhir', 88.58);
    }

    public function test_kirim_menyimpan_status_terkirim_dan_mengunci_penilaian(): void
    {
        [, $kelas, $asisten] = $this->siapForm();
        $url = "/api/penilaian/{$kelas->id_kelas}/{$asisten->id_pengguna}";

        $this->postJson("$url/kirim", ['nilai' => $this->nilai(90)])->assertOk()
            ->assertJsonPath('message', 'Penilaian berhasil dikirim.')->assertJsonPath('status', 'terkirim');
        $this->assertNotNull($this->getJson($url)->json('dikirim_pada'));
        $this->getJson('/api/penilaian')->assertJsonPath('asisten.0.status', 'terkirim')->assertJsonPath('asisten.0.rataan', 90)->assertJsonPath('kelas.0.terkirim', 1);

        $this->postJson("$url/draft", ['nilai' => $this->nilai(10)])->assertStatus(409);
        $this->postJson("$url/kirim", ['nilai' => $this->nilai(10)])->assertStatus(409);
        $this->getJson($url)->assertJsonPath('rataan_akhir', 90);
    }

    public function test_kirim_ditolak_bila_masih_ada_isian_kosong(): void
    {
        [, $kelas, $asisten] = $this->siapForm();
        $ids = array_keys($this->nilai());

        $this->postJson("/api/penilaian/{$kelas->id_kelas}/{$asisten->id_pengguna}/kirim", ['nilai' => $this->nilai(90, 10)])
            ->assertUnprocessable()->assertJsonValidationErrors(["nilai.{$ids[10]}"]);
        $this->getJson('/api/penilaian')->assertJsonPath('asisten.0.status', 'belum');
    }

    public function test_nilai_di_luar_rentang_atau_bukan_bilangan_bulat_ditolak(): void
    {
        [, $kelas, $asisten] = $this->siapForm();
        $ids = array_keys($this->nilai());
        $url = "/api/penilaian/{$kelas->id_kelas}/{$asisten->id_pengguna}/draft";

        foreach ([-1, 101, 85.5, 'abc'] as $salah) {
            $this->postJson($url, ['nilai' => [$ids[0] => $salah]])->assertUnprocessable()->assertJsonValidationErrors(["nilai.{$ids[0]}"]);
        }
        foreach ([0, 100] as $batas) { // batas rentang valid
            $this->postJson($url, ['nilai' => [$ids[0] => $batas]])->assertOk();
        }
    }

    public function test_akses_dibatasi_pada_pengampu_dan_asisten_kelas_itu(): void
    {
        [, $kelas, $asisten] = $this->siapForm();
        $luar = Pengguna::factory()->asisten()->create();

        $this->getJson("/api/penilaian/{$kelas->id_kelas}/{$luar->id_pengguna}")->assertNotFound();

        $this->masukSebagai(Pengguna::factory()->dosen()->create());
        $this->getJson("/api/penilaian/{$kelas->id_kelas}/{$asisten->id_pengguna}")->assertForbidden();
        $this->postJson("/api/penilaian/{$kelas->id_kelas}/{$asisten->id_pengguna}/kirim", ['nilai' => $this->nilai(90)])->assertForbidden();
    }

    private function catatPresensi(Pengguna $asisten, $kelas, string $modul, string $tanggal, string $foto = 'x.jpg'): Presensi
    {
        return Presensi::create(['id_asisten' => $asisten->id_pengguna, 'id_kelas' => $kelas->id_kelas, 'tanggal' => $tanggal,
            'kegiatan' => $modul, 'jam_masuk' => '13:00:00', 'jam_keluar' => '15:30:00', 'foto_kegiatan' => $foto]);
    }

    public function test_daftar_asisten_memuat_jumlah_kehadiran_dan_presensi_dapat_dilihat_dosen(): void
    {
        [, $kelas, $asisten] = $this->siapForm();
        $this->catatPresensi($asisten, $kelas, 'Modul 9', '2026-11-13');
        $this->catatPresensi($asisten, $kelas, 'Modul 8', '2026-11-06');

        $this->getJson('/api/penilaian')->assertJsonPath('asisten.0.hadir', 2);
        $this->getJson("/api/penilaian/{$kelas->id_kelas}/{$asisten->id_pengguna}/presensi")->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('data.0.modul', 'Modul 8')
            ->assertJsonPath('data.0.jam_masuk', '13:00')->assertJsonPath('data.0.jam_keluar', '15:30');
    }

    public function test_foto_presensi_hanya_untuk_dosen_pengampu_kelasnya(): void
    {
        $disk = Storage::fake(config('filesystems.upload_disk'));
        [, $kelas, $asisten] = $this->siapForm();
        $path = $disk->putFile('presensi/1', UploadedFile::fake()->image('foto.jpg'));
        $p = $this->catatPresensi($asisten, $kelas, 'Modul 1', '2026-11-06', $path);

        $this->getJson("/api/penilaian/presensi/{$p->id_presensi}/foto")->assertOk()->assertJsonPath('url', fn ($u) => str_starts_with($u, 'data:image/'));

        $this->masukSebagai(Pengguna::factory()->dosen()->create());
        $this->getJson("/api/penilaian/presensi/{$p->id_presensi}/foto")->assertForbidden();
        $this->getJson("/api/penilaian/{$kelas->id_kelas}/{$asisten->id_pengguna}/presensi")->assertForbidden();
    }
}
