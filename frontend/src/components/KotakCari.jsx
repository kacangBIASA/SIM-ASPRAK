import Ikon from './Ikon';

/* Kolom pencarian tabel (wireframe: ikon kaca pembesar + placeholder). */
export default function KotakCari({ nilai, onUbah, placeholder }) {
  return (
    <label className="kotak-cari">
      <Ikon nama="cari" ukuran={16} />
      <input type="search" value={nilai} onChange={(e) => onUbah(e.target.value)} placeholder={placeholder} aria-label={placeholder} />
    </label>
  );
}
