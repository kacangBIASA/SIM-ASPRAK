<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** UC01 Login — KF-01, KF-02, KNF-02. */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_berhasil_mengembalikan_token_dan_peran(): void
    {
        Pengguna::factory()->dosen()->create(['email' => 'dosen@test.id', 'kata_sandi' => 'rahasia123']);

        $res = $this->postJson('/api/login', ['login' => 'dosen@test.id', 'kata_sandi' => 'rahasia123']);

        $res->assertOk()
            ->assertJsonStructure(['token', 'pengguna' => ['id_pengguna', 'nama', 'peran']])
            ->assertJsonPath('pengguna.peran', 'dosen')
            ->assertJsonMissingPath('pengguna.kata_sandi');
    }

    public function test_login_dengan_username(): void
    {
        Pengguna::factory()->create(['username' => 'asisten9', 'kata_sandi' => 'rahasia123']);

        $this->postJson('/api/login', ['login' => 'asisten9', 'kata_sandi' => 'rahasia123'])
            ->assertOk()
            ->assertJsonPath('pengguna.peran', 'asisten');
    }

    public function test_alternate_3a_isian_kosong(): void
    {
        $this->postJson('/api/login', ['login' => '', 'kata_sandi' => ''])
            ->assertStatus(422)
            ->assertJsonPath('errors.login.0', 'Email atau username wajib diisi.')
            ->assertJsonPath('errors.kata_sandi.0', 'Kata sandi wajib diisi.');
    }

    public function test_alternate_4a_kredensial_salah(): void
    {
        Pengguna::factory()->create(['email' => 'asisten@test.id', 'kata_sandi' => 'benar']);

        $this->postJson('/api/login', ['login' => 'asisten@test.id', 'kata_sandi' => 'salah'])
            ->assertStatus(422)
            ->assertJsonPath('errors.login.0', 'Email atau username atau kata sandi salah.');

        $this->postJson('/api/login', ['login' => 'tidakada@test.id', 'kata_sandi' => 'benar'])
            ->assertStatus(422)
            ->assertJsonPath('errors.login.0', 'Email atau username atau kata sandi salah.');
    }

    public function test_kata_sandi_disimpan_sebagai_hash(): void
    {
        $p = Pengguna::factory()->create(['kata_sandi' => 'rahasia123']);

        $this->assertNotEquals('rahasia123', $p->getRawOriginal('kata_sandi'));
        $this->assertTrue(Hash::check('rahasia123', $p->getRawOriginal('kata_sandi')));
    }

    public function test_profil_tidak_dapat_diakses_tanpa_login(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
    }

    public function test_profil_mengembalikan_pengguna_yang_login(): void
    {
        $this->masukSebagai(Pengguna::factory()->laboran()->create(['email' => 'lab@test.id']));

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('pengguna.email', 'lab@test.id')
            ->assertJsonPath('pengguna.peran', 'laboran');
    }

    public function test_logout_mencabut_token(): void
    {
        Pengguna::factory()->create(['email' => 'a@test.id', 'kata_sandi' => 'password']);
        $token = $this->postJson('/api/login', ['login' => 'a@test.id', 'kata_sandi' => 'password'])->json('token');

        $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
