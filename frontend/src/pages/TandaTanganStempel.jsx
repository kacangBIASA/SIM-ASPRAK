import { useEffect, useRef, useState } from 'react';
import { api } from '../api';
import Ikon from '../components/Ikon';
import Pesan from '../components/Pesan';
import { Skel } from '../components/Skeleton';

const TIPE = ['image/png', 'image/jpeg'];
const KARTU = [
  ['tanda_tangan', 'Tanda tangan digital direktur', 'tanda_tangan_info', 'tanda-tangan'],
  ['stempel', 'Stempel digital', 'stempel_info', 'stempel'],
];

/* UC09 Tanda tangan dan stempel digital direktur (US10, wireframe 5): satu kartu per berkas, unggah langsung saat berkas dipilih. */
export default function TandaTanganStempel({ onAturPosisi }) {
  const [data, setData] = useState(null);
  const [pesan, setPesan] = useState({ jenis: '', teks: '' });
  const [proses, setProses] = useState('');
  const [error, setError] = useState({});
  const input = useRef({});

  useEffect(() => { api.get('/sertifikat/pengesahan').then(setData).catch((e) => setPesan({ jenis: 'error', teks: e.message })); }, []);

  async function kirim(kunci, fd) {
    setProses(kunci);
    try {
      const r = await api.post('/sertifikat/pengesahan', fd);
      setData(r);
      setError({});
      setPesan({ jenis: 'sukses', teks: r.message });
    } catch (err) {
      setError({ [kunci]: err.errors?.[kunci]?.[0] ?? '' });
      setPesan({ jenis: 'error', teks: err.errors?.[kunci]?.[0] ?? err.message });
    } finally {
      setProses('');
    }
  }

  function pilih(kunci, f) {
    if (!f) return;
    const salah = !TIPE.includes(f.type) ? 'Format gambar harus PNG atau JPG.' : f.size > data.maks_kb * 1024 ? 'Ukuran gambar maksimal 2 MB.' : '';
    if (salah) { setError({ [kunci]: salah }); setPesan({ jenis: 'error', teks: salah }); return; }
    const fd = new FormData();
    fd.append(kunci, f);
    kirim(kunci, fd);
  }

  return (
    <>
      <Pesan jenis={pesan.jenis || 'error'} onTutup={() => setPesan({ jenis: '', teks: '' })}>{pesan.teks}</Pesan>
      <div className="dua-kolom">
        {KARTU.map(([k, judul, kunciInfo, nama]) => {
          const info = data?.[kunciInfo];
          return (
            <section className="kartu kartu-berkas" key={k}>
              <div className="kepala-kartu" style={{ marginBottom: 0 }}>
                <div>
                  <h2>{judul}</h2>
                  <p className="redup kecil">Format PNG atau JPG, maksimal 2 MB. Latar putih dihapus otomatis.</p>
                </div>
                {data ? <span className={`lencana ${info ? 'lencana-hijau' : 'lencana-kuning'}`}>{info ? 'Lengkap' : 'Belum diunggah'}</span> : <Skel l={90} t={22} />}
              </div>
              <div className="pratinjau-berkas">
                {!data ? <Skel t={110} kelas="skel-kotak" /> : data[k] ? <img src={data[k]} alt={`${judul} tersimpan`} className={k} /> : <span className="redup kecil">Belum ada berkas</span>}
              </div>
              {info && (
                <div className="info-berkas">
                  <Ikon nama="foto" />
                  <div>
                    <b>{nama}.{info.jenis.toLowerCase()} · {info.kb} KB</b>
                    <small>{info.jenis} · diunggah {info.tanggal}</small>
                  </div>
                </div>
              )}
              {error[k] && <small className="error">{error[k]}</small>}
              <div className="aksi" style={{ justifyContent: 'flex-start' }}>
                <input ref={(el) => { input.current[k] = el; }} type="file" accept="image/png,image/jpeg" hidden aria-label={judul}
                  onChange={(e) => { pilih(k, e.target.files?.[0]); e.target.value = ''; }} />
                <button type="button" className="tombol" disabled={proses === k || !data} onClick={() => input.current[k].click()}>
                  <Ikon nama="unggah" />{proses === k ? 'Mengunggah…' : info ? 'Ganti berkas' : 'Unggah berkas'}
                </button>
                {k === 'stempel' && info && (
                  <button type="button" className="tombol tombol-utama" onClick={onAturPosisi}><Ikon nama="geser" />Atur posisi</button>
                )}
              </div>
            </section>
          );
        })}
      </div>

      <Pesan jenis="info">{data && `Tanda tangan dan stempel berlaku untuk semua sertifikat pada periode ${data.periode}.`}</Pesan>
    </>
  );
}
