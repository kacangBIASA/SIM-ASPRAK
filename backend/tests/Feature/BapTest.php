<?php

namespace Tests\Feature;

use App\Models\Bap;
use App\Models\KelasPraktikum;
use App\Models\Pengguna;
use App\Models\Presensi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** UC03 Mengunduh BAP — KF-05, KF-06, KF-07. */
class BapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-21 09:00');
    }

    private function presensi(Pengguna $asisten, KelasPraktikum $kelas, string $tanggal, string $masuk = '08:00', string $keluar = '10:30'): Presensi
    {
        return Presensi::create([
            'id_asisten' => $asisten->id_pengguna, 'id_kelas' => $kelas->id_kelas, 'tanggal' => $tanggal,
            'kegiatan' => 'Modul 1', 'jam_masuk' => $masuk.':00', 'jam_keluar' => $keluar.':00', 'foto_kegiatan' => 'presensi/x.jpg',
        ]);
    }

    private function susun(KelasPraktikum $kelas, string $mulai = '2026-09-26', string $selesai = '2026-10-25')
    {
        return $this->postJson('/api/bap', ['id_kelas' => $kelas->id_kelas, 'tanggal_mulai' => $mulai, 'tanggal_selesai' => $selesai]);
    }

    public function test_pilihan_praktikum(): void
    {
        [$asisten, $kelas] = $this->asistenDenganKelas();
        $this->buatKelas(kodeKelas: 'IS-05-09');
        $this->masukSebagai($asisten);

        $this->getJson('/api/bap/pilihan')
            ->assertOk()
            ->assertJsonCount(1, 'praktikum')
            ->assertJsonPath('praktikum.0.id_kelas', $kelas->id_kelas)
            ->assertJsonPath('praktikum.0.label', "SI3104 · {$kelas->praktikum->nama_praktikum} (IS-05-01)")
            ->assertJsonPath('praktikum.0.id_periode', $kelas->praktikum->id_periode);
    }

    public function test_bap_disusun_dari_presensi_pada_periode(): void
    {
        [$asisten, $kelas] = $this->asistenDenganKelas();
        $this->presensi($asisten, $kelas, '2026-10-21', '13:00', '16:00');
        $this->presensi($asisten, $kelas, '2026-09-30');
        $diluar = $this->presensi($asisten, $kelas, '2026-09-20');
        $this->masukSebagai($asisten);

        $this->susun($kelas)
            ->assertOk()
            ->assertJsonPath('data.nama', $asisten->nama)
            ->assertJsonPath('data.kode_mk', 'SI3104')
            ->assertJsonPath('data.bulan', 'September - Oktober')
            ->assertJsonPath('data.total_jam', '5,5 Jam')
            ->assertJsonCount(2, 'data.baris')
            ->assertJsonPath('data.baris.0.jumlah_jam', '2,5 Jam')
            ->assertJsonPath('data.baris.0.kelas', 'IS-05-01')
            ->assertJsonPath('data.dosen', $kelas->dosen->nama);

        $bap = Bap::sole();
        $this->assertSame(2, Presensi::where('id_bap', $bap->id_bap)->count());
        $this->assertNull($diluar->fresh()->id_bap);
    }

    public function test_menyusun_ulang_tidak_membuat_bap_ganda(): void
    {
        [$asisten, $kelas] = $this->asistenDenganKelas();
        $this->presensi($asisten, $kelas, '2026-10-21');
        $this->masukSebagai($asisten);

        $this->susun($kelas)->assertOk();
        $this->presensi($asisten, $kelas, '2026-10-20', '13:00', '14:00');
        $this->susun($kelas)->assertOk()->assertJsonCount(2, 'data.baris');

        $this->assertDatabaseCount('bap', 1);
    }

    public function test_rentang_tanggal_bebas_lintas_bulan(): void
    {
        [$asisten, $kelas] = $this->asistenDenganKelas();
        $this->travelTo('2026-12-18 09:00');
        $this->presensi($asisten, $kelas, '2026-11-25');
        $this->presensi($asisten, $kelas, '2026-11-26');
        $this->presensi($asisten, $kelas, '2026-12-16');
        $luar = $this->presensi($asisten, $kelas, '2026-12-17');
        $this->masukSebagai($asisten);

        $r = $this->susun($kelas, '2026-11-26', '2026-12-16')->assertOk()
            ->assertJsonPath('data.bulan', 'November - Desember')->assertJsonCount(2, 'data.baris');
        $this->assertNull($luar->fresh()->id_bap);

        $tautan = $this->getJson("/api/bap/{$r->json('data.id_bap')}/tautan")->json();
        $this->assertSame("BAP_{$asisten->nomor_induk}_SI3104_20261126-20261216.pdf", $tautan['nama_berkas']);
    }

    public function test_rentang_tumpang_tindih_memberi_peringatan(): void
    {
        [$asisten, $kelas] = $this->asistenDenganKelas();
        $this->presensi($asisten, $kelas, '2026-10-10');
        $this->presensi($asisten, $kelas, '2026-10-20');
        $this->masukSebagai($asisten);

        $this->susun($kelas, '2026-10-01', '2026-10-15')->assertOk()->assertJsonPath('peringatan', null);
        $this->susun($kelas, '2026-10-05', '2026-10-25')->assertOk()
            ->assertJsonPath('peringatan', '1 presensi pada rentang ini sebelumnya sudah masuk BAP lain dan kini dipindahkan ke BAP ini.');
        $this->susun($kelas, '2026-10-05', '2026-10-25')->assertJsonPath('peringatan', null);
    }

    public function test_rentang_terbalik_ditolak(): void
    {
        [$asisten, $kelas] = $this->asistenDenganKelas();
        $this->masukSebagai($asisten);

        $this->susun($kelas, '2026-10-25', '2026-10-01')->assertStatus(422)->assertJsonValidationErrors('tanggal_selesai');
    }

    public function test_alternate_4a_belum_ada_presensi(): void
    {
        [$asisten, $kelas] = $this->asistenDenganKelas();
        $this->masukSebagai($asisten);

        $this->susun($kelas)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Data presensi pada rentang tanggal ini belum tersedia. Silakan ubah tanggal.');
        $this->assertDatabaseCount('bap', 0);
    }

    public function test_kelas_bukan_tugasnya_ditolak(): void
    {
        [$asisten] = $this->asistenDenganKelas();
        $lain = $this->buatKelas(kodeKelas: 'IS-05-09');
        $this->masukSebagai($asisten);

        $this->susun($lain)->assertStatus(422)->assertJsonValidationErrors('id_kelas');
    }

    public function test_tanggal_selesai_di_luar_semester_ditolak(): void
    {
        [$asisten, $kelas] = $this->asistenDenganKelas();
        $this->masukSebagai($asisten);

        $this->susun($kelas, '2026-10-01', '2027-03-01')->assertStatus(422)->assertJsonValidationErrors('tanggal_selesai');
    }

    public function test_unduh_pdf_lewat_tautan_bertanda_tangan(): void
    {
        [$asisten, $kelas] = $this->asistenDenganKelas();
        $this->presensi($asisten, $kelas, '2026-10-21');
        $this->masukSebagai($asisten);
        $id = $this->susun($kelas)->json('data.id_bap');

        $tautan = $this->getJson("/api/bap/{$id}/tautan")->assertOk()->json();
        $this->assertSame("BAP_{$asisten->nomor_induk}_SI3104_20260926-20261025.pdf", $tautan['nama_berkas']);

        $this->get($tautan['url'])->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get("/api/bap/{$id}/berkas")->assertStatus(403);
    }

    public function test_tautan_bap_milik_asisten_lain_ditolak(): void
    {
        [$asisten, $kelas] = $this->asistenDenganKelas();
        $this->presensi($asisten, $kelas, '2026-10-21');
        $this->masukSebagai($asisten);
        $id = $this->susun($kelas)->json('data.id_bap');

        $this->masukSebagai(Pengguna::factory()->asisten()->create());
        $this->getJson("/api/bap/{$id}/tautan")->assertStatus(403);
    }
}
