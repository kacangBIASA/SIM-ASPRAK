import { useState } from 'react';
import { api } from '../api';
import { tanggal } from '../format';
import Dialog from './Dialog';
import Pesan from './Pesan';

export default function DialogPresensi({ detail, urlFoto, onTutup }) {
  const [foto, setFoto] = useState(null);
  const [error, setError] = useState('');

  async function lihat(p) {
    setError('');
    try {
      setFoto({ id: p.id_presensi, url: (await api.get(urlFoto(p))).url });
    } catch (e) {
      setFoto(null);
      setError(e.message);
    }
  }

  return (
    <Dialog kelas="lebar" judul={`Foto presensi · ${detail.nama}`} onTutup={onTutup}
      aksi={<button type="button" className="tombol" onClick={onTutup}>Tutup</button>}>
      <p className="redup">{detail.praktikum} · {detail.kelas}</p>
      <Pesan jenis="error">{error}</Pesan>
      <div className="tabel-bungkus">
        <table className="tabel">
          <thead><tr><th>Tanggal</th><th>Modul</th><th>Jam masuk</th><th>Jam keluar</th><th>Foto</th></tr></thead>
          <tbody>
            {detail.data.length === 0 && <tr><td colSpan={5} className="redup">Belum ada presensi.</td></tr>}
            {detail.data.map((p) => (
              <tr key={p.id_presensi}>
                <td className="nowrap">{tanggal(p.tanggal)}</td><td>{p.modul}</td><td>{p.jam_masuk}</td><td>{p.jam_keluar}</td>
                <td><button type="button" className={`tombol tombol-kecil ${foto?.id === p.id_presensi ? 'tombol-utama' : ''}`} onClick={() => lihat(p)}>Lihat</button></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {foto && <img className="foto-dialog" src={foto.url} alt="Foto kegiatan" />}
    </Dialog>
  );
}
