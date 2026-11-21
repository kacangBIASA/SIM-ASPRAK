import { useState } from 'react';
import { api } from '../api';
import { useDaftar } from '../hooks';
import Halaman from '../components/Halaman';
import KotakCari from '../components/KotakCari';
import Pesan from '../components/Pesan';
import ThUrut from '../components/ThUrut';
import { BarisSkel } from '../components/Skeleton';

/* UC11 Mengunduh sertifikat (US13, wireframe 1): daftar sertifikat asisten pada semua periode; PDF diunduh lewat tautan bertanda tangan. */
export default function SertifikatSaya() {
  const [pesan, setPesan] = useState('');
  const [proses, setProses] = useState(null);
  const [sertifikat, setSertifikat] = useState('');
  const { data, error, cari, paging, urut } = useDaftar('/sertifikat-saya', { sertifikat }, 5);

  async function unduh(b) {
    setProses(b.id_sertifikat);
    setPesan('');
    try {
      await api.unduh(`/sertifikat-saya/${b.id_sertifikat}/tautan`);
    } catch (err) {
      setPesan(err.message);
    } finally {
      setProses(null);
    }
  }

  return (
    <>
      <Pesan jenis="error">{error || pesan}</Pesan>

      <section className="kartu">
        <h2>Sertifikat saya</h2>
        <p className="redup kecil">Sertifikat dapat diunduh setelah diterbitkan oleh Laboran.</p>
        <div className="alat-tabel">
          <KotakCari {...cari} placeholder="Cari praktikum atau periode…" />
          <label className="bidang">
            <span>Status sertifikat</span>
            <select value={sertifikat} onChange={(e) => setSertifikat(e.target.value)}>
              <option value="">Semua status</option>
              <option value="terbit">Terbit</option>
              <option value="belum">Belum terbit</option>
            </select>
          </label>
        </div>
        <div className="tabel-bungkus">
          <table className="tabel">
            <thead><tr>
              <th style={{ width: 56 }}>No</th>
              <ThUrut k="periode" {...urut} style={{ width: 160 }}>Periode</ThUrut>
              <ThUrut k="praktikum" {...urut}>Praktikum</ThUrut>
              <ThUrut k="prodi" {...urut}>Prodi</ThUrut>
              <ThUrut k="nomor_surat" {...urut} style={{ width: 210 }}>Nomor surat</ThUrut>
              <ThUrut k="status" {...urut} style={{ width: 130 }}>Status</ThUrut>
              <th style={{ width: 150 }}>Aksi</th>
            </tr></thead>
            <tbody>
                {!data && !error && <BarisSkel kolom={7} />}
              {data?.baris.length === 0 && <tr><td colSpan={7} className="redup">Belum ada sertifikat atau praktikum yang cocok.</td></tr>}
              {data?.baris.map((b, i) => (
                <tr key={`${b.periode}-${b.praktikum}-${b.prodi}`}>
                  <td className="redup">{data.halaman.dari + i}</td>
                  <td>{b.periode}</td>
                  <td><b>{b.praktikum}</b></td>
                  <td>{b.prodi}</td>
                  <td>{b.nomor_surat ?? '–'}</td>
                  <td><span className={`lencana ${b.id_sertifikat ? 'lencana-hijau' : ''}`}>{b.id_sertifikat ? 'Terbit' : 'Belum terbit'}</span></td>
                  <td>
                    {b.id_sertifikat
                      ? <button type="button" className="tombol tombol-kecil tombol-utama" disabled={proses !== null} onClick={() => unduh(b)}>{proses === b.id_sertifikat ? 'Mengunduh…' : 'Unduh PDF'}</button>
                      : <span className="redup">–</span>}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <Halaman {...paging} />
      </section>
    </>
  );
}
