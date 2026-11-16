# SIM Asisten Praktikum

Sistem informasi manajemen kegiatan asisten praktikum di laboratorium. Sistem ini menggantikan pencatatan manual (presensi, BAP, penilaian, sertifikat) dengan satu aplikasi web untuk tiga peran:

- **Asisten Praktikum**: mencatat presensi kegiatan beserta foto, lalu memperoleh Berita Acara Pekerjaan (BAP) yang tersusun otomatis dari data presensi dan dapat diunduh sebagai PDF.
- **Dosen Pengampu**: mendaftarkan kelas praktikum dan menetapkan asisten pada kelas yang diampu.
- **Laboran**: mengisi nomor surat sertifikat untuk periode berjalan.

Dibangun bertahap per user story mengikuti Tabel 4.5.2 (Perencanaan iterasi) pada proposal. Backend memakai Laravel (PostgreSQL), frontend memakai React (Vite).

## Status pengembangan

| Iterasi | User story | Status |
|---|---|---|
| 1 | US01 Masuk ke sistem sesuai peran | ✅ |
| 1 | US05 Mendaftarkan kelas praktikum | ✅ |
| 1 | US06 Menetapkan asisten pada kelas | ✅ |
| 1 | US02 Mencatat presensi beserta foto | ✅ |
| 1 | US03 Memperoleh BAP dari presensi | ✅ |
| 1 | US11 Mengisi nomor surat sertifikat | ✅ |
| 2 | US04 · US07 · US08 · US09 · US10 · US12 | ⏳ |
| 3 | US13 · US14 · US15 · US16 | ⏳ |

Isi saat ini (iterasi 1):

- **Asisten**: Presensi (`/api/presensi/form`, `POST /api/presensi`). BAP (`/api/bap/*`, unduh PDF lewat tautan bertanda tangan).
- **Dosen**: Kelas Praktikum (`/api/kelas-praktikum`, tambah kelas). Penetapan asisten (`/api/kelas-praktikum/{id}/asisten`).
- **Laboran**: Sertifikat → nomor surat periode berjalan (`/api/sertifikat/nomor-surat`). Status penilaian & tombol Terbitkan menyusul di US12.
- Menu lain → "Segera hadir".

## Menjalankan

Jalankan dari folder utama proyek.

**1. Database** (PostgreSQL di port `5434`, DB `sim_asprak`)

```
docker compose up -d
```

**2. Backend** (http://localhost:8000)

```
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

**3. Frontend** (http://localhost:5173), di terminal lain

```
cd frontend
npm run dev                  # http://localhost:5173
```

Akun uji (kata sandi `password`): `asisten1@gmail.com`…`asisten4@gmail.com`, `dosen1@gmail.com`, `dosen2@gmail.com`, `laboran@gmail.com`.
Seeder juga membuat contoh kelas SI3104 (IS-05-01 dosen1, IS-05-02 dosen2) dan SI3107 (IS-05-01 dosen2) pada periode berjalan; asisten4 belum ditugaskan agar penetapan asisten bisa dicoba.

Pengujian: `cd backend && php artisan test` (SQLite in-memory).

## Catatan untuk menambah user story berikutnya

1. Tambah migration, model, dan controller untuk user story tersebut.
2. Daftarkan rute di `backend/routes/api.php` (di dalam grup `auth:sanctum`, dengan `peran:…`).
3. Tambah halaman di `frontend/src/pages/` dan daftarkan di `HALAMAN[peran][path]` pada `App.jsx` dan di `menu.js`.
