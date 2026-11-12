/**
 * Menu sidebar per peran (KF-02), sesuai wireframe. `judul` = judul topbar bila berbeda dari label menu.
 * Item yang fiturnya belum dibuat diarahkan ke halaman "segera hadir" sampai user story-nya selesai.
 */
const BERANDA = { ke: '/', label: 'Beranda', ikon: 'beranda' };
const BANTUAN = { ke: '/bantuan', label: 'Bantuan (Chatbot)', ikon: 'obrolan' };
const SERTIFIKAT = { ke: '/sertifikat', label: 'Sertifikat', ikon: 'sertifikat' };

export const MENU = {
  asisten: [
    BERANDA,
    { ke: '/presensi', label: 'Presensi', ikon: 'kamera' },                                  // US02
    { ke: '/bap', label: 'BAP', judul: 'Berita Acara Pekerjaan', ikon: 'dokumen' },          // US03
    SERTIFIKAT,                                                                               // US13
    BANTUAN,
  ],
  dosen: [
    BERANDA,
    { ke: '/kelas-praktikum', label: 'Kelas Praktikum', ikon: 'folder' },                    // US05, US06
    { ke: '/penilaian', label: 'Penilaian', ikon: 'bintang' },                               // US07–US10
    BANTUAN,
  ],
  laboran: [
    BERANDA,
    { ke: '/rekapitulasi', label: 'Rekapitulasi Kehadiran', ikon: 'tabel' },                // US04
    SERTIFIKAT,                                                                               // US11, US12
    { ke: '/dokumen-chatbot', label: 'Dokumen Chatbot', ikon: 'folder' },                    // US14
    BANTUAN,
  ],
};
