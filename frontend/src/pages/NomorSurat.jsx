import { useEffect, useState } from 'react';
import { api } from '../api';
import Pesan from '../components/Pesan';
import Skeleton from '../components/Skeleton';

/**
 * UC10 Mengisi nomor surat sertifikat (wireframe 10). Kolom status penilaian, status sertifikat.
 */
export default function NomorSurat() {
  const [data, setData] = useState(null);
  const [nomor, setNomor] = useState('');
  const [galat, setGalat] = useState('');
  const [pesan, setPesan] = useState({ jenis: '', teks: '' });
  const [proses, setProses] = useState(false);

  useEffect(() => {
    api.get('/sertifikat/nomor-surat')
      .then((r) => { setData(r); setNomor(r.nomor_surat ?? ''); })
      .catch((e) => setPesan({ jenis: 'galat', teks: e.message }));
  }, []);

  async function simpan(e) {
    e.preventDefault();
    if (!nomor.trim()) {
      setGalat('Nomor surat wajib diisi.');
      setPesan({ jenis: 'galat', teks: 'Nomor surat wajib diisi.' });
      return;
    }
    setProses(true);
    try {
      const r = await api.post('/sertifikat/nomor-surat', { nomor_surat: nomor });
      setData(r);
      setNomor(r.nomor_surat);
      setGalat('');
      setPesan({ jenis: 'sukses', teks: r.message });
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
      <Pesan jenis={pesan.jenis} onTutup={() => setPesan({ jenis: '', teks: '' })}>{pesan.teks}</Pesan>

      {!data && !galat && !pesan.teks ? <Skeleton baris={3} /> : (
      <form className="kartu form-presensi" onSubmit={simpan} noValidate>
        <h2>Nomor Surat Sertifikat</h2>
        <label className="bidang">
          <span>Periode (terisi otomatis)</span>
          <input value={data?.periode ?? ''} readOnly />
        </label>
        <label className="bidang">
          <span>Nomor Surat</span>
          <input value={nomor} maxLength={50} onChange={(e) => setNomor(e.target.value)} aria-invalid={Boolean(galat)} />
          {galat && <small className="galat">{galat}</small>}
        </label>
        <div className="aksi">
          <button className="tombol tombol-utama" disabled={proses || !data}>{proses ? 'Menyimpan…' : 'Simpan'}</button>
        </div>
      </form>
      )}

      {data?.nomor_surat && (
        <section className="kartu">
          <h2>Daftar Asisten</h2>
          {data.asisten.length === 0 ? (
            <p className="redup">Belum ada asisten yang ditetapkan pada periode ini.</p>
          ) : (
            <div className="tabel-bungkus">
              <table className="tabel">
                <thead><tr><th>No</th><th>Nama Asisten</th><th>NIM</th><th>Praktikum</th><th>Kelas</th></tr></thead>
                <tbody>
                  {data.asisten.map((a, i) => (
                    <tr key={`${a.id_asisten}-${a.praktikum}`}>
                      <td>{i + 1}</td><td>{a.nama}</td><td>{a.nim}</td><td>{a.praktikum}</td><td>{a.kelas}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      )}
    </>
  );
}
