import { useEffect, useState } from 'react';
import { api } from '../api';
import { BarisSkel } from './Skeleton';
import Dialog from './Dialog';
import Pesan from './Pesan';

const pendek = (iso) => new Date(`${iso}T00:00:00`).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
const panjang = (iso) => new Date(`${iso}T00:00:00`).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' });
const jam = (h) => h.replace(':', '.');

/* Wireframe 8 (US07): presensi satu asisten pada kelas terpilih, hanya baca; foto dibuka per baris. */
export default function DialogPresensiAsisten({ idKelas, asisten, onTutup }) {
  const [data, setData] = useState(null);
  const [error, setError] = useState('');
  const [foto, setFoto] = useState(null); // { tanggal, url }
  const [memuat, setMemuat] = useState(false);

  useEffect(() => {
    api.get(`/penilaian/${idKelas}/${asisten.id_asisten}/presensi`).then(setData).catch((e) => setError(e.message));
  }, [idKelas, asisten.id_asisten]);

  async function lihat(p) {
    setMemuat(true);
    setError('');
    try {
      setFoto({ tanggal: p.tanggal, url: (await api.get(`/penilaian/presensi/${p.id_presensi}/foto`)).url });
    } catch (e) {
      setError(e.message);
    } finally {
      setMemuat(false);
    }
  }

  return (
    <Dialog judul={`Presensi · ${asisten.nama}`} kelas="dialog-presensi" onTutup={onTutup} aksi={<button type="button" className="tombol" onClick={onTutup}>Tutup</button>}>
      {data && <p className="redup">{data.praktikum} · {data.kelas} · hanya baca</p>}
      <Pesan jenis="error">{error}</Pesan>
      <div className="tabel-bungkus">
        <table className="tabel">
          <thead><tr><th style={{ width: 110 }}>Tanggal</th><th>Modul</th><th style={{ width: 90 }}>Jam masuk</th><th style={{ width: 90 }}>Jam keluar</th><th style={{ width: 100 }}>Foto</th></tr></thead>
          <tbody>
                {!data && !error && <BarisSkel kolom={5} />}
            {data?.data.length === 0 && <tr><td colSpan={5} className="redup">Asisten ini belum mencatat presensi pada kelas ini.</td></tr>}
            {data?.data.map((p) => (
              <tr key={p.id_presensi}>
                <td className="redup">{pendek(p.tanggal)}</td>
                <td>{p.modul}</td>
                <td className="redup">{jam(p.jam_masuk)}</td>
                <td className="redup">{jam(p.jam_keluar)}</td>
                <td><button type="button" className="tombol tombol-kecil" disabled={memuat} onClick={() => lihat(p)}>Lihat</button></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {foto && (
        <figure className="pratinjau-presensi">
          <img src={foto.url} alt={`Foto kegiatan ${pendek(foto.tanggal)}`} />
          <figcaption>Foto kegiatan · {panjang(foto.tanggal)}</figcaption>
        </figure>
      )}
    </Dialog>
  );
}
