function nomor({ saat_ini: s, terakhir: t }) {
  const set = [...new Set([1, s - 1, s, s + 1, t])].filter((n) => n >= 1 && n <= t).sort((a, b) => a - b);
  return set.flatMap((n, i) => (i && n - set[i - 1] > 1 ? [null, n] : [n]));
}

/* Penutup tabel: "Menampilkan a–b dari N data", pilihan baris per halaman, dan nomor halaman (wireframe). `halaman` dari API. */
export default function Halaman({ halaman, onUbah, onPer }) {
  if (!halaman) return null;
  const { saat_ini: s, terakhir: t, total, dari, ke, per } = halaman;

  return (
    <div className="paginasi">
      <span className="redup">{total === 0 ? 'Tidak ada data' : `Menampilkan ${dari}–${ke} dari ${total} data`}</span>
      <div className="paginasi-kanan">
        {onPer && (
          <label className="redup">Baris per halaman{' '}
            <select value={per} onChange={(e) => onPer(Number(e.target.value))}>
              {[5, 10, 25, 50].map((n) => <option key={n}>{n}</option>)}
            </select>
          </label>
        )}
        <nav className="nomor-halaman" aria-label="Halaman">
          <button type="button" disabled={s <= 1} onClick={() => onUbah(s - 1)} aria-label="Halaman sebelumnya">‹</button>
          {nomor(halaman).map((n, i) => (n === null
            ? <span key={`e${i}`}>…</span>
            : <button key={n} type="button" aria-current={n === s ? 'page' : undefined} className={n === s ? 'aktif' : ''} onClick={() => onUbah(n)}>{n}</button>))}
          <button type="button" disabled={s >= t} onClick={() => onUbah(s + 1)} aria-label="Halaman berikutnya">›</button>
        </nav>
      </div>
    </div>
  );
}
