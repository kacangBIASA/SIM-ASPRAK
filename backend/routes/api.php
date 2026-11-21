<?php

use App\Http\Controllers\BapController;
use App\Http\Controllers\BerandaController;
use App\Http\Controllers\KelasPraktikumController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\NomorSuratController;
use App\Http\Controllers\PengesahanController;
use App\Http\Controllers\PenilaianController;
use App\Http\Controllers\PresensiController;
use App\Http\Controllers\RekapitulasiController;
use App\Http\Controllers\SertifikatController;
use Illuminate\Support\Facades\Route;

// US01 / UC01 Login
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:30,1');

Route::get('/bap/{bap}/berkas', [BapController::class, 'berkas'])->name('bap.berkas')->middleware('signed');
Route::get('/sertifikat/{sertifikat}/berkas', [SertifikatController::class, 'berkas'])->name('sertifikat.berkas')->middleware('signed');

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

        // US07 Memilih praktikum yang diampu dan melihat daftar asistennya
        Route::get('/penilaian', [PenilaianController::class, 'index']);

        // US08 / UC05 Mengisi nilai · US09 / UC06 Simpan draft · UC07 Kirim penilaian
        Route::get('/penilaian/presensi/{presensi}/foto', [PenilaianController::class, 'foto']);
        Route::get('/penilaian/{kelas}/{asisten}', [PenilaianController::class, 'show']);
        Route::get('/penilaian/{kelas}/{asisten}/presensi', [PenilaianController::class, 'presensi']);
        Route::post('/penilaian/{kelas}/{asisten}/draft', [PenilaianController::class, 'draft']);
        Route::post('/penilaian/{kelas}/{asisten}/kirim', [PenilaianController::class, 'kirim']);
    });

    Route::middleware('peran:admin')->group(function () {
        // US04 / UC04 Melihat rekapitulasi kehadiran dalam BAP
        Route::get('/rekapitulasi', [RekapitulasiController::class, 'index']);

        // US11 / UC10 Mengisi nomor surat sertifikat
        Route::get('/sertifikat/nomor-surat', [NomorSuratController::class, 'show']);
        Route::post('/sertifikat/nomor-surat', [NomorSuratController::class, 'simpan']);

        // US10 / UC09 Mengunggah tanda tangan dan stempel digital
        Route::get('/sertifikat/pengesahan', [PengesahanController::class, 'show']);
        Route::post('/sertifikat/pengesahan', [PengesahanController::class, 'simpan']);
        Route::post('/sertifikat/pengesahan/posisi', [PengesahanController::class, 'posisi']);

        // US12 Menerbitkan sertifikat dari penilaian terkirim
        Route::get('/sertifikat/penerbitan', [SertifikatController::class, 'index']);
        Route::post('/sertifikat/terbitkan/{kelas}/{asisten}', [SertifikatController::class, 'terbitkan']);
        Route::get('/sertifikat/{sertifikat}/tautan', [SertifikatController::class, 'tautan']);
    });
});
