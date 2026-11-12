export default function Skeleton({ baris = 4 }) {
  return (
    <div className="kartu" role="status" aria-label="Memuat">
      <i className="skeleton" style={{ height: 24, width: '35%', marginBottom: '1rem' }} />
      {Array.from({ length: baris }, (_, n) => (
        <i key={n} className="skeleton" style={{ height: 38, marginBottom: '.7rem' }} />
      ))}
    </div>
  );
}

export function BarisSkeleton({ kolom, jumlah = 3 }) {
  return Array.from({ length: jumlah }, (_, n) => (
    <tr key={n}><td colSpan={kolom}><i className="skeleton" style={{ height: 18 }} /></td></tr>
  ));
}
