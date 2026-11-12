import { useEffect, useState } from 'react';
import { BarisSkeleton } from '../components/Skeleton';
import { api } from '../api';
import { useAuth } from '../auth';
import { NAMA_PERAN, tanggal } from '../format';
import Pesan from '../components/Pesan';

/*
 * UC01 langkah 5: beranda sesuai peran (wireframe 2–4) — kartu sambutan, tiga kartu statistik, satu tabel.
 */
const ISI = {
  asisten: {
    statistik: ['Kehadiran Bulan Ini', 'Total Jam Bulan Ini', 'Praktikum yang Ditugaskan'],
    tabel: 'Presensi Terakhir',
    kolom: ['Tanggal', 'Praktikum', 'Jam Masuk', 'Jam Keluar'],
    sel: ([tgl, ...lain]) => [tanggal(tgl), ...lain],
  },
  dosen: {
    statistik: ['Praktikum yang Diampu', 'Jumlah Asisten', 'Penilaian Belum Terkirim'],
    tabel: 'Praktikum yang Diampu',
    kolom: ['Praktikum', 'Jumlah Asisten', 'Status Penilaian'],
  },
  laboran: {
    statistik: ['Asisten Aktif', 'Praktikum Berjalan', 'Presensi Bulan Ini'],
    tabel: 'Praktikum Berjalan',
    kolom: ['Praktikum', 'Dosen Pengampu', 'Jumlah Asisten'],
  },
};

export default function Beranda() {
  const { pengguna } = useAuth();
  const isi = ISI[pengguna.peran];
  const [data, setData] = useState(null);
  const [galat, setGalat] = useState('');

  useEffect(() => {
    api.get('/beranda').then(setData).catch((e) => setGalat(e.message));
  }, []);

  return (
    <>
      <div className="kartu sambutan">
        <h2>Selamat datang, {pengguna.nama.split(' ')[0]}</h2>
        <p className="redup">Anda masuk sebagai <strong>{NAMA_PERAN[pengguna.peran]}</strong>
          {pengguna.nomor_induk && <> · {pengguna.nomor_induk}</>}</p>
      </div>

      <Pesan jenis="galat">{galat}</Pesan>

      <div className="ringkasan-kartu">
        {isi.statistik.map((s, i) => (
          <div key={s} className="kartu statistik">
            <small>{s}</small>
            <strong>{data ? data.statistik[i] : <i className="skeleton" style={{ width: '4ch' }} />}</strong>
          </div>
        ))}
      </div>

      <div className="kartu">
        <h2>{isi.tabel}</h2>
        <div className="tabel-bungkus">
          <table className="tabel">
            <thead>
              <tr><th>No</th>{isi.kolom.map((k) => <th key={k}>{k}</th>)}</tr>
            </thead>
            <tbody>
              {!data && !galat && <BarisSkeleton kolom={isi.kolom.length + 1} />}
              {data?.baris.length === 0 && (
                <tr><td colSpan={isi.kolom.length + 1} className="redup">Belum ada data.</td></tr>
              )}
              {data?.baris.map((b, i) => (
                <tr key={i}>
                  <td>{i + 1}</td>
                  {(isi.sel ? isi.sel(b) : b).map((v, j) => <td key={j}>{v}</td>)}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </>
  );
}
