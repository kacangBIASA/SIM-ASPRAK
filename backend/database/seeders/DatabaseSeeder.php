<?php

namespace Database\Seeders;

use App\Models\Pengguna;
use App\Models\Periode;
use App\Models\Praktikum;
use Illuminate\Database\Seeder;

/** US01: akun uji untuk ketiga peran (kata sandi `password`). */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $akun = [
            ['Laboran', 'laboran', 'laboran@gmail.com', '22900011', Pengguna::PERAN_LABORAN],
            ['Dosen Pengampu Satu', 'dosen1', 'dosen1@gmail.com', '23860001', Pengguna::PERAN_DOSEN],
            ['Dosen Pengampu Dua', 'dosen2', 'dosen2@gmail.com', '23860002', Pengguna::PERAN_DOSEN],
            ['Ridho Nasrullah', 'ridhonasrullah', 'asisten1@gmail.com', '1204230085', Pengguna::PERAN_ASISTEN],
            ['Asisten Dua', 'asisten2', 'asisten2@gmail.com', '1204230001', Pengguna::PERAN_ASISTEN],
            ['Asisten Tiga', 'asisten3', 'asisten3@gmail.com', '1204230002', Pengguna::PERAN_ASISTEN],
            ['Asisten Empat', 'asisten4', 'asisten4@gmail.com', '1204230003', Pengguna::PERAN_ASISTEN],
        ];

        foreach ($akun as [$nama, $username, $email, $nomorInduk, $peran]) {
            Pengguna::create([
                'nama' => $nama, 'username' => $username, 'email' => $email,
                'kata_sandi' => 'password', 'peran' => $peran, 'nomor_induk' => $nomorInduk,
            ]);
        }

        // US05/US06: contoh kelas & penugasan pada periode berjalan.
        $periode = Periode::berjalan();
        $id = fn (string $email) => Pengguna::where('email', $email)->value('id_pengguna');
        $dataWarehouse = Praktikum::create(['id_periode' => $periode->id_periode, 'kode_praktikum' => 'SI3104', 'nama_praktikum' => 'Praktikum Data Warehouse', 'prodi' => 'Sistem Informasi']);
        $mobile = Praktikum::create(['id_periode' => $periode->id_periode, 'kode_praktikum' => 'SI3107', 'nama_praktikum' => 'Praktikum Pemrograman Mobile', 'prodi' => 'Sistem Informasi']);

        foreach ([
            [$dataWarehouse, 'dosen1@gmail.com', 'IS-05-01', ['asisten1@gmail.com', 'asisten2@gmail.com']],
            [$dataWarehouse, 'dosen2@gmail.com', 'IS-05-02', ['asisten3@gmail.com']],
            [$mobile, 'dosen2@gmail.com', 'IS-05-01', ['asisten1@gmail.com']],
        ] as [$praktikum, $dosen, $kodeKelas, $asisten]) {
            $kelas = $praktikum->kelas()->create(['id_dosen' => $id($dosen), 'kode_kelas' => $kodeKelas]);
            foreach ($asisten as $email) {
                $kelas->asisten()->attach($id($email), ['id_praktikum' => $praktikum->id_praktikum]);
            }
        }
    }
}
