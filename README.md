# SIM Asisten Praktikum — Versi Bertahap

Dibangun bertahap per user story mengikuti Tabel 4.5.2 (Perencanaan iterasi) pada proposal.
Kode sebelum rollback 9 Nov 2026 disimpan di `../backup-sim-asprak-sebelum-rollback-20261109`.

| Iterasi | User story | Status |
|---|---|---|
| 1 | US01 Masuk ke sistem sesuai peran (UC01 Login, KF-01, KF-02) | ✅ |
| 1 | US05 Mendaftarkan kelas praktikum (UC05, KF-09, KF-10) | ✅ |
| 1 | US06 Menetapkan asisten pada kelas (UC06, KF-11) | ✅ |
| 1 | US02 Mencatat presensi beserta foto (UC02, KF-03, KF-04) | ✅ |
| 1 | US03 Memperoleh BAP dari presensi (UC03, KF-05, KF-06, KF-07) | ✅ |
| 1 | US11 Mengisi nomor surat sertifikat (UC10, KF-17) | ✅ |
| 2 | US04 · US07 · US08 · US09 · US10 · US12 | ⏳ |
| 3 | US13 · US14 · US15 · US16 | ⏳ |

Isi saat ini (iterasi 1):

- **Asisten**: Presensi (`/api/presensi/form`, `POST /api/presensi`). BAP (`/api/bap/*`, unduh PDF lewat tautan bertanda tangan).
- **Dosen**: Kelas Praktikum (`/api/kelas-praktikum`, tambah kelas). Penetapan asisten (`/api/kelas-praktikum/{id}/asisten`).
- **Laboran**: Sertifikat → nomor surat periode berjalan (`/api/sertifikat/nomor-surat`). Status penilaian & tombol Terbitkan menyusul di US12.
- Menu lain → "Segera hadir".

## Menjalankan

Database memakai port **5434** dan DB `sim_asprak`.

```
docker compose up -d
cd backend
php artisan migrate:fresh --seed
php artisan serve            # http://localhost:8000
```
```
cd frontend
npm run dev                  # http://localhost:5173
```

Akun uji (kata sandi `password`): `asisten1@gmail.com`…`asisten4@gmail.com`, `dosen1@gmail.com`, `dosen2@gmail.com`, `laboran@gmail.com`.
Seeder juga membuat contoh kelas SI3104 (IS-05-01 dosen1, IS-05-02 dosen2) dan SI3107 (IS-05-01 dosen2) pada periode berjalan; asisten4 belum ditugaskan agar penetapan asisten bisa dicoba.

Pengujian: `cd backend && php artisan test` (SQLite in-memory).

## Catatan untuk menambah user story berikutnya

1. Tambah migration/model/controller untuk user story tersebut.
2. Daftarkan rute di `backend/routes/api.php` (di dalam grup `auth:sanctum`, dengan `peran:…`).
3. Tambah halaman di `frontend/src/pages/` dan daftarkan di `HALAMAN[peran][path]` pada `App.jsx` (menu sudah ada di `menu.js`).
