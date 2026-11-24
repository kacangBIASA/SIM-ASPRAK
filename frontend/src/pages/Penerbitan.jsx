import { useState } from 'react';
import { api } from '../api';
import { useDaftar } from '../hooks';
import Halaman from '../components/Halaman';
import Ikon from '../components/Ikon';
import ThUrut from '../components/ThUrut';
import KotakCari from '../components/KotakCari';
import Pesan from '../components/Pesan';
import { BarisSkel, Skel } from '../components/Skeleton';

const PENILAIAN = { belum: ['Belum dinilai', ''], draft: ['Draft', 'lencana-kuning'], terkirim: ['Terkirim', 'lencana-hijau'] };
const STATUS = { terbit: ['Terbit', { sertifikat: 'terbit' }], belum: ['Belum terbit', { sertifikat: 'belum' }] };
const SYARAT = [['nomor_surat', 'Nomor surat terisi'], ['tanda_tangan', 'Tanda tangan digital terunggah'], ['stempel', 'Stempel digital terunggah'], ['penilaian_terkirim', 'Penilaian asisten terkirim']];

/* UC09 Menerbitkan sertifikat (US12, wireframe 7): hanya untuk penilaian terkirim dan bila nomor surat, tanda tangan, serta stempel periode terisi. */
export default function Penerbitan() {
  const [status, setStatus] = useState('');
  const [penilaian, setPenilaian] = useState('');
  const [pesan, setPesan] = useState({ jenis: '', teks: '' });
  const [proses, setProses] = useState('');
  const { data, error, muat, cari, paging, urut } = useDaftar('/sertifikat/penerbitan', { ...(STATUS[status]?.[1] ?? {}), ...(penilaian && { penilaian }) }, 5);
  const syarat = data?.syarat;

  async function terbitkan(b) {
    setProses(`${b.id_kelas}-${b.id_asisten}`);
    setPesan({ jenis: '', teks: '' });
    try {
      const r = await api.post(`/sertifikat/terbitkan/${b.id_kelas}/${b.id_asisten}`);
      setPesan({ jenis: 'sukses', teks: r.message });
      muat();
    } catch (err) {
      setPesan({ jenis: 'error', teks: err.message });
    } finally {
      setProses('');
    }
  }

  async function lihat(b) {
    const jendela = window.open('', '_blank'); // dibuka sebelum await agar tidak diblokir peramban
    try {
      const { url } = await api.get(`/sertifikat/${b.id_sertifikat}/tautan`);
      if (jendela) { jendela.opener = null; jendela.location.href = url; }
    } catch (err) {
      jendela?.close();
      setPesan({ jenis: 'error', teks: err.message });
    }
  }

  return (
    <>
      <Pesan jenis="error">{error}</Pesan>
      <Pesan jenis={pesan.jenis || 'info'} onTutup={() => setPesan({ jenis: '', teks: '' })}>{pesan.teks}</Pesan>

      <section className="kartu">
        <div className="kepala-kartu">
          <div>
            <h2>Syarat penerbitan</h2>
            <p className="redup kecil">Seluruh syarat harus terpenuhi sebelum sertifikat dapat diterbitkan.</p>
          </div>
          {syarat && <span className={`lencana ${syarat.lengkap ? 'lencana-hijau' : 'lencana-kuning'}`}>{syarat.lengkap ? 'Lengkap' : 'Belum lengkap'}</span>}
        </div>
        <ul className="syarat">
          {SYARAT.map(([k, label]) => (
            <li key={k}>{syarat ? <span className={`centang ${syarat[k] ? '' : 'belum'}`}><Ikon nama={syarat[k] ? 'centang' : 'info'} ukuran={12} /></span> : <Skel l={20} t={20} bulat />}{label}</li>
          ))}
        </ul>
      </section>

      <section className="kartu">
        <h2>Daftar asisten</h2>
        <p className="redup kecil">Sertifikat hanya dapat diterbitkan untuk asisten dengan penilaian terkirim.</p>
        <div className="alat-tabel">
          <KotakCari {...cari} placeholder="Cari NIM atau nama asisten…" />
          <label className="bidang">
            <span>Status penilaian</span>
            <select value={penilaian} onChange={(e) => setPenilaian(e.target.value)}>
              <option value="">Semua status</option>
              {Object.entries(PENILAIAN).map(([k, [label]]) => <option key={k} value={k}>{label}</option>)}
            </select>
          </label>
          <label className="bidang">
            <span>Status sertifikat</span>
            <select value={status} onChange={(e) => setStatus(e.target.value)}>
              <option value="">Semua status</option>
              {Object.entries(STATUS).map(([k, [label]]) => <option key={k} value={k}>{label}</option>)}
            </select>
          </label>
        </div>
        <div className="tabel-bungkus">
          <table className="tabel tabel-penerbitan">
            <thead><tr><th style={{ width: 56 }}>No</th><ThUrut k="nim" {...urut} style={{ width: 120 }}>NIM</ThUrut><ThUrut k="nama" {...urut}>Nama asisten</ThUrut><ThUrut k="praktikum" {...urut} style={{ width: 200 }}>Praktikum</ThUrut><ThUrut k="prodi" {...urut} style={{ width: 160 }}>Prodi</ThUrut><ThUrut k="penilaian" {...urut} style={{ width: 150 }}>Penilaian</ThUrut><ThUrut k="sertifikat" {...urut} style={{ width: 130 }}>Sertifikat</ThUrut><th style={{ width: 130 }}>Aksi</th></tr></thead>
            <tbody>
                {!data && !error && <BarisSkel kolom={8} />}
              {data?.baris.length === 0 && <tr><td colSpan={8} className="redup">Tidak ada asisten yang cocok pada periode ini.</td></tr>}
              {data?.baris.map((b, i) => (
                <tr key={`${b.id_kelas}-${b.id_asisten}`}>
                  <td className="redup">{data.halaman.dari + i}</td>
                  <td>{b.nim}</td>
                  <td><b>{b.nama}</b></td>
                  <td>{b.praktikum}</td>
                  <td>{b.prodi}</td>
                  <td><span className={`lencana ${PENILAIAN[b.penilaian][1]}`}>{PENILAIAN[b.penilaian][0]}</span></td>
                  <td><span className={`lencana ${b.id_sertifikat ? 'lencana-hijau' : ''}`}>{b.id_sertifikat ? 'Terbit' : 'Belum terbit'}</span></td>
                  <td>
                    {b.id_sertifikat ? <button type="button" className="tombol tombol-kecil" onClick={() => lihat(b)}>Lihat</button>
                      : b.penilaian === 'terkirim' ? (
                        <button type="button" className="tombol tombol-kecil tombol-utama" disabled={!syarat?.lengkap || proses !== ''} title={syarat?.lengkap ? undefined : 'Lengkapi syarat penerbitan terlebih dahulu'}
                          onClick={() => terbitkan(b)}>{proses === `${b.id_kelas}-${b.id_asisten}` ? 'Menerbitkan…' : 'Terbitkan'}</button>
                      ) : <span className="redup">–</span>}
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
