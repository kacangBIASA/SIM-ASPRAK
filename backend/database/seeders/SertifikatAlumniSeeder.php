<?php

namespace Database\Seeders;

use App\Http\Controllers\PengesahanController;
use App\Models\KomponenPenilaian;
use App\Models\Pengguna;
use App\Models\Penilaian;
use App\Models\Periode;
use App\Models\Sertifikat;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Data uji SUS/UAT: semua penugasan semester lalu (alumni asisten dan asisten yang juga menjabat semester lalu) diberi
 * penilaian terkirim dan sertifikat terbit. Dapat dijalankan ulang kapan saja dan hanya menambah yang belum ada:
 *   php artisan db:seed --class=SertifikatAlumniSeeder
 * PDF dibuat saat sertifikat pertama kali dibuka/diunduh (SertifikatController::berkas) dari tanda tangan dan stempel periode.
 */
class SertifikatAlumniSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(2027);
        $periode = Periode::lalu();
        $laboran = Pengguna::where('peran', Pengguna::PERAN_ADMIN)->orderBy('id_pengguna')->firstOrFail();
        $this->siapkanPengesahan($periode);
        $komponen = KomponenPenilaian::pluck('id_komponen');

        $penugasan = DB::table('penugasan_asisten as pa')
            ->join('praktikum as p', 'p.id_praktikum', '=', 'pa.id_praktikum')
            ->where('p.id_periode', $periode->id_periode)
            ->get(['pa.id_kelas', 'pa.id_asisten']);

        DB::transaction(function () use ($penugasan, $periode, $laboran, $komponen) {
            foreach ($penugasan as $t) {
                $penilaian = Penilaian::firstOrCreate(
                    ['id_kelas' => $t->id_kelas, 'id_asisten' => $t->id_asisten],
                    ['status' => Penilaian::TERKIRIM, 'dikirim_pada' => $periode->tanggal_selesai->copy()->addDay()],
                );
                if ($penilaian->status !== Penilaian::TERKIRIM) {
                    continue; // penilaian yang sedang diisi dosen tidak diubah
                }
                if (! $penilaian->komponen()->exists()) {
                    $penilaian->komponen()->attach($komponen->mapWithKeys(fn ($id) => [$id => ['nilai' => mt_rand(78, 98)]])->all());
                }
                Sertifikat::firstOrCreate(['id_penilaian' => $penilaian->id_penilaian], [
                    'id_laboran' => $laboran->id_pengguna,
                    'nomor_surat' => $periode->nomor_surat,
                    'tanggal_terbit' => $periode->tanggal_selesai->copy()->addDays(7)->toDateString(),
                ]);
            }
        });
    }

    /** Nomor surat, penandatangan, serta gambar tanda tangan dan stempel contoh agar PDF dapat dibuat. */
    private function siapkanPengesahan(Periode $periode): void
    {
        $disk = Storage::disk(config('filesystems.upload_disk'));
        $konfig = config('services.sertifikat');
        $periode->nomor_surat ??= sprintf('045/LAB-SI/%d', $periode->tanggal_selesai->year);
        $periode->nama_penandatangan ??= $konfig['nama'];
        $periode->nip_penandatangan ??= $konfig['nip'];
        // Tanda tangan selalu gambar contoh buatan; stempel memakai stempel_contoh.png (di luar git) bila ada. Pengesahan unggahan laboran tidak ditimpa.
        $sumber = [
            'path_tanda_tangan' => ['', 'contoh-tanda-tangan', 'gambarTandaTangan'],
            'path_stempel' => [base_path('../stempel_contoh.png'), 'contoh-stempel', 'gambarStempel'],
        ];
        foreach ($sumber as $kolom => [$berkas, $nama, $buat]) {
            $punyaSendiri = $periode->{$kolom} && ! str_contains($periode->{$kolom}, '/contoh-') && $disk->exists($periode->{$kolom});
            if ($punyaSendiri) {
                continue;
            }
            $periode->{$kolom} = "pengesahan/{$periode->id_periode}/$nama.png";
            $disk->put($periode->{$kolom}, is_file($berkas) ? app(PengesahanController::class)->tanpaLatar($berkas) : $this->{$buat}());
        }
        $periode->save();
    }

    private function gambarTandaTangan(): string
    {
        $im = $this->kanvas(440, 116);
        $tinta = imagecolorallocate($im, 20, 40, 120);
        imagesetthickness($im, 4);
        $titik = [[10, 80], [60, 25], [100, 95], [150, 30], [190, 90], [250, 40], [300, 85], [360, 50], [430, 70]];
        for ($i = 0; $i < count($titik) - 1; $i++) {
            imageline($im, $titik[$i][0], $titik[$i][1], $titik[$i + 1][0], $titik[$i + 1][1], $tinta);
        }

        return $this->png($im);
    }

    private function gambarStempel(): string
    {
        $im = $this->kanvas(300, 300);
        $tinta = imagecolorallocate($im, 30, 70, 170);
        imagesetthickness($im, 6);
        imageellipse($im, 150, 150, 280, 280, $tinta);
        imagesetthickness($im, 2);
        imageellipse($im, 150, 150, 230, 230, $tinta);
        imagestring($im, 5, 96, 136, 'LAB SISTEM INFORMASI', $tinta);

        return $this->png($im);
    }

    private function kanvas(int $w, int $h): \GdImage
    {
        $im = imagecreatetruecolor($w, $h);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));

        return $im;
    }

    private function png(\GdImage $im): string
    {
        ob_start();
        imagepng($im);

        return ob_get_clean();
    }
}
