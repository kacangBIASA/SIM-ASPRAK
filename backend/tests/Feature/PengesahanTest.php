<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use App\Models\Periode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** UC09 Mengunggah tanda tangan dan stempel digital — KF-18. */
class PengesahanTest extends TestCase
{
    use RefreshDatabase;

    private function laboran(): void
    {
        Storage::fake(config('filesystems.upload_disk'));
        $this->masukSebagai(Pengguna::factory()->admin()->create());
    }

    public function test_tanda_tangan_dan_stempel_disimpan_lalu_tampil_dan_dapat_diganti(): void
    {
        $this->laboran();
        $this->getJson('/api/sertifikat/pengesahan')->assertOk()->assertJsonPath('tanda_tangan', null)->assertJsonPath('stempel', null);

        $this->post('/api/sertifikat/pengesahan', [
            'tanda_tangan' => UploadedFile::fake()->image('ttd.png'),
            'stempel' => UploadedFile::fake()->image('stempel.jpg'),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('message', 'Tanda tangan dan stempel berhasil disimpan.');

        $lama = Periode::berjalan()->path_tanda_tangan;
        $this->assertStringStartsWith('data:image/', $this->getJson('/api/sertifikat/pengesahan')->json('tanda_tangan'));

        $this->post('/api/sertifikat/pengesahan', ['tanda_tangan' => UploadedFile::fake()->image('baru.png')], ['Accept' => 'application/json'])->assertOk();
        Storage::disk(config('filesystems.upload_disk'))->assertMissing($lama);
        $this->assertNotNull(Periode::berjalan()->path_stempel);
    }

    public function test_format_ukuran_dan_berkas_kosong_ditolak(): void
    {
        $this->laboran();
        $h = ['Accept' => 'application/json'];

        $this->post('/api/sertifikat/pengesahan', ['stempel' => UploadedFile::fake()->create('s.pdf', 100, 'application/pdf')], $h)
            ->assertStatus(422)->assertJsonPath('errors.stempel.0', 'Format gambar harus PNG atau JPG.');
        $this->post('/api/sertifikat/pengesahan', ['stempel' => UploadedFile::fake()->image('s.png')->size(3000)], $h)
            ->assertStatus(422)->assertJsonPath('errors.stempel.0', 'Ukuran gambar maksimal 2 MB.');
        $this->post('/api/sertifikat/pengesahan', [], $h)->assertStatus(422);
    }

    public function test_posisi_stempel_disimpan_dan_tidak_boleh_keluar_kanvas(): void
    {
        $this->laboran();
        $this->getJson('/api/sertifikat/pengesahan')->assertJsonPath('posisi.ukuran', 14.5);

        $this->postJson('/api/sertifikat/pengesahan/posisi', ['x' => 60, 'y' => 70, 'ukuran' => 15])
            ->assertOk()->assertJsonPath('posisi.x', 60);
        $this->postJson('/api/sertifikat/pengesahan/posisi', ['x' => 95, 'y' => 70, 'ukuran' => 15])->assertStatus(422);
        $this->postJson('/api/sertifikat/pengesahan/posisi', ['x' => 10, 'y' => 90, 'ukuran' => 15])->assertStatus(422);
        $this->assertSame(60.0, Periode::berjalan()->stempel_x);
    }

    public function test_hanya_laboran(): void
    {
        $this->masukSebagai(Pengguna::factory()->dosen()->create());
        $this->getJson('/api/sertifikat/pengesahan')->assertForbidden();
    }

    public function test_latar_otomatis_dijadikan_latar_putih_transparan_dan_goresan_tetap(): void
    {
        $this->laboran();
        $img = imagecreatetruecolor(40, 40);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        imagefilledrectangle($img, 10, 10, 20, 20, imagecolorallocate($img, 0, 0, 120));
        ob_start(); imagepng($img); $png = UploadedFile::fake()->createWithContent('ttd.png', ob_get_clean());

        $this->post('/api/sertifikat/pengesahan', ['tanda_tangan' => $png], ['Accept' => 'application/json'])->assertOk();

        $hasil = imagecreatefromstring(Storage::disk(config('filesystems.upload_disk'))->get(Periode::berjalan()->path_tanda_tangan));
        $this->assertSame(127, (imagecolorat($hasil, 2, 2) >> 24) & 127); // latar bening
        $this->assertSame(0, (imagecolorat($hasil, 15, 15) >> 24) & 127); // goresan buram
    }

    public function test_png_yang_sudah_transparan_tidak_menjadi_latar_hitam(): void
    {
        $this->laboran();
        $img = imagecreatetruecolor(40, 40);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127)); // latar bening berpiksel hitam
        imagefilledrectangle($img, 10, 10, 20, 20, imagecolorallocatealpha($img, 200, 0, 0, 0));
        ob_start(); imagepng($img); $png = UploadedFile::fake()->createWithContent('stempel.png', ob_get_clean());

        $this->post('/api/sertifikat/pengesahan', ['stempel' => $png], ['Accept' => 'application/json'])->assertOk();

        $hasil = imagecreatefromstring(Storage::disk(config('filesystems.upload_disk'))->get(Periode::berjalan()->path_stempel));
        $this->assertSame(127, (imagecolorat($hasil, 2, 2) >> 24) & 127);
        $this->assertSame(0, (imagecolorat($hasil, 15, 15) >> 24) & 127);
    }

    public function test_ukuran_tanda_tangan_disimpan_dalam_rentang(): void
    {
        $this->laboran();
        $this->getJson('/api/sertifikat/pengesahan')->assertJsonPath('posisi.ttd_ukuran', 26.1);

        $this->postJson('/api/sertifikat/pengesahan/posisi', ['x' => 60, 'y' => 70, 'ukuran' => 15, 'ttd_ukuran' => 30])->assertOk()->assertJsonPath('posisi.ttd_ukuran', 30);
        $this->postJson('/api/sertifikat/pengesahan/posisi', ['x' => 60, 'y' => 70, 'ukuran' => 15, 'ttd_ukuran' => 50])->assertStatus(422);
        $this->postJson('/api/sertifikat/pengesahan/posisi', ['x' => 61, 'y' => 70, 'ukuran' => 15])->assertOk()->assertJsonPath('posisi.ttd_ukuran', 30); // tidak dikirim → tetap
    }
}
