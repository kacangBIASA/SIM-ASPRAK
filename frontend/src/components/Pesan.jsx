import Ikon from './Ikon';

export default function Pesan({ jenis = 'info', children, onTutup }) {
  if (!children) return null;
  return (
    <div className={`pesan pesan-${jenis}`} role={jenis === 'galat' ? 'alert' : 'status'}>
      <span className="pesan-isi"><Ikon nama={jenis === 'sukses' ? 'centang' : 'info'} ukuran={16} />{children}</span>
      {onTutup && (
        <button className="pesan-tutup" onClick={onTutup} aria-label="Tutup pesan">×</button>
      )}
    </div>
  );
}
