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

export function useDaftar(path, extra = {}) {
  const [page, setPage] = useState(1);
  const [per, setPerState] = useState(10);
  const [cari, setCariState] = useState('');
  const [data, setData] = useState(null);
  const [galat, setGalat] = useState('');
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
      .then((d) => { if (!batal) { setData(d); setGalat(''); } })
      .catch((e) => { if (!batal) setGalat(e.message); });
    return () => { batal = true; };
  }, [path, kunci, versi]);

  return {
    data,
    setData,
    kunci,
    galat,
    muat: () => setVersi((v) => v + 1),
    cari: { nilai: cari, onUbah: setCariState },
    urut: {
      nilai: urutan,
      onUbah: (k) => setUrutan((u) => (u.urut !== k ? { urut: k, arah: 'asc' } : u.arah === 'asc' ? { urut: k, arah: 'desc' } : { urut: '', arah: 'asc' })),
    },
    paging: { halaman: data?.halaman, onUbah: setPage, onPer: (n) => { setPerState(n); setPage(1); } },
  };
}
