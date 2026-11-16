<?php

namespace Database\Factories;

use App\Models\Pengguna;
use Illuminate\Database\Eloquent\Factories\Factory;

class PenggunaFactory extends Factory
{
    protected $model = Pengguna::class;

    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'kata_sandi' => 'password',
            'peran' => Pengguna::PERAN_ASISTEN,
            'nomor_induk' => (string) fake()->unique()->numerify('120423####'),
        ];
    }

    public function mahasiswa(): static
    {
        return $this->state(['peran' => Pengguna::PERAN_MAHASISWA]);
    }

    public function asisten(): static
    {
        return $this->state(['peran' => Pengguna::PERAN_ASISTEN]);
    }

    public function dosen(): static
    {
        return $this->state(['peran' => Pengguna::PERAN_DOSEN, 'nomor_induk' => fake()->numerify('2023####')]);
    }

    public function admin(): static
    {
        return $this->state(['peran' => Pengguna::PERAN_ADMIN, 'nomor_induk' => null]);
    }
}
