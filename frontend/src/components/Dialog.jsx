import { useEffect } from 'react';

export default function Dialog({ judul, onTutup, children, aksi, kelas = '' }) {
  useEffect(() => {
    const tutup = (e) => e.key === 'Escape' && onTutup();
    document.addEventListener('keydown', tutup);
    return () => document.removeEventListener('keydown', tutup);
  }, [onTutup]);

  return (
    <div className="dialog-latar" onClick={onTutup}>
      <div className={`dialog kartu ${kelas}`} role="dialog" aria-modal="true" aria-label={judul} onClick={(e) => e.stopPropagation()}>
        <h2>{judul}</h2>
        {children}
        {aksi && <div className="aksi">{aksi}</div>}
      </div>
    </div>
  );
}
