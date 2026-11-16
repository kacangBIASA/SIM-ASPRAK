import { useEffect, useState } from 'react';
import { api } from '../api';
import { useDaftar } from '../hooks';
import ThUrut from '../components/ThUrut';
import Halaman from '../components/Halaman';
import KotakCari from '../components/KotakCari';
import Pesan from '../components/Pesan';

/* UC10 Mengisi nomor surat sertifikat (US11, wireframe 10): satu nomor per periode, beserta daftar asisten periode itu. */
export default function NomorSurat() {
  const [nomor, setNomor] = useState('');
  const [galat, setGalat] = useState('');
  const [pesan, setPesan] = useState({ jenis: '', teks: '' });
  const [proses, setProses] = useState(false);
  const [praktikum, setPraktikum] = useState('');
  const [periode, setPeriode] = useState('');
  const { data, setData, galat: galatMuat, cari, paging, urut } = useDaftar('/sertifikat/nomor-surat', { praktikum, periode });

  useEffect(() => { if (data) setNomor((n) => n || (data.nomor_surat ?? '')); }, [data]);

  async function simpan(e) {
    e.preventDefault();
    if (!nomor.trim()) {
      setGalat('Nomor surat wajib diisi.');
      setPesan({ jenis: 'galat', teks: 'Nomor surat wajib diisi.' });
      return;
    }
    setProses(true);
    try {
      const r = await api.post('/sertifikat/nomor-surat', { nomor_surat: nomor, periode: data.id_periode });
      setData(r);
      setPesan({ jenis: 'sukses', teks: r.message });
      setNomor(r.nomor_surat);
      setGalat('');
    } catch (err) {
      const teks = err.errors?.nomor_surat?.[0] ?? err.message;
      setGalat(err.errors?.nomor_surat?.[0] ?? '');
      setPesan({ jenis: 'galat', teks });
    } finally {
      setProses(false);
    }
  }

  return (
    <>
      {data && (
        <label className="bidang" style={{ maxWidth: 320 }}>
          <span>Periode</span>
          <select value={periode} disabled={data.periode_opsi.length < 2} onChange={(e) => { setPeriode(e.target.value); setPraktikum(''); setNomor(''); setGalat(''); }}>
            {data.periode_opsi.map((o) => <option key={o.id} value={o.berjalan ? '' : o.id}>{o.nama}</option>)}
          </select>
        </label>
      )}
      <Pesan jenis={pesan.jenis || 'galat'} onTutup={() => setPesan({ jenis: '', teks: '' })}>{pesan.teks || galatMuat}</Pesan>

      <form className="kartu form-presensi" onSubmit={simpan} noValidate>
        <div className="kepala-kartu" style={{ marginBottom: 0 }}>
          <div>
            <h2>Nomor surat sertifikat</h2>
            <p className="redup">Nomor surat berlaku untuk semua sertifikat pada periode yang dipilih.</p>
          </div>
          {data?.nomor_surat && <span className="lencana lencana-hijau">Tersimpan</span>}
        </div>
        <label className="bidang">
          <span>Periode</span>
          <input value={data?.periode ?? ''} readOnly />
        </label>
        <label className="bidang">
          <span>Nomor surat</span>
          <input value={nomor} maxLength={50} onChange={(e) => setNomor(e.target.value)} aria-invalid={Boolean(galat)} />
          {galat && <small className="galat">{galat}</small>}
        </label>
        <p className="redup kecil" style={{ margin: 0 }}>Nomor surat ditulis sesuai format surat keluar kampus dan tampil pada setiap sertifikat.</p>
        <div className="aksi aksi-garis">
          <button className="tombol tombol-utama" disabled={proses || !data}>{proses ? 'Menyimpan…' : 'Simpan nomor surat'}</button>
        </div>
      </form>

      <section className="kartu">
        <h2>Daftar asisten</h2>
        <p className="redup">Asisten yang bertugas pada periode yang dipilih.</p>
        <div className="alat-tabel">
          <KotakCari {...cari} placeholder="Cari NIM atau nama asisten…" />
          <label className="bidang">
            <span>Praktikum</span>
            <select value={praktikum} onChange={(e) => setPraktikum(e.target.value)}>
              <option value="">Semua praktikum</option>
              {data?.praktikum_opsi.map((p) => <option key={p.id} value={p.id}>{p.nama}</option>)}
            </select>
          </label>
        </div>
        {data?.asisten.length === 0 ? (
          <p className="redup">Tidak ada asisten yang cocok pada periode ini.</p>
        ) : (
          <div className="tabel-bungkus">
            <table className="tabel">
              <thead><tr><th>No</th><ThUrut k="nim" {...urut}>NIM</ThUrut><ThUrut k="nama" {...urut}>Nama Asisten</ThUrut><ThUrut k="praktikum" {...urut}>Praktikum</ThUrut><th>Kelas</th></tr></thead>
              <tbody>
                {data?.asisten.map((a, i) => (
                  <tr key={`${a.id_asisten}-${a.praktikum}-${a.kelas}`}>
                    <td>{data.halaman.dari + i}</td>
                    <td>{a.nim}</td>
                    <td><b>{a.nama}</b></td>
                    <td>{a.praktikum}</td>
                    <td>{a.kelas}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        <Halaman {...paging} />
      </section>
    </>
  );
}
