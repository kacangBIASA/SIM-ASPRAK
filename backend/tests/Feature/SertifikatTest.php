<?php

namespace Tests\Feature;

use App\Models\KomponenPenilaian;
use App\Models\Pengguna;
use App\Models\Penilaian;
use App\Models\Periode;
use App\Models\Sertifikat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** UC09 / US12 Menerbitkan sertifikat dari penilaian terkirim — KF-19, KF-20. */
class SertifikatTest extends TestCase
{
    use RefreshDatabase;

    /** Laboran masuk; syarat periode (nomor surat, tanda tangan, stempel) sudah terisi kecuali dimatikan. */
    private function laboran(bool $syaratLengkap = true): void
    {
        $disk = Storage::fake(config('filesystems.upload_disk'));
        if ($syaratLengkap) {
            $disk->put('pengesahan/ttd.png', UploadedFile::fake()->image('ttd.png', 200, 100)->getContent());
            $disk->put('pengesahan/stempel.png', UploadedFile::fake()->image('stempel.png', 120, 120)->getContent());
            Periode::berjalan()->update(['nomor_surat' => '641/AKD09/TUKS/2026', 'path_tanda_tangan' => 'pengesahan/ttd.png', 'path_stempel' => 'pengesahan/stempel.png', 'nama_penandatangan' => 'Dr. Direktur', 'nip_penandatangan' => '25760006']);
        }
        $this->masukSebagai(Pengguna::factory()->admin()->create());
    }

    /** @return array{0: \App\Models\KelasPraktikum, 1: Pengguna, 2: Penilaian} */
    private function penilaian(string $status = Penilaian::TERKIRIM): array
    {
        $kelas = $this->buatKelas();
        $asisten = Pengguna::factory()->asisten()->create(['nama' => 'Salsabila Putri']);
        $this->tugaskan($asisten, $kelas);
        $p = Penilaian::create(['id_kelas' => $kelas->id_kelas, 'id_asisten' => $asisten->id_pengguna, 'status' => $status]);
        $p->komponen()->sync(KomponenPenilaian::pluck('id_komponen')->mapWithKeys(fn ($id) => [$id => ['nilai' => 85]])->all());

        return [$kelas, $asisten, $p];
    }

    public function test_daftar_penerbitan_menampilkan_syarat_dan_status_tiap_asisten(): void
    {
        $this->laboran();
        [, , $terkirim] = $this->penilaian();
        $this->tugaskan(Pengguna::factory()->asisten()->create(['nama' => 'Zulkifli']), $this->buatKelas(kodeMk: 'SI3107'));

        $r = $this->getJson('/api/sertifikat/penerbitan')->assertOk()
            ->assertJsonPath('syarat', ['nomor_surat' => true, 'tanda_tangan' => true, 'stempel' => true, 'penilaian_terkirim' => true, 'lengkap' => true])
            ->assertJsonCount(2, 'baris')
            ->assertJsonPath('baris.0.penilaian', 'terkirim')->assertJsonPath('baris.0.id_sertifikat', null)
            ->assertJsonPath('baris.1.penilaian', 'belum');
        $this->assertSame($terkirim->id_kelas, $r->json('baris.0.id_kelas'));
        $this->getJson('/api/sertifikat/penerbitan?q=zulkifli')->assertJsonCount(1, 'baris');
    }

    public function test_daftar_penerbitan_dapat_diurutkan(): void
    {
        $this->laboran();
        $this->penilaian(); // Salsabila, terkirim
        $this->tugaskan(Pengguna::factory()->asisten()->create(['nama' => 'Aaron']), $this->buatKelas(kodeMk: 'SI3107'));

        $this->getJson('/api/sertifikat/penerbitan?urut=nama&arah=desc')->assertJsonPath('baris.0.nama', 'Salsabila Putri');
        $this->getJson('/api/sertifikat/penerbitan?urut=penilaian&arah=desc')->assertJsonPath('baris.0.penilaian', 'terkirim');
        $this->getJson('/api/sertifikat/penerbitan?urut=penilaian&arah=asc')->assertJsonPath('baris.0.penilaian', 'belum');
    }

    public function test_syarat_belum_lengkap_ditampilkan(): void
    {
        $this->laboran(syaratLengkap: false);
        $this->getJson('/api/sertifikat/penerbitan')->assertOk()
            ->assertJsonPath('syarat', ['nomor_surat' => false, 'tanda_tangan' => false, 'stempel' => false, 'penilaian_terkirim' => false, 'lengkap' => false]);
    }

    public function test_terbitkan_membuat_sertifikat_dan_berkas_pdf(): void
    {
        $this->laboran();
        [$kelas, $asisten, $p] = $this->penilaian();

        $this->postJson("/api/sertifikat/terbitkan/{$kelas->id_kelas}/{$asisten->id_pengguna}")->assertCreated()
            ->assertJsonPath('message', 'Sertifikat Salsabila Putri berhasil diterbitkan.');

        $s = Sertifikat::firstWhere('id_penilaian', $p->id_penilaian);
        $this->assertSame('641/AKD09/TUKS/2026', $s->nomor_surat);
        $this->assertStringStartsWith('%PDF', Storage::disk(config('filesystems.upload_disk'))->get($s->berkas_sertifikat));
        $this->getJson('/api/sertifikat/penerbitan')->assertJsonPath('baris.0.id_sertifikat', $s->id_sertifikat);
    }

    public function test_sertifikat_tidak_dapat_diterbitkan_dua_kali(): void
    {
        $this->laboran();
        [$kelas, $asisten] = $this->penilaian();
        $url = "/api/sertifikat/terbitkan/{$kelas->id_kelas}/{$asisten->id_pengguna}";

        $this->postJson($url)->assertCreated();
        $this->postJson($url)->assertStatus(409);
        $this->assertSame(1, Sertifikat::count());
    }

    public function test_hanya_penilaian_terkirim_yang_dapat_diterbitkan(): void
    {
        $this->laboran();
        [$kelas, $asisten] = $this->penilaian(Penilaian::DRAFT);
        $tanpaNilai = Pengguna::factory()->asisten()->create();
        $this->tugaskan($tanpaNilai, $kelas);

        $this->postJson("/api/sertifikat/terbitkan/{$kelas->id_kelas}/{$asisten->id_pengguna}")->assertUnprocessable();
        $this->postJson("/api/sertifikat/terbitkan/{$kelas->id_kelas}/{$tanpaNilai->id_pengguna}")->assertUnprocessable();
        $this->assertSame(0, Sertifikat::count());
    }

    public function test_penerbitan_ditolak_bila_nomor_surat_tanda_tangan_atau_stempel_belum_terisi(): void
    {
        $this->laboran(syaratLengkap: false);
        [$kelas, $asisten] = $this->penilaian();
        $url = "/api/sertifikat/terbitkan/{$kelas->id_kelas}/{$asisten->id_pengguna}";

        $this->postJson($url)->assertUnprocessable();
        foreach ([['nomor_surat' => '1/A'], ['path_tanda_tangan' => 'pengesahan/ttd.png']] as $sebagian) { // masih ada yang kosong
            Storage::disk(config('filesystems.upload_disk'))->put('pengesahan/ttd.png', UploadedFile::fake()->image('ttd.png')->getContent());
            Periode::berjalan()->update($sebagian);
            $this->postJson($url)->assertUnprocessable();
        }
        $this->assertSame(0, Sertifikat::count());
    }

    public function test_tautan_bertanda_tangan_membuka_pdf_dan_tanpa_tanda_tangan_ditolak(): void
    {
        $this->laboran();
        [$kelas, $asisten, $p] = $this->penilaian();
        $this->postJson("/api/sertifikat/terbitkan/{$kelas->id_kelas}/{$asisten->id_pengguna}")->assertCreated();
        $s = Sertifikat::firstWhere('id_penilaian', $p->id_penilaian);

        $url = $this->getJson("/api/sertifikat/{$s->id_sertifikat}/tautan")->assertOk()->json('url');
        $r = $this->get($url)->assertOk();
        $this->assertSame('application/pdf', $r->headers->get('Content-Type'));
        $this->get("/api/sertifikat/{$s->id_sertifikat}/berkas")->assertForbidden();
    }

    public function test_hanya_laboran_yang_dapat_menerbitkan(): void
    {
        Storage::fake(config('filesystems.upload_disk'));
        [$kelas, $asisten] = $this->penilaian();
        $this->masukSebagai(Pengguna::factory()->dosen()->create());

        $this->getJson('/api/sertifikat/penerbitan')->assertForbidden();
        $this->postJson("/api/sertifikat/terbitkan/{$kelas->id_kelas}/{$asisten->id_pengguna}")->assertForbidden();
    }

    public function test_asisten_melihat_dan_mengunduh_sertifikatnya_sendiri(): void
    {
        $this->laboran();
        [$kelas, $asisten, $p] = $this->penilaian();
        $lain = $this->buatKelas(kodeMk: 'SI3107');
        $this->tugaskan($asisten, $lain); // praktikum kedua: belum dinilai
        $this->postJson("/api/sertifikat/terbitkan/{$kelas->id_kelas}/{$asisten->id_pengguna}")->assertCreated();
        $s = Sertifikat::firstWhere('id_penilaian', $p->id_penilaian);

        $this->masukSebagai($asisten);
        $r = $this->getJson('/api/sertifikat-saya')->assertOk()->assertJsonCount(2, 'baris');
        $this->assertContains($s->id_sertifikat, array_column($r->json('baris'), 'id_sertifikat'));
        $this->getJson('/api/sertifikat-saya?q=SI3107')->assertJsonCount(1, 'baris')->assertJsonPath('baris.0.id_sertifikat', null);

        $t = $this->getJson("/api/sertifikat-saya/{$s->id_sertifikat}/tautan")->assertOk();
        $this->assertStringStartsWith('Sertifikat_', $t->json('nama_berkas'));
        $unduh = $this->get($t->json('url'))->assertOk();
        $this->assertStringContainsString('attachment', $unduh->headers->get('Content-Disposition'));
    }

    public function test_asisten_tidak_dapat_mengunduh_sertifikat_orang_lain_dan_peran_lain_ditolak(): void
    {
        $this->laboran();
        [$kelas, $asisten, $p] = $this->penilaian();
        $this->postJson("/api/sertifikat/terbitkan/{$kelas->id_kelas}/{$asisten->id_pengguna}")->assertCreated();
        $s = Sertifikat::firstWhere('id_penilaian', $p->id_penilaian);

        $orangLain = Pengguna::factory()->asisten()->create();
        $this->tugaskan($orangLain, $this->buatKelas(kodeMk: 'SI3107')); // peran asisten hanya berlaku bila ada penugasan
        $this->masukSebagai($orangLain);
        $this->getJson("/api/sertifikat-saya/{$s->id_sertifikat}/tautan")->assertForbidden();
        $this->getJson('/api/sertifikat-saya')->assertOk()->assertJsonCount(1, 'baris')->assertJsonPath('baris.0.id_sertifikat', null); // hanya miliknya sendiri

        $this->masukSebagai(Pengguna::factory()->dosen()->create());
        $this->getJson('/api/sertifikat-saya')->assertForbidden();
    }

    public function test_berkas_dibuat_ulang_dari_pengaturan_periode_saat_ini(): void
    {
        $this->laboran();
        [$kelas, $asisten, $p] = $this->penilaian();
        $this->postJson("/api/sertifikat/terbitkan/{$kelas->id_kelas}/{$asisten->id_pengguna}")->assertCreated();
        $s = Sertifikat::firstWhere('id_penilaian', $p->id_penilaian);
        $disk = Storage::disk(config('filesystems.upload_disk'));
        $lama = $disk->get($s->berkas_sertifikat);

        // laboran mengubah posisi stempel dan nomor surat setelah sertifikat terbit
        Periode::berjalan()->update(['stempel_x' => 10, 'stempel_y' => 10, 'ttd_ukuran' => 30, 'nomor_surat' => '999/BARU/2026']);
        $url = $this->getJson("/api/sertifikat/{$s->id_sertifikat}/tautan")->json('url');
        $this->get($url)->assertOk();

        $this->assertNotSame($lama, $disk->get($s->fresh()->berkas_sertifikat));
        $this->assertSame('999/BARU/2026', $s->fresh()->nomor_surat);
        $this->getJson('/api/sertifikat/penerbitan')->assertOk();
    }
}
