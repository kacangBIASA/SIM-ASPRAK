<?php

use App\Http\Controllers\BapController;
use App\Http\Controllers\BerandaController;
use App\Http\Controllers\KelasPraktikumController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\NomorSuratController;
use App\Http\Controllers\PresensiController;
use Illuminate\Support\Facades\Route;

// US01 / UC01 Login
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:30,1');

Route::get('/bap/{bap}/berkas', [BapController::class, 'berkas'])->name('bap.berkas')->middleware('signed');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [LoginController::class, 'me']);
    Route::post('/logout', [LoginController::class, 'logout']);
    Route::get('/beranda', BerandaController::class); // isi beranda sesuai peran (wireframe 2–4)

    Route::middleware('peran:asisten')->group(function () {
        // US02 / UC02 Mencatat presensi kegiatan
        Route::get('/presensi/form', [PresensiController::class, 'form']);
        Route::post('/presensi', [PresensiController::class, 'store']);
        Route::get('/presensi/riwayat', [PresensiController::class, 'riwayat']);
        Route::get('/presensi/{presensi}/foto', [PresensiController::class, 'foto']);

        // US03 / UC03 Mengunduh BAP
        Route::get('/bap/pilihan', [BapController::class, 'pilihan']);
        Route::post('/bap', [BapController::class, 'susun']);
        Route::get('/bap/{bap}/tautan', [BapController::class, 'tautan']);
    });

    Route::middleware('peran:dosen')->group(function () {
        // US05 / UC05 Mendaftarkan kelas praktikum
        Route::get('/kelas-praktikum', [KelasPraktikumController::class, 'index']);
        Route::post('/kelas-praktikum', [KelasPraktikumController::class, 'store']);

        // US06 / UC06 Menetapkan asisten praktikum
        Route::get('/kelas-praktikum/{kelas}', [KelasPraktikumController::class, 'show']);
        Route::post('/kelas-praktikum/{kelas}/asisten', [KelasPraktikumController::class, 'tetapkanAsisten']);
        Route::get('/asisten', [KelasPraktikumController::class, 'cariAsisten']);
    });

    Route::middleware('peran:admin')->group(function () {
        // US11 / UC10 Mengisi nomor surat sertifikat
        Route::get('/sertifikat/nomor-surat', [NomorSuratController::class, 'show']);
        Route::post('/sertifikat/nomor-surat', [NomorSuratController::class, 'simpan']);
    });
});
