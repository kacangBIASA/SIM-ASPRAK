const JALUR = {
  beranda: 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z',
  kamera: 'M4 7h3l2-3h6l2 3h3a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1zm8 3.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z',
  dokumen: 'M6 2h8l6 6v13a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1zm7 1.5V9h5.5M8 13h8M8 17h8',
  bintang: 'm12 3 2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9z',
  tabel: 'M3 4h18v16H3zM3 9h18M3 14h18M9 4v16',
  keluar: 'M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4M10 16l4-4-4-4M14 12H4',
  menu: 'M4 6h16M4 12h16M4 18h16',
  unduh: 'M12 4v11m0 0-4-4m4 4 4-4M5 20h14',
  kembali: 'M15 18l-6-6 6-6',
  foto: 'M4 5h16v14H4zM4 15l4-4 4 4 3-3 5 5M15 9.5a1.5 1.5 0 1 0 0-.01',
  tutup: 'M6 6l12 12M18 6 6 18',
  sertifikat: 'M12 3a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11zM8.6 12.8 7 21l5-2.8 5 2.8-1.6-8.2',
  obrolan: 'M4 5h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H9l-5 4V6a1 1 0 0 1 1-1zM8 10h8M8 13h5',
  folder: 'M3 6a1 1 0 0 1 1-1h5l2 2h9a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z',
};

export default function Ikon({ nama, ukuran = 18 }) {
  return (
    <svg width={ukuran} height={ukuran} viewBox="0 0 24 24" fill="none" stroke="currentColor"
      strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d={JALUR[nama]} />
    </svg>
  );
}
