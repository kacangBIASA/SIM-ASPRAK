<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        $this->batasChatbot();
    }

    private function batasChatbot(): void
    {
        RateLimiter::for('chatbot', function (Request $request) {
            $c = config('services.chatbot');
            $id = $request->user()?->id_pengguna ?? $request->ip();
            $tolak = fn (string $pesan) => fn () => response()->json(['message' => $pesan], 429);

            return [
                Limit::perMinute($c['batas_menit'])->by("menit:$id")->response($tolak('Terlalu banyak pertanyaan dalam waktu singkat. Tunggu sebentar lalu coba lagi.')),
                Limit::perMinute($c['batas_ip_menit'])->by('ip:'.$request->ip())->response($tolak('Terlalu banyak pertanyaan dari jaringan Anda. Tunggu sebentar lalu coba lagi.')),
                Limit::perDay($c['batas_harian'])->by("harian:$id")->response($tolak('Batas pertanyaan harian Anda sudah tercapai. Coba lagi besok atau hubungi laboran.')),
                Limit::perDay($c['batas_global'])->by('global')->response($tolak('Layanan chatbot sedang dibatasi karena banyaknya permintaan. Coba lagi nanti.')),
            ];
        });
    }
}
