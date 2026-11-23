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
| 2 | US04 Rekapitulasi kehadiran · US07 Praktikum diampu dan daftar asisten · US10 Tanda tangan dan stempel | ✅ |
| 2 | US08 Mengisi nilai · US09 Simpan draft dan kirim penilaian | ✅ |
| 2 | US12 Menerbitkan sertifikat | ✅ |
| 3 | US14 Mengelola dokumen sumber chatbot (KF-21, KF-22) | ✅ |
| 3 | US15 · US16 Bertanya ke chatbot, pesan di luar cakupan | ✅ |
| 3 | US13 Unduh sertifikat | ✅ |

Isi saat ini (iterasi 1):

- **Asisten**: Presensi (`/api/presensi/form`, `POST /api/presensi`). BAP (`/api/bap/*`, unduh PDF lewat tautan bertanda tangan).
- **Dosen**: Kelas Praktikum (`/api/kelas-praktikum`, tambah kelas). Penetapan asisten (`/api/kelas-praktikum/{id}/asisten`).
- **Laboran**: Sertifikat → nomor surat periode berjalan (`/api/sertifikat/nomor-surat`). Tab Penerbitan: syarat (nomor surat, tanda tangan, stempel, penilaian terkirim) + tombol Terbitkan per asisten (`/api/sertifikat/penerbitan`, `/terbitkan/{kelas}/{asisten}`); PDF dibuat DomPDF dan dibuka lewat tautan bertanda tangan.
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

Akun uji dari seeder (`php artisan db:seed`), login dengan username atau email `<username>@gmail.com`:

| Kelompok | Username | Kata sandi |
|---|---|---|
| Admin/laboran | `admin1`–`admin4` | `laboran123` |
| Dosen | `dosen1`–`dosen52` | `dosen123` |
| Asisten aktif, pernah menjabat semester lalu | `mahasiswa1`–`mahasiswa24` | `password` |
| Asisten aktif baru (pertama kali) | `mahasiswa25`–`mahasiswa40` | `password` |
| Mahasiswa belum pernah jadi asisten | `mahasiswa41`–`mahasiswa55` | `password` |
| Alumni asisten (hanya semester lalu) | `mahasiswa56`–`mahasiswa63` | `password` |

Pengujian: `cd backend && php artisan test` (SQLite in-memory).

## Catatan untuk menambah user story berikutnya

1. Tambah migration, model, dan controller untuk user story tersebut.
2. Daftarkan rute di `backend/routes/api.php` (di dalam grup `auth:sanctum`, dengan `peran:…`).
3. Tambah halaman di `frontend/src/pages/` dan daftarkan di `HALAMAN[peran][path]` pada `App.jsx` dan di `menu.js`.
