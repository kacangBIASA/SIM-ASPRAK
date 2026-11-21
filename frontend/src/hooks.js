import { useEffect, useState } from 'react';
import { api } from './api';

export function useTunda(nilai, ms = 300) {
  const [tunda, setTunda] = useState(nilai);
  useEffect(() => {
    const t = setTimeout(() => setTunda(nilai), ms);
    return () => clearTimeout(t);
  }, [nilai, ms]);
  return tunda;
}

export function useDaftar(path, extra = {}, perAwal = 10) {
  const [page, setPage] = useState(1);
  const [per, setPerState] = useState(perAwal);
  const [cari, setCariState] = useState('');
  const [data, setData] = useState(null);
  const [error, setError] = useState('');
  const [versi, setVersi] = useState(0);
  const [urutan, setUrutan] = useState({ urut: '', arah: 'asc' });
  const q = useTunda(cari);
  const filter = JSON.stringify([extra, urutan]);
  const kunci = new URLSearchParams({ page, per, q, ...extra, ...(urutan.urut ? urutan : {}) }).toString();

  useEffect(() => { setPage(1); }, [q, filter, path]);
  useEffect(() => {
    if (!path) return undefined;
    let batal = false;
    api.get(`${path}?${kunci}`)
      .then((d) => { if (!batal) { setData(d); setError(''); } })
      .catch((e) => { if (!batal) setError(e.message); });
    return () => { batal = true; };
  }, [path, kunci, versi]);

  return {
    data,
    setData,
    kunci,
    error,
    muat: () => setVersi((v) => v + 1),
    cari: { nilai: cari, onUbah: setCariState },
    urut: {
      nilai: urutan,
      onUbah: (k) => setUrutan((u) => (u.urut !== k ? { urut: k, arah: 'asc' } : u.arah === 'asc' ? { urut: k, arah: 'desc' } : { urut: '', arah: 'asc' })),
    },
    paging: { halaman: data?.halaman, onUbah: setPage, onPer: (n) => { setPerState(n); setPage(1); } },
  };
}
