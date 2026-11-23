<?php

namespace Database\Seeders;

use App\Http\Controllers\KelasPraktikumController;
use App\Models\Bap;
use App\Models\Pengguna;
use App\Models\Periode;
use App\Models\PeriodeBap;
use App\Models\Praktikum;
use App\Models\Presensi;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * US01: akun uji berpola. Kata sandi: admin `laboran123`, dosen `dosen123`, mahasiswa/asisten `password`.
 *
 * Kelompok akun (username mahasiswaN):
 *  - admin1–4          : admin/laboran
 *  - dosen1–52         : dosen pengampu kelas
 *  - mahasiswa1–24     : asisten aktif yang juga pernah menjabat semester lalu (punya sertifikat semester lalu)
 *  - mahasiswa25–40    : asisten aktif baru, pertama kali menjabat
 *  - mahasiswa41–55    : mahasiswa yang belum pernah menjadi asisten
 *  - mahasiswa56–63    : alumni asisten (hanya menjabat semester lalu)
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $periode = Periode::berjalan();
        $this->dataBanyak($periode);
        $this->semesterLalu(Periode::lalu());
    }

    private function dataBanyak(Periode $periode): void
    {
        $faker = fake('id_ID');
        mt_srand(2026);
        $faker->seed(2026);

        DB::transaction(function () use ($periode, $faker) {
            foreach ([1, 2, 3, 4] as $n) {
                Pengguna::create(['nama' => $faker->name(), 'username' => "admin$n", 'email' => "admin$n@gmail.com", 'kata_sandi' => 'laboran123', 'peran' => Pengguna::PERAN_ADMIN]);
            }
            $dosen = $asisten = [];
            for ($n = 1; $n <= 52; $n++) {
                $dosen[] = Pengguna::create(['nama' => $faker->name().', M.T.', 'username' => "dosen$n", 'email' => "dosen$n@gmail.com", 'kata_sandi' => 'dosen123', 'peran' => Pengguna::PERAN_DOSEN, 'nomor_induk' => (string) (23860000 + $n)])->id_pengguna;
            }
            for ($n = 1; $n <= 40; $n++) {
                $asisten[] = Pengguna::create(['nama' => $faker->name(), 'username' => "mahasiswa$n", 'email' => "mahasiswa$n@gmail.com", 'kata_sandi' => 'password', 'peran' => Pengguna::PERAN_ASISTEN, 'nomor_induk' => (string) (1204230000 + $n)])->id_pengguna;
            }
            for ($n = 41; $n <= 55; $n++) {
                Pengguna::create(['nama' => $faker->name(), 'username' => "mahasiswa$n", 'email' => "mahasiswa$n@gmail.com", 'kata_sandi' => 'password', 'peran' => Pengguna::PERAN_MAHASISWA, 'nomor_induk' => (string) (1204230000 + $n)]);
            }

            $mataKuliah = ['Algoritma dan Pemrograman', 'Struktur Data', 'Basis Data', 'Basis Data Lanjut', 'Pemrograman Web', 'Pemrograman Web Lanjut', 'Pemrograman Berorientasi Objek', 'Jaringan Komputer', 'Keamanan Jaringan', 'Sistem Operasi', 'Arsitektur Komputer', 'Rekayasa Perangkat Lunak', 'Analisis dan Perancangan Sistem', 'Pengujian Perangkat Lunak', 'Kecerdasan Buatan', 'Pembelajaran Mesin', 'Data Mining', 'Business Intelligence', 'Data Warehouse', 'Pengolahan Citra Digital', 'Interaksi Manusia dan Komputer', 'Pemrograman Game', 'Komputasi Awan', 'Internet of Things', 'Sistem Tertanam', 'Sistem Terdistribusi', 'Pemrograman Paralel', 'Manajemen Proyek TI', 'Tata Kelola TI', 'Audit Sistem Informasi', 'Enterprise Resource Planning', 'Customer Relationship Management', 'Sistem Pendukung Keputusan', 'Statistika dan Probabilitas', 'Matematika Diskrit', 'Aljabar Linear', 'Riset Operasi', 'Simulasi dan Pemodelan', 'Pemrograman Python', 'Pemrograman Java', 'Pemrograman Mobile', 'Pemrograman iOS', 'Pemrograman Cross Platform', 'DevOps', 'Big Data', 'Text Mining', 'Etika Profesi TI', 'Kewirausahaan Digital', 'Desain UI/UX', 'Jaminan Mutu Perangkat Lunak'];
            $prodi = KelasPraktikumController::PRODI;
            $penugasan = [];
            foreach ($mataKuliah as $p => $nama) {
                $praktikum = Praktikum::create(['id_periode' => $periode->id_periode, 'kode_praktikum' => sprintf('SI4%03d', $p + 1), 'nama_praktikum' => "Praktikum $nama"]);
                foreach ([1, 2] as $k => $nomor) {
                    $idDosen = $dosen[($p * 2 + $k) % count($dosen)];
                    $kelas = $praktikum->kelas()->create(['id_dosen' => $idDosen, 'kode_kelas' => sprintf('IS-05-%02d', $nomor), 'prodi' => $prodi[($p + ($k === 1 && $p % 2 === 0 ? 1 : 0)) % count($prodi)]]);
                    foreach ([0, 1] as $j) { // 4 asisten berbeda per praktikum (KF-11)
                        $idAsisten = $asisten[($p * 4 + $k * 2 + $j) % count($asisten)];
                        $kelas->asisten()->attach($idAsisten, ['id_praktikum' => $praktikum->id_praktikum]);
                        $penugasan[] = [$kelas->id_kelas, $idAsisten, $idDosen];
                    }
                }
            }

            $semuaBap = PeriodeBap::where('id_periode', $periode->id_periode)->get();
            $rentang = max(1, $periode->tanggal_mulai->diffInDays(now()->subDay()->startOfDay()));
            $kegiatan = ['Modul 1 Pengenalan', 'Modul 2 Dasar', 'Modul 3 Studi Kasus', 'Modul 4 Latihan Mandiri', 'Modul 5 Evaluasi', 'Pendampingan Tugas', 'Koreksi Laporan'];
            foreach ($penugasan as [$idKelas, $idAsisten]) {
                for ($i = 0; $i < 6; $i++) {
                    $tanggal = $periode->tanggal_mulai->copy()->addDays(mt_rand(0, $rentang));
                    $masuk = $tanggal->copy()->setTime(mt_rand(8, 15), [0, 30][mt_rand(0, 1)]);
                    $bap = $semuaBap->first(fn ($b) => $tanggal->between($b->tanggal_mulai, $b->tanggal_selesai));
                    $idBap = $bap && $bap->tanggal_selesai->lt(now()->startOfDay())
                        ? Bap::firstOrCreate(['id_asisten' => $idAsisten, 'id_kelas' => $idKelas, 'tanggal_mulai' => $bap->tanggal_mulai->toDateString(), 'tanggal_selesai' => $bap->tanggal_selesai->toDateString()], ['id_periode_bap' => $bap->id_periode_bap, 'tanggal_dibuat' => $bap->tanggal_selesai->toDateString()])->id_bap
                        : null;
                    Presensi::create(['id_asisten' => $idAsisten, 'id_kelas' => $idKelas, 'tanggal' => $tanggal->toDateString(), 'kegiatan' => $kegiatan[mt_rand(0, count($kegiatan) - 1)], 'jam_masuk' => $masuk->format('H:i'), 'jam_keluar' => $masuk->copy()->addHours(2)->format('H:i'), 'foto_kegiatan' => 'presensi/dummy.jpg'])
                        ->forceFill(['id_bap' => $idBap])->save();
                }
            }
        });
    }

    private function semesterLalu(Periode $periode): void
    {
        $dosen = Pengguna::where('peran', Pengguna::PERAN_DOSEN)->orderBy('id_pengguna')->pluck('id_pengguna')->all();
        $faker = fake('id_ID');
        $mantan = [];
        for ($n = 1; $n <= 8; $n++) {
            $mantan[] = Pengguna::create(['nama' => $faker->name(), 'username' => 'mahasiswa'.(55 + $n), 'email' => 'mahasiswa'.(55 + $n).'@gmail.com', 'kata_sandi' => 'password', 'peran' => Pengguna::PERAN_MAHASISWA, 'nomor_induk' => (string) (1204230055 + $n)])->id_pengguna;
        }
        $asisten = array_merge($mantan, Pengguna::where('peran', Pengguna::PERAN_ASISTEN)->orderBy('id_pengguna')->pluck('id_pengguna')->all());
        $kegiatan = ['Modul 1 Pengenalan', 'Modul 2 Dasar', 'Modul 3 Studi Kasus', 'Modul 4 Latihan Mandiri', 'Modul 5 Evaluasi', 'Pendampingan Tugas', 'Koreksi Laporan'];
        $nomorSurat = sprintf('045/LAB-SI/%d', $periode->tanggal_selesai->year);
        $periode->update(['nomor_surat' => $nomorSurat]);
        $semuaBap = PeriodeBap::where('id_periode', $periode->id_periode)->get();
        $rentang = $periode->tanggal_mulai->diffInDays($periode->tanggal_selesai);

        DB::transaction(function () use ($periode, $dosen, $asisten, $kegiatan, $nomorSurat, $semuaBap, $rentang) {
            $nama = ['Basis Data', 'Pemrograman Web', 'Jaringan Komputer', 'Struktur Data', 'Sistem Operasi', 'Data Mining', 'Pemrograman Mobile', 'Rekayasa Perangkat Lunak'];
            $penugasan = [];
            foreach ($nama as $p => $mk) {
                $praktikum = Praktikum::create(['id_periode' => $periode->id_periode, 'kode_praktikum' => sprintf('SI3%03d', 101 + $p), 'nama_praktikum' => "Praktikum $mk"]);
                foreach ([1, 2] as $k => $nomor) {
                    $idDosen = $dosen[($p * 2 + $k) % count($dosen)];
                    $kelas = $praktikum->kelas()->create(['id_dosen' => $idDosen, 'kode_kelas' => sprintf('IS-04-%02d', $nomor), 'prodi' => KelasPraktikumController::PRODI[$p % count(KelasPraktikumController::PRODI)]]);
                    foreach ([0, 1] as $j) {
                        $idAsisten = $asisten[($p * 4 + $k * 2 + $j) % count($asisten)];
                        $kelas->asisten()->attach($idAsisten, ['id_praktikum' => $praktikum->id_praktikum]);
                        $penugasan[] = [$kelas->id_kelas, $idAsisten, $idDosen];
                    }
                }
            }

            foreach ($penugasan as [$idKelas, $idAsisten]) {
                for ($i = 0; $i < 8; $i++) {
                    $tanggal = $periode->tanggal_mulai->copy()->addDays(mt_rand(0, $rentang));
                    $masuk = $tanggal->copy()->setTime(mt_rand(8, 15), [0, 30][mt_rand(0, 1)]);
                    $bap = $semuaBap->first(fn ($b) => $tanggal->between($b->tanggal_mulai, $b->tanggal_selesai));
                    $idBap = $bap ? Bap::firstOrCreate(['id_asisten' => $idAsisten, 'id_kelas' => $idKelas, 'tanggal_mulai' => $bap->tanggal_mulai->toDateString(), 'tanggal_selesai' => $bap->tanggal_selesai->toDateString()], ['id_periode_bap' => $bap->id_periode_bap, 'tanggal_dibuat' => $bap->tanggal_selesai->toDateString()])->id_bap : null;
                    Presensi::create(['id_asisten' => $idAsisten, 'id_kelas' => $idKelas, 'tanggal' => $tanggal->toDateString(), 'kegiatan' => $kegiatan[mt_rand(0, count($kegiatan) - 1)], 'jam_masuk' => $masuk->format('H:i'), 'jam_keluar' => $masuk->copy()->addHours(2)->format('H:i'), 'foto_kegiatan' => 'presensi/dummy.jpg'])
                        ->forceFill(['id_bap' => $idBap])->save();
                }

            }
        });
    }
}
