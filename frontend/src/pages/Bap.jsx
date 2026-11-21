import { useEffect, useState } from 'react';
import { api } from '../api';
import Pesan from '../components/Pesan';
import Ikon from '../components/Ikon';
import { Skel } from '../components/Skeleton';
import logo from '../components/logo/Logo Vertikal Telkom University.png';

const IDENTITAS = [
  ['Nama', 'nama'], ['NIM', 'nim'], ['Prodi', 'prodi'], ['Kode MK', 'kode_mk'],
  ['Nama MK', 'nama_mk'], ['Bulan', 'bulan'], ['Total Jam', 'total_jam'],
];

/* UC03 Mengunduh BAP (wireframe 6). */
export default function Bap() {
  const [pilihan, setPilihan] = useState(null);
  const [idKelas, setIdKelas] = useState('');
  const [rentang, setRentang] = useState({ tanggal_mulai: '', tanggal_selesai: '' });
  const [bap, setBap] = useState(null);
  const [pesan, setPesan] = useState({ jenis: '', teks: '' });
  const [proses, setProses] = useState(false);

  // Basic course langkah 2: pilihan praktikum dan periode BAP (KF-05).
  useEffect(() => {
    api.get('/bap/pilihan').then(setPilihan).catch((e) => setPesan({ jenis: 'error', teks: e.message }));
  }, []);

  function pilihPraktikum(e) {
    setIdKelas(e.target.value);
  }

  // Langkah 3–5: susun BAP lalu tampilkan pratinjau (KF-06).
  async function tampilkan(e) {
    e.preventDefault();
    setBap(null);
    if (!idKelas || !rentang.tanggal_mulai || !rentang.tanggal_selesai) {
      setPesan({ jenis: 'error', teks: !idKelas ? 'Praktikum wajib dipilih.' : 'Tanggal mulai dan selesai wajib diisi.' });
      return;
    }
    setProses(true);
    try {
      const r = await api.post('/bap', { id_kelas: idKelas, ...rentang });
      setBap(r.data);
      setPesan(r.peringatan ? { jenis: 'info', teks: r.peringatan } : { jenis: '', teks: '' });
    } catch (err) {
      setPesan({ jenis: 'error', teks: err.message });
    } finally {
      setProses(false);
    }
  }

  // Langkah 6–7: unduh PDF (KF-07).
  async function unduh() {
    try {
      await api.unduh(`/bap/${bap.id_bap}/tautan`);
    } catch (err) {
      setPesan({ jenis: 'error', teks: err.message });
    }
  }

  return (
    <>
      <form className="kartu" onSubmit={tampilkan} noValidate>
        <div>
          <h2>Pilih data BAP</h2>
          <p className="redup">BAP disusun otomatis dari data presensi pada rentang tanggal yang dipilih (boleh lintas bulan, sesuai batas dari laboran).</p>
        </div>
        <div className="baris-form">
        <label className="bidang bidang-lebar">
          <span>Praktikum (kelas yang ditugaskan)</span>
          {!pilihan && !pesan.teks ? <Skel t={44} kelas="skel-kotak" /> : <select value={idKelas} onChange={pilihPraktikum} disabled={!pilihan?.praktikum.length}>
            <option value="">{pilihan?.praktikum.length === 0 ? 'Anda belum ditugaskan pada kelas praktikum' : '— Pilih praktikum —'}</option>
            {pilihan?.praktikum.map((p) => <option key={p.id_kelas} value={p.id_kelas}>{p.label}</option>)}
          </select>}
        </label>
        <label className="bidang">
          <span>Dari tanggal</span>
          <input type="date" value={rentang.tanggal_mulai} onChange={(e) => setRentang({ ...rentang, tanggal_mulai: e.target.value })} />
        </label>
        <label className="bidang">
          <span>Sampai tanggal</span>
          <input type="date" value={rentang.tanggal_selesai} min={rentang.tanggal_mulai} onChange={(e) => setRentang({ ...rentang, tanggal_selesai: e.target.value })} />
        </label>
        <button className="tombol tombol-utama" disabled={proses || !pilihan?.praktikum.length}>
          {proses ? 'Memproses…' : 'Tampilkan'}
        </button>
        </div>
      </form>

      <Pesan jenis={pesan.jenis} onTutup={() => setPesan({ jenis: '', teks: '' })}>{pesan.teks}</Pesan>

      {bap && (
        <section className="kartu">
          <div className="kepala-kartu">
            <div>
              <h2>Pratinjau BAP</h2>
              <p className="redup">Periksa isi BAP sebelum diunduh.</p>
            </div>
            <button className="tombol tombol-utama" onClick={unduh}><Ikon nama="unduh" ukuran={16} /> Unduh BAP (PDF)</button>
          </div>

          <div className="dokumen-bap">
            <div className="dokumen-kepala">
              <div>
                <strong className="dokumen-judul">BERITA ACARA PEKERJAAN DAN KEHADIRAN<br />ASISTEN PRAKTIKUM</strong>
                <table className="dokumen-identitas">
                  <tbody>
                    {IDENTITAS.map(([label, k]) => (
                      <tr key={k}><th>{label.toUpperCase()}</th><td>: {bap[k] ?? '-'}</td></tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <div className="dokumen-lab">
                <img className="dokumen-logo" src={logo} alt="Logo Telkom University" /><br />Laboratorium<br />Universitas Telkom Surabaya
              </div>
            </div>

            <div className="tabel-bungkus">
              <table className="tabel tabel-bergaris">
                <thead>
                  <tr>
                    <th>No</th><th>Hari</th><th>Tanggal</th><th>Kelas</th><th>Jam Masuk</th>
                    <th>Jam Keluar</th><th>Jumlah Jam</th><th>Modul Praktikum</th><th>Paraf</th>
                  </tr>
                </thead>
                <tbody>
                  {bap.baris.map((b, i) => (
                    <tr key={i}>
                      <td>{i + 1}</td><td>{b.hari}</td><td className="nowrap">{b.tanggal}</td><td>{b.kelas}</td>
                      <td>{b.jam_masuk}</td><td>{b.jam_keluar}</td><td className="nowrap">{b.jumlah_jam}</td>
                      <td>{b.modul}</td><td />
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
