/* Menu sidebar per peran (KF-02), sesuai wireframe. `judul` = judul topbar bila berbeda dari label menu. Halaman tiap item didaftarkan di HALAMAN pada App.jsx. Iterasi 1: US01, US02, US03, US05, US06, US11. */
const BERANDA = { ke: '/', label: 'Beranda', ikon: 'beranda' };

export const MENU = {
  mahasiswa: [BERANDA],
  asisten: [
    BERANDA,
    { ke: '/presensi', label: 'Presensi', ikon: 'kamera' },                                  // US02
    { ke: '/bap', label: 'BAP', judul: 'Berita Acara Pekerjaan', ikon: 'dokumen' },          // US03
  ],
  dosen: [
    BERANDA,
    { ke: '/kelas-praktikum', label: 'Kelas Praktikum', ikon: 'folder' },                    // US05, US06
  ],
  admin: [
    BERANDA,
    { ke: '/sertifikat', label: 'Nomor Surat', ikon: 'sertifikat' },                         // US11
  ],
};
