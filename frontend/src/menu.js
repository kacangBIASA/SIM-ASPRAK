/* Menu sidebar per peran (KF-02), sesuai wireframe. `judul` = judul topbar bila berbeda dari label menu. Halaman tiap item didaftarkan di HALAMAN pada App.jsx. Iterasi 1: US01, US02, US03, US05, US06, US11; iterasi 2: US04, US07–US10, US12. */
const BERANDA = { ke: '/', label: 'Beranda', ikon: 'beranda' };
const SERTIFIKAT = { ke: '/sertifikat', label: 'Sertifikat', ikon: 'sertifikat' };

export const MENU = {
  mahasiswa: [BERANDA, SERTIFIKAT],
  asisten: [
    BERANDA,
    { ke: '/presensi', label: 'Presensi', ikon: 'kamera' },                                  // US02
    { ke: '/bap', label: 'BAP', judul: 'Berita Acara Pekerjaan', ikon: 'dokumen' },          // US03
  ],
  dosen: [
    BERANDA,
    { ke: '/kelas-praktikum', label: 'Kelas Praktikum', ikon: 'folder' },                    // US05, US06
    { ke: '/penilaian', label: 'Penilaian', ikon: 'bintang' },                               // US07–US10
  ],
  admin: [
    BERANDA,
    { ke: '/rekapitulasi', label: 'Rekapitulasi Kehadiran', ikon: 'tabel' },                // US04
    SERTIFIKAT,                                                                               // US11
  ],
};
