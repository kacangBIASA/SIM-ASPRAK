export default function ThUrut({ k, nilai, onUbah, className, children }) {
  const aktif = nilai.urut === k;
  const menurun = aktif && nilai.arah === 'desc';

  return (
    <th className={className} aria-sort={aktif ? (menurun ? 'descending' : 'ascending') : 'none'}>
      <button type="button" className="th-urut" onClick={() => onUbah(k)}>
        {children}<span aria-hidden="true">{aktif ? (menurun ? '▼' : '▲') : '↕'}</span>
      </button>
    </th>
  );
}
