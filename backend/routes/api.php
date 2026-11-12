<?php

use App\Http\Controllers\BapController;
use App\Http\Controllers\BerandaController;
use App\Http\Controllers\KelasPraktikumController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\NomorSuratController;
use App\Http\Controllers\PresensiController;
use Illuminate\Support\Facades\Route;

// US01 / UC01 Login
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [LoginController::class, 'me']);
    Route::post('/logout', [LoginController::class, 'logout']);
    Route::get('/beranda', BerandaController::class); // isi beranda sesuai peran (wireframe 2–4)

    // Fitur user story berikutnya ditambahkan di sini, dibatasi per peran dengan
    // ->middleware('peran:asisten') / 'peran:dosen' / 'peran:laboran'.

    Route::middleware('peran:asisten')->group(function () {
        // US02 / UC02 Mencatat presensi kegiatan
        Route::get('/presensi/form', [PresensiController::class, 'form']);
        Route::post('/presensi', [PresensiController::class, 'store']);
        // US03 / UC03 Mengunduh BAP
        Route::get('/bap/pilihan', [BapController::class, 'pilihan']);
        Route::post('/bap', [BapController::class, 'susun']);
        Route::get('/bap/{bap}/unduh', [BapController::class, 'unduh']);
    });

    Route::middleware('peran:dosen')->group(function () {
        // US05 / UC05 Mendaftarkan kelas praktikum
        Route::get('/kelas-praktikum', [KelasPraktikumController::class, 'index']);
        Route::post('/kelas-praktikum', [KelasPraktikumController::class, 'store']);
        // US06 / UC06 Menetapkan asisten praktikum
        Route::get('/kelas-praktikum/{kelas}/asisten', [KelasPraktikumController::class, 'asisten']);
        Route::get('/kelas-praktikum/{kelas}/asisten/cari', [KelasPraktikumController::class, 'cariAsisten']);
        Route::post('/kelas-praktikum/{kelas}/asisten', [KelasPraktikumController::class, 'tugaskan']);
    });

    Route::middleware('peran:laboran')->group(function () {
        // US11 / UC10 Mengisi nomor surat sertifikat
        Route::get('/sertifikat/nomor-surat', [NomorSuratController::class, 'show']);
        Route::post('/sertifikat/nomor-surat', [NomorSuratController::class, 'simpan']);
    });
});

// US03 / UC03 Mengunduh BAP
Route::get('/bap/{bap}/berkas', [BapController::class, 'berkas'])->middleware('signed')->name('bap.berkas');
