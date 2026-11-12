const BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

/** "2026-11-04" → "04 November 2026" */
export function tanggal(iso) {
  if (!iso) return '-';
  const [y, m, d] = iso.split('-').map(Number);
  return `${String(d).padStart(2, '0')} ${BULAN[m - 1]} ${y}`;
}

export const NAMA_PERAN = {
  asisten: 'Asisten Praktikum',
  dosen: 'Dosen Pengampu',
  laboran: 'Laboran',
};
