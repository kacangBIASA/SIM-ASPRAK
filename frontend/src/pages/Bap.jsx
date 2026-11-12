import { useEffect, useState } from 'react';
import { api } from '../api';
import Pesan from '../components/Pesan';
import Skeleton from '../components/Skeleton';
import logo from '../components/logo/Logo Vertikal Telkom University.png';

const TANPA_PESAN = { jenis: '', teks: '' };

/** UC03 Mengunduh BAP (wireframe Berita Acara Pekerjaan). */
export default function Bap() {
  const [pilihan, setPilihan] = useState(null);
  const [isian, setIsian] = useState({ id_kelas: '', id_periode_bap: '' });
  const [bap, setBap] = useState(null);
  const [pesan, setPesan] = useState(TANPA_PESAN);
  const [proses, setProses] = useState(false);

  useEffect(() => {
    api.get('/bap/pilihan').then(setPilihan).catch((e) => setPesan({ jenis: 'galat', teks: e.message }));
  }, []);

  const ubah = (k) => (e) => { setIsian((s) => ({ ...s, [k]: e.target.value })); setBap(null); };

  async function tampilkan(e) {
    e.preventDefault();
    setPesan(TANPA_PESAN);
    setBap(null);
    if (!isian.id_kelas || !isian.id_periode_bap) {
      setPesan({ jenis: 'galat', teks: 'Praktikum dan periode BAP wajib dipilih.' });
      return;
    }
    setProses(true);
    try {
      setBap(await api.post('/bap', isian));
    } catch (err) {
      setPesan({ jenis: 'galat', teks: err.errors?.id_periode_bap?.[0] ?? err.errors?.id_kelas?.[0] ?? err.message });
    } finally { setProses(false); }
  }

  async function unduh() {
    try { await api.unduh(`/bap/${bap.id_bap}/unduh`); } catch (err) { setPesan({ jenis: 'galat', teks: err.message }); }
  }

  return (
    <>
      <form className="kartu baris-form" onSubmit={tampilkan} noValidate>
        <label className="bidang bidang-lebar">
          <span>Praktikum (Kelas yang Ditugaskan)</span>
          <select value={isian.id_kelas} onChange={ubah('id_kelas')}>
            <option value="">— Pilih praktikum —</option>
            {pilihan?.praktikum.map((p) => (
              <option key={p.id_kelas} value={p.id_kelas}>{p.kode_praktikum} · {p.nama_praktikum} ({p.kode_kelas})</option>
            ))}
          </select>
        </label>
        <label className="bidang bidang-lebar">
          <span>Periode BAP</span>
          <select value={isian.id_periode_bap} onChange={ubah('id_periode_bap')}>
            <option value="">— Pilih periode —</option>
            {pilihan?.periode_bap.map((p) => <option key={p.id_periode_bap} value={p.id_periode_bap}>{p.nama_periode_bap}</option>)}
          </select>
        </label>
        <button className="tombol tombol-utama" disabled={!pilihan || proses}>{proses ? 'Menyusun…' : 'Tampilkan'}</button>
      </form>

      <Pesan jenis={pesan.jenis} onTutup={() => setPesan(TANPA_PESAN)}>{pesan.teks}</Pesan>

      {!pilihan && pesan.jenis !== 'galat' && <Skeleton baris={3} />}
      {pilihan?.praktikum.length === 0 && <p className="redup">Anda belum ditugaskan pada kelas praktikum pada periode ini.</p>}

      {bap && (
        <section className="kartu">
          <div className="baris-judul">
            <h2>Pratinjau BAP</h2>
            <button className="tombol" onClick={unduh}>Unduh BAP</button>
          </div>
          <div className="dokumen-bap">
            <div className="dokumen-kepala">
              <div>
                <strong className="dokumen-judul">BERITA ACARA PEKERJAAN DAN KEHADIRAN<br />ASISTEN PRAKTIKUM</strong>
                <table className="dokumen-identitas">
                  <tbody>
                    {[['NAMA', bap.nama], ['NIM', bap.nim], ['BULAN', bap.bulan], ['PRODI', bap.prodi], ['KODE MK', bap.kode_mk],
                      ['NAMA MK', bap.nama_mk], ['TAHUN', bap.tahun], ['TOTAL JAM', bap.total_jam]].map(([k, v]) => (
                      <tr key={k}><th>{k}</th><td>: {v}</td></tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <div className="dokumen-lab">
                <img className="dokumen-logo" src={logo} alt="Logo Telkom University" /><br />
                Laboratorium<br />Universitas Telkom Surabaya
              </div>
            </div>
            <div className="tabel-bungkus">
              <table className="tabel tabel-bergaris">
                <thead>
                  <tr><th>No</th><th>Hari</th><th>Tanggal</th><th>Kelas</th><th>Jam Masuk</th><th>Jam Keluar</th><th>Jumlah Jam</th><th>Modul Praktikum</th><th>Paraf</th></tr>
                </thead>
                <tbody>
                  {bap.baris.map((b, i) => (
                    <tr key={i}>
                      <td>{i + 1}</td><td>{b.hari}</td><td>{b.tanggal}</td><td>{b.kelas}</td><td>{b.jam_masuk}</td>
                      <td>{b.jam_keluar}</td><td>{b.jumlah_jam}</td><td>{b.modul}</td><td />
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <div className="dokumen-ttd">
              Surabaya, {bap.tanggal_dibuat}<br />Koordinator Dosen Mata Kuliah
              <div className="dokumen-ruang-ttd" />
              <span className="dokumen-nama-ttd">{bap.dosen}</span>
            </div>
          </div>
        </section>
      )}
    </>
  );
}
