<?php

namespace Tests\Feature;

use App\Models\Bap;
use App\Models\Pengguna;
use App\Models\Periode;
use App\Models\PeriodeBap;
use App\Models\Presensi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** UC04 Melihat rekapitulasi kehadiran dalam BAP — KF-08. */
class RekapitulasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-21 09:00');
    }

    private function isiBap(Pengguna $asisten, $kelas, array $presensi, ?PeriodeBap $pb = null): Bap
    {
        $pb ??= PeriodeBap::where('id_periode', Periode::berjalan()->id_periode)->whereDate('tanggal_mulai', '<=', '2026-10-21')->orderByDesc('tanggal_mulai')->first();
        $bap = Bap::create(['id_asisten' => $asisten->id_pengguna, 'id_kelas' => $kelas->id_kelas, 'id_periode_bap' => $pb->id_periode_bap,
            'tanggal_mulai' => $pb->tanggal_mulai, 'tanggal_selesai' => $pb->tanggal_selesai, 'tanggal_dibuat' => '2026-10-21']);
        foreach ($presensi as [$tgl, $masuk, $keluar]) {
            Presensi::unguarded(fn () => Presensi::create(['id_asisten' => $asisten->id_pengguna, 'id_kelas' => $kelas->id_kelas, 'id_bap' => $bap->id_bap, 'tanggal' => $tgl,
                'kegiatan' => 'Modul', 'jam_masuk' => $masuk, 'jam_keluar' => $keluar, 'foto_kegiatan' => 'x.jpg']));
        }

        return $bap;
    }

    public function test_rekap_menjumlahkan_kehadiran_dan_jam_dari_presensi_dalam_bap(): void
    {
        [$asisten, $kelas] = $this->asistenDenganKelas();
        $this->isiBap($asisten, $kelas, [['2026-10-05', '08:00:00', '10:30:00'], ['2026-10-12', '08:00:00', '10:00:00']]);
        // presensi yang belum masuk BAP tidak ikut dihitung
        Presensi::create(['id_asisten' => $asisten->id_pengguna, 'id_kelas' => $kelas->id_kelas, 'tanggal' => '2026-10-19', 'kegiatan' => 'Modul', 'jam_masuk' => '08:00:00', 'jam_keluar' => '12:00:00', 'foto_kegiatan' => 'x.jpg']);
        $this->masukSebagai(Pengguna::factory()->admin()->create());

        $this->getJson('/api/rekapitulasi')->assertOk()
            ->assertJsonPath('statistik', [1, 2, 4.5])
            ->assertJsonCount(1, 'baris')
            ->assertJsonPath('baris.0.nama', $asisten->nama)
            ->assertJsonPath('baris.0.hadir', 2)
            ->assertJsonPath('baris.0.jam', 4.5)
            ->assertJsonPath('baris.0.kelas', 'IS-05-01');
    }

    public function test_filter_praktikum_rentang_tanggal_dan_cari(): void
    {
        [$a1, $k1] = $this->asistenDenganKelas();
        $k2 = $this->buatKelas(kodeMk: 'SI3107');
        $a2 = Pengguna::factory()->asisten()->create(['nama' => 'Zara Unik']);
        $this->tugaskan($a2, $k2);
        $this->isiBap($a1, $k1, [['2026-10-05', '08:00:00', '10:00:00']]);
        $this->isiBap($a2, $k2, [['2026-10-06', '08:00:00', '09:00:00']]);
        $this->masukSebagai(Pengguna::factory()->admin()->create());

        $this->getJson('/api/rekapitulasi')->assertJsonCount(2, 'baris')->assertJsonPath('statistik.1', 2);
        $this->getJson('/api/rekapitulasi?praktikum='.$k2->id_praktikum)->assertJsonCount(1, 'baris')->assertJsonPath('baris.0.nama', 'Zara Unik');
        $this->getJson('/api/rekapitulasi?q=zara')->assertJsonCount(1, 'baris');

        // rentang bebas lintas bulan: hanya presensi di dalam rentang yang dihitung
        $this->getJson('/api/rekapitulasi?dari=2026-10-06&sampai=2026-10-31')->assertJsonCount(1, 'baris')->assertJsonPath('statistik.1', 1);
        $this->getJson('/api/rekapitulasi?dari=2026-09-20&sampai=2026-10-31')->assertJsonCount(2, 'baris');
        $this->getJson('/api/rekapitulasi?dari=2026-10-31&sampai=2026-10-01')->assertStatus(422);
    }

    public function test_hanya_laboran(): void
    {
        $this->masukSebagai(Pengguna::factory()->dosen()->create());
        $this->getJson('/api/rekapitulasi')->assertForbidden();
    }
}
