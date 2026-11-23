<?php

namespace App\Console\Commands;

use App\Models\Pengguna;
use Illuminate\Console\Command;

/** Membuat admin pertama di produksi (tanpa seeder). Aman dijalankan berulang: lewati bila username sudah ada. */
class BuatAdmin extends Command
{
    protected $signature = 'admin:buat {username} {email} {kata_sandi} {--nama=Admin}';
    protected $description = 'Buat akun admin bila belum ada';

    public function handle(): int
    {
        if (Pengguna::where('username', $this->argument('username'))->exists()) {
            $this->info('Admin sudah ada, dilewati.');

            return self::SUCCESS;
        }

        Pengguna::create([
            'nama' => $this->option('nama'),
            'username' => $this->argument('username'),
            'email' => $this->argument('email'),
            'kata_sandi' => $this->argument('kata_sandi'),
            'peran' => Pengguna::PERAN_ADMIN,
        ]);
        $this->info('Admin dibuat.');

        return self::SUCCESS;
    }
}
