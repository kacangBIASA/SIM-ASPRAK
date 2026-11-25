import { useEffect, useState } from 'react';
import { api } from '../api';
import Ikon from '../components/Ikon';
import Pesan from '../components/Pesan';
import { FormSkel } from '../components/Skeleton';

/* UC10 Mengisi nomor surat sertifikat (US11, wireframe 10): satu nomor per periode */
export default function NomorSurat() {
  const [nomor, setNomor] = useState('');
  const [penandatangan, setPenandatangan] = useState({ nama_penandatangan: '', nip_penandatangan: '' });
  const [error, setError] = useState('');
  const [pesan, setPesan] = useState({ jenis: '', teks: '' });
  const [proses, setProses] = useState(false);
  const [data, setData] = useState(null);
  const [errorMuat, setErrorMuat] = useState('');

  useEffect(() => { api.get('/sertifikat/nomor-surat').then(setData).catch((e) => setErrorMuat(e.message)); }, []);

  useEffect(() => {
    if (!data) return;
    setNomor((n) => n || (data.nomor_surat ?? ''));
    setPenandatangan((p) => ({ nama_penandatangan: p.nama_penandatangan || (data.nama_penandatangan ?? ''), nip_penandatangan: p.nip_penandatangan || (data.nip_penandatangan ?? '') }));
  }, [data]);

  async function simpan(e) {
    e.preventDefault();
    if (!nomor.trim()) {
      setError('Nomor surat wajib diisi.');
      setPesan({ jenis: 'error', teks: 'Nomor surat wajib diisi.' });
      return;
    }
    setProses(true);
    try {
      const r = await api.post('/sertifikat/nomor-surat', { nomor_surat: nomor, ...penandatangan, periode: data.id_periode });
      setData(r);
      setPesan({ jenis: 'sukses', teks: r.message });
      setNomor(r.nomor_surat);
      setPenandatangan({ nama_penandatangan: r.nama_penandatangan ?? '', nip_penandatangan: r.nip_penandatangan ?? '' });
      setError('');
    } catch (err) {
      const teks = err.errors?.nomor_surat?.[0] ?? Object.values(err.errors ?? {})[0]?.[0] ?? err.message;
      setError(err.errors?.nomor_surat?.[0] ?? '');
      setPesan({ jenis: 'error', teks });
    } finally {
      setProses(false);
    }
  }

  if (!data && !errorMuat) return <FormSkel bidang={4} />;

  return (
    <>
      <Pesan jenis={pesan.jenis || 'error'} onTutup={() => setPesan({ jenis: '', teks: '' })}>{pesan.teks || errorMuat}</Pesan>

      <form className="kartu form-lebar" onSubmit={simpan} noValidate>
        <div className="kepala-kartu" style={{ marginBottom: 0 }}>
          <div>
            <h2>Nomor surat dan penandatangan</h2>
            <p className="redup kecil">Data ini berlaku untuk semua sertifikat pada periode aktif.</p>
          </div>
          {data?.nomor_surat && <span className="lencana lencana-hijau">Tersimpan</span>}
        </div>
        <fieldset className="bagian-form">
          <legend>Nomor surat</legend>
          <div className="baris-penandatangan">
            <label className="bidang">
              <span>Nomor surat <i className="wajib" aria-hidden="true">*</i></span>
              <input value={nomor} maxLength={50} placeholder="Contoh: 045/LAB-SI/2027" onChange={(e) => setNomor(e.target.value)} aria-invalid={Boolean(error)} />
              {error ? <small className="error">{error}</small> : <small className="petunjuk">Tercetak pada setiap sertifikat. Maksimal 50 karakter.</small>}
            </label>
            <label className="bidang">
              <span>Periode</span>
              <input value={data?.periode ?? ''} readOnly />
              <small className="petunjuk">Terisi otomatis.</small>
            </label>
          </div>
        </fieldset>

        <fieldset className="bagian-form">
          <legend>Penandatangan</legend>
          <div className="baris-penandatangan">
            <label className="bidang">
              <span>Nama penandatangan</span>
              <input value={penandatangan.nama_penandatangan} maxLength={100} placeholder="Contoh: Nama Lengkap, S.T., M.T." onChange={(e) => setPenandatangan((p) => ({ ...p, nama_penandatangan: e.target.value }))} />
              <small className="petunjuk">Beserta gelar, seperti yang tercetak di bawah tanda tangan.</small>
            </label>
            <label className="bidang">
              <span>NIP penandatangan</span>
              <input value={penandatangan.nip_penandatangan} maxLength={30} placeholder="Contoh: 12345678" onChange={(e) => setPenandatangan((p) => ({ ...p, nip_penandatangan: e.target.value }))} />
              <small className="petunjuk">Maksimal 30 karakter.</small>
            </label>
          </div>
        </fieldset>

        <p className="catatan-info"><Ikon nama="info" ukuran={16} />Nama dan NIP penandatangan tampil pada bagian tanda tangan setiap sertifikat.</p>
        <div className="aksi aksi-garis">
          <button className="tombol tombol-utama" disabled={proses || !data}>{proses ? 'Menyimpan…' : 'Simpan data sertifikat'}</button>
        </div>
      </form>
    </>
  );
}
