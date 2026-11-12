<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** KF-02 / KNF-02: rute dibatasi per peran lewat middleware `peran`. */
class HakAksesPeranTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Route::middleware(['api', 'auth:sanctum', 'peran:dosen'])->get('/api/uji-peran', fn () => ['ok' => true]);
    }

    public function test_peran_sesuai_boleh_akses(): void
    {
        $this->masukSebagai(Pengguna::factory()->dosen()->create());

        $this->getJson('/api/uji-peran')->assertOk();
    }

    public function test_peran_lain_ditolak(): void
    {
        $this->masukSebagai(Pengguna::factory()->asisten()->create());

        $this->getJson('/api/uji-peran')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Anda tidak memiliki akses ke fitur ini.');
    }

    public function test_tanpa_login_ditolak(): void
    {
        $this->getJson('/api/uji-peran')->assertStatus(401);
    }
}
