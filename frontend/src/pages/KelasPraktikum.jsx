import { useCallback, useEffect, useState } from 'react';
import { api } from '../api';
import Pesan from '../components/Pesan';
import Skeleton from '../components/Skeleton';

const KOSONG = { kode_praktikum: '', kode_kelas: '', nama_praktikum: '', prodi: '' };
const TANPA_PESAN = { jenis: '', teks: '' };
const galatDari = (err) => Object.fromEntries(Object.entries(err.errors ?? {}).map(([k, v]) => [k, v[0]]));

/** UC05 Mendaftarkan kelas praktikum (wireframe 7–8). */
export default function KelasPraktikum() {
  const [daftar, setDaftar] = useState(null);
  const [tampilan, setTampilan] = useState('daftar');
  const [kelasDipilih, setKelasDipilih] = useState(null);
  const [pesan, setPesan] = useState(TANPA_PESAN);

  const muat = useCallback(() => {
    api.get('/kelas-praktikum').then(setDaftar).catch((e) => setPesan({ jenis: 'galat', teks: e.message }));
  }, []);
  useEffect(muat, [muat]);

  const kembali = (p = TANPA_PESAN) => { setPesan(p); setTampilan('daftar'); muat(); };
  const kotakPesan = <Pesan jenis={pesan.jenis} onTutup={() => setPesan(TANPA_PESAN)}>{pesan.teks}</Pesan>;

  if (tampilan === 'asisten') return <AsistenKelas kelas={kelasDipilih} periode={daftar.periode} onKembali={() => kembali()} />;
  if (tampilan === 'tambah') return <FormKelas daftar={daftar} onBatal={() => kembali()} onTersimpan={kembali} />;

  return (
    <>
      <div className="kartu baris-form">
        <div className="bidang-lebar">
          <small className="redup">PERIODE BERJALAN</small>
          <div><strong>{daftar?.periode ?? <i className="skeleton" style={{ width: '10ch' }} />}</strong></div>
        </div>
        <button className="tombol tombol-utama" onClick={() => { setPesan(TANPA_PESAN); setTampilan('tambah'); }} disabled={!daftar}>
          Tambah Kelas
        </button>
      </div>

      {kotakPesan}

      {!daftar && pesan.jenis !== 'galat' && <Skeleton baris={4} />}

      {daftar && (
        <section className="kartu">
          <h2>Kelas Praktikum yang Diampu</h2>
          {daftar.data.length === 0 ? (
            <p className="redup">Belum ada kelas praktikum pada periode ini. Pilih Tambah Kelas untuk mendaftarkan kelas.</p>
          ) : (
            <div className="tabel-bungkus">
              <table className="tabel">
                <thead>
                  <tr><th>No</th><th>Kode MK</th><th>Mata Kuliah</th><th>Kelas</th><th>Jumlah Asisten</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                  {daftar.data.map((k, i) => (
                    <tr key={k.id_kelas}>
                      <td>{i + 1}</td><td>{k.kode_praktikum}</td><td>{k.nama_praktikum}</td><td>{k.kode_kelas}</td>
                      <td>{k.jumlah_asisten}</td>
                      <td>
                        <button className="tombol" onClick={() => { setPesan(TANPA_PESAN); setKelasDipilih(k); setTampilan('asisten'); }}>
                          Kelola Asisten
                        </button>
                      </td>
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

/** UC05 langkah 4–7 (wireframe 8). */
function FormKelas({ daftar, onBatal, onTersimpan }) {
  const [isian, setIsian] = useState(KOSONG);
  const [galat, setGalat] = useState({});
  const [pesan, setPesan] = useState(TANPA_PESAN);
  const [proses, setProses] = useState(false);

  const ubah = (k) => (e) => setIsian((s) => ({ ...s, [k]: e.target.value }));

  async function simpan(e) {
    e.preventDefault();
    const g = {};
    if (!isian.kode_praktikum.trim()) g.kode_praktikum = 'Kode mata kuliah wajib diisi.';
    if (!isian.kode_kelas.trim()) g.kode_kelas = 'Kode kelas wajib diisi.';
    if (!isian.nama_praktikum.trim()) g.nama_praktikum = 'Nama mata kuliah wajib diisi.';
    if (!isian.prodi) g.prodi = 'Prodi wajib dipilih.';
    setGalat(g);
    if (Object.keys(g).length) { setPesan({ jenis: 'galat', teks: 'Isian wajib diisi.' }); return; } // alternate 6a

    setProses(true);
    try {
      const r = await api.post('/kelas-praktikum', isian);
      onTersimpan({ jenis: 'sukses', teks: r.message });
    } catch (err) {
      setGalat(galatDari(err));
      setPesan({ jenis: 'galat', teks: err.errors?.kode_kelas?.[0] ?? (err.status === 422 ? 'Periksa kembali isian Anda.' : err.message) });
      setProses(false);
    }
  }

  const bidang = (k, label, input) => (
    <label className="bidang">
      <span>{label}</span>
      {input}
      {galat[k] && <small className="galat">{galat[k]}</small>}
    </label>
  );
  const teks = (k, max) => <input value={isian[k]} maxLength={max} onChange={ubah(k)} aria-invalid={Boolean(galat[k])} />;

  return (
    <>
      <Pesan jenis={pesan.jenis} onTutup={() => setPesan(TANPA_PESAN)}>{pesan.teks}</Pesan>
      <form className="kartu form-presensi" onSubmit={simpan} noValidate>
        <h2>Form Pendaftaran Kelas Praktikum</h2>
        <div className="grid-2">
          {bidang('kode_praktikum', 'Kode Mata Kuliah', teks('kode_praktikum', 20))}
          {bidang('kode_kelas', 'Kode Kelas', teks('kode_kelas', 20))}
        </div>
        {bidang('nama_praktikum', 'Nama Mata Kuliah', teks('nama_praktikum', 100))}
        <div className="grid-2">
          {bidang('prodi', 'Prodi', (
            <select value={isian.prodi} onChange={ubah('prodi')} aria-invalid={Boolean(galat.prodi)}>
              <option value="">— Pilih prodi —</option>
              {daftar?.prodi.map((p) => <option key={p}>{p}</option>)}
            </select>
          ))}
          {bidang('periode', 'Periode (terisi otomatis)', <input value={daftar?.periode ?? ''} readOnly />)}
        </div>
        <small className="redup">Jika kode mata kuliah sudah terdaftar pada periode berjalan, kelas digabungkan ke mata kuliah tersebut.</small>
        <div className="aksi">
          <button type="button" className="tombol" onClick={onBatal}>Batal</button>
          <button className="tombol tombol-utama" disabled={proses}>{proses ? 'Menyimpan…' : 'Simpan'}</button>
        </div>
      </form>
    </>
  );
}

/** UC06 Menetapkan asisten praktikum (wireframe kelola asisten). */
function AsistenKelas({ kelas, periode, onKembali }) {
  const [asisten, setAsisten] = useState(null);
  const [kata, setKata] = useState('');
  const [hasil, setHasil] = useState(null);
  const [dipilih, setDipilih] = useState(null);
  const [pesan, setPesan] = useState(TANPA_PESAN);
  const [proses, setProses] = useState(false);
  const alamat = `/kelas-praktikum/${kelas.id_kelas}/asisten`;

  const muat = useCallback(() => {
    api.get(alamat).then((r) => setAsisten(r.data)).catch((e) => setPesan({ jenis: 'galat', teks: e.message }));
  }, [alamat]);
  useEffect(muat, [muat]);

  async function cari(e) {
    e.preventDefault();
    setPesan(TANPA_PESAN);
    setDipilih(null);
    try {
      const r = await api.get(`${alamat}/cari?q=${encodeURIComponent(kata.trim())}`);
      setHasil(r.data);
      if (!r.data.length) setPesan({ jenis: 'galat', teks: 'Asisten tidak ditemukan.' });
    } catch (err) { setPesan({ jenis: 'galat', teks: err.message }); }
  }

  async function simpan() {
    if (!dipilih) { setPesan({ jenis: 'galat', teks: 'Asisten wajib dipilih.' }); return; }
    setProses(true);
    try {
      const r = await api.post(alamat, { id_asisten: dipilih });
      setPesan({ jenis: 'sukses', teks: r.message });
      setHasil(null); setDipilih(null); setKata('');
      muat();
    } catch (err) {
      setPesan({ jenis: 'galat', teks: err.errors?.id_asisten?.[0] ?? err.message });
    } finally { setProses(false); }
  }

  const info = [['KODE MK', kelas.kode_praktikum], ['MATA KULIAH', kelas.nama_praktikum], ['KELAS', kelas.kode_kelas], ['PERIODE', periode]];

  return (
    <>
      <div className="kartu info-kelas">
        {info.map(([k, v]) => <div key={k}><small className="redup">{k}</small><div><strong>{v}</strong></div></div>)}
      </div>

      <Pesan jenis={pesan.jenis} onTutup={() => setPesan(TANPA_PESAN)}>{pesan.teks}</Pesan>

      <div className="grid-2 kolom-asisten">
        <section className="kartu">
          <h2>Daftar Asisten Kelas</h2>
          {!asisten ? <Skeleton baris={3} /> : asisten.length === 0 ? (
            <p className="redup">Belum ada asisten pada kelas ini.</p>
          ) : (
            <div className="tabel-bungkus">
              <table className="tabel tabel-bergaris">
                <thead><tr><th>No</th><th>NIM</th><th>Nama Asisten</th></tr></thead>
                <tbody>{asisten.map((a, i) => <tr key={a.id_pengguna}><td>{i + 1}</td><td>{a.nomor_induk}</td><td>{a.nama}</td></tr>)}</tbody>
              </table>
            </div>
          )}
          <div className="aksi" style={{ marginTop: '1rem' }}>
            <button className="tombol" onClick={onKembali}>Kembali</button>
          </div>
        </section>

        <section className="kartu">
          <h2>Tambah Asisten</h2>
          <form className="baris-form" onSubmit={cari}>
            <label className="bidang bidang-lebar">
              <span>Cari NIM atau Nama Asisten</span>
              <input value={kata} onChange={(e) => setKata(e.target.value)} />
            </label>
            <button className="tombol" disabled={!kata.trim()}>Cari</button>
          </form>
          {hasil?.length > 0 && (
            <>
              <small className="redup">HASIL PENCARIAN</small>
              <div className="tabel-bungkus">
                <table className="tabel tabel-bergaris">
                  <thead><tr><th>NIM</th><th>Nama Asisten</th><th>Pilih</th></tr></thead>
                  <tbody>
                    {hasil.map((a) => (
                      <tr key={a.id_pengguna}>
                        <td>{a.nomor_induk}</td><td>{a.nama}</td>
                        <td>
                          <button type="button" className={`tombol ${dipilih === a.id_pengguna ? 'tombol-utama' : ''}`}
                            aria-pressed={dipilih === a.id_pengguna} onClick={() => setDipilih(a.id_pengguna)}>
                            {dipilih === a.id_pengguna ? 'Dipilih' : 'Pilih'}
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </>
          )}
          <div className="aksi" style={{ marginTop: '1rem' }}>
            <button className="tombol tombol-utama" disabled={proses} onClick={simpan}>{proses ? 'Menyimpan…' : 'Simpan'}</button>
          </div>
        </section>
      </div>
    </>
  );
}
