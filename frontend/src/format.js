const BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

export function tanggal(iso) {
  if (!iso) return '-';
  const [y, m, d] = iso.split('-').map(Number);
  return `${String(d).padStart(2, '0')} ${BULAN[m - 1]} ${y}`;
}

export const NAMA_PERAN = {
  mahasiswa: 'Mahasiswa',
  asisten: 'Asisten Praktikum',
  dosen: 'Dosen Pengampu',
  admin: 'Admin',
};

/** Nilai penilaian berkoma: minimal satu desimal, maksimal dua (89 → "89,0", 87.75 → "87,75"); kosong → "–". */
export const angka = (n) => (n === null || n === undefined ? '–' : n.toFixed(2).replace(/0$/, '').replace('.', ','));
