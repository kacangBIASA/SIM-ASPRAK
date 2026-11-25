import { useEffect, useState } from 'react';
import { api } from '../api';
import { useDaftar, useTunda } from '../hooks';
import { useSubJudul } from '../subjudul';
import Ikon from '../components/Ikon';
import ThUrut from '../components/ThUrut';
import Halaman from '../components/Halaman';
import KotakCari from '../components/KotakCari';
import Pesan from '../components/Pesan';
import { BarisSkel, Skel } from '../components/Skeleton';

const KOSONG = { kode_praktikum: '', kode_kelas: '', nama_praktikum: '', prodi: '' };
const TANPA_PESAN = { jenis: '', teks: '' };
const errorDari = (err) => Object.fromEntries(Object.entries(err.errors ?? {}).map(([k, v]) => [k, v[0]]));

/* UC05 Mendaftarkan kelas praktikum & UC06 Menetapkan asisten (wireframe 7–9). */
export default function KelasPraktikum() {
  const [tampilan, setTampilan] = useState('daftar');
  const [pesan, setPesan] = useState(TANPA_PESAN);
  const [periode, setPeriode] = useState('');
  const { data: daftar, error, muat, cari, paging, urut } = useDaftar('/kelas-praktikum', { periode });

  const kembali = (p = TANPA_PESAN) => { setPesan(p); setTampilan('daftar'); muat(); };

  if (tampilan === 'tambah') return <FormKelas daftar={daftar} onBatal={() => kembali()} onTersimpan={kembali} />;
  if (tampilan !== 'daftar') return <KelolaAsisten idKelas={tampilan} onKembali={() => kembali()} />;

  return (
    <>
      <Pesan jenis={pesan.jenis} onTutup={() => setPesan(TANPA_PESAN)}>{pesan.teks || error}</Pesan>

      <section className="kartu">
        <h2>Kelas praktikum yang diampu</h2>
        <p className="redup">Daftarkan kelas baru atau kelola asisten pada kelas yang sudah ada.</p>
        <div className="alat-tabel">
          <KotakCari {...cari} placeholder="Cari kode, mata kuliah, kelas, atau prodi…" />
          {daftar?.periode_opsi.length > 1 && (
            <label className="bidang">
              <span>Periode</span>
              <select value={periode} onChange={(e) => setPeriode(e.target.value)}>
                {daftar.periode_opsi.map((o) => <option key={o.id} value={o.berjalan ? '' : o.id}>{o.nama}</option>)}
              </select>
            </label>
          )}
          <button className="tombol tombol-utama" onClick={() => { setPesan(TANPA_PESAN); setTampilan('tambah'); }} disabled={!daftar}><Ikon nama="tambah" />Tambah kelas</button>
        </div>
        {daftar?.data.length === 0 && !cari.nilai && <p className="redup">Belum ada kelas praktikum pada periode ini. Pilih Tambah Kelas untuk mendaftarkan kelas.</p>}
        {(!daftar || daftar.data.length > 0 || cari.nilai) && (
          <div className="tabel-bungkus">
            <table className="tabel">
              <thead>
                <tr><th>No</th><ThUrut k="kode_mk" {...urut}>Kode MK</ThUrut><ThUrut k="mata_kuliah" {...urut}>Mata Kuliah</ThUrut><ThUrut k="prodi" {...urut}>Prodi</ThUrut><ThUrut k="kelas" {...urut}>Kelas</ThUrut><ThUrut k="jumlah_asisten" {...urut}>Jumlah Asisten</ThUrut><th>Aksi</th></tr>
              </thead>
              <tbody>
                {!daftar && !error && <BarisSkel kolom={7} />}
                {daftar?.data.length === 0 && <tr><td colSpan={7} className="redup">Tidak ada kelas yang cocok.</td></tr>}
                {daftar?.data.map((k, i) => (
                  <tr key={k.id_kelas}>
                    <td>{daftar.halaman.dari + i}</td><td>{k.kode_praktikum}</td><td><b>{k.nama_praktikum}</b></td><td>{k.prodi}</td><td>{k.kode_kelas}</td>
                    <td>{k.jumlah_asisten}</td>
                    <td>
                      <button className="tombol tombol-kecil" onClick={() => { setPesan(TANPA_PESAN); setTampilan(k.id_kelas); }}>
                        Kelola asisten
                      </button>
                    </td>
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

/* UC05 langkah 4–7 (wireframe 8). */
function FormKelas({ daftar, onBatal, onTersimpan }) {
  useSubJudul('Tambah Kelas');
  const [isian, setIsian] = useState(KOSONG);
  const [error, setError] = useState({});
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
    setError(g);
    if (Object.keys(g).length) { setPesan({ jenis: 'error', teks: 'Isian wajib diisi.' }); return; }

    setProses(true);
    try {
      const r = await api.post('/kelas-praktikum', { ...isian, periode: daftar.id_periode });
      onTersimpan({ jenis: 'sukses', teks: r.message });
    } catch (err) {
      setError(errorDari(err));
      setPesan({ jenis: 'error', teks: err.errors?.kode_kelas?.[0] ?? (err.status === 422 ? 'Periksa kembali isian Anda.' : err.message) });
      setProses(false);
    }
  }

  const bidang = (k, label, input, petunjuk, wajib = true) => (
    <label className="bidang">
      <span>{label}{wajib && <i className="wajib" aria-hidden="true"> *</i>}</span>
      {input}
      {error[k] ? <small className="error">{error[k]}</small> : petunjuk && <small className="petunjuk">{petunjuk}</small>}
    </label>
  );
  const teks = (k, max, placeholder) => <input value={isian[k]} maxLength={max} placeholder={placeholder} onChange={ubah(k)} aria-invalid={Boolean(error[k])} />;

  return (
    <>
      <Pesan jenis={pesan.jenis || 'info'} onTutup={() => setPesan(TANPA_PESAN)}>{pesan.teks || 'Satu kelas hanya dapat didaftarkan satu kali pada periode yang dipilih.'}</Pesan>
      <form className="kartu form-lebar" onSubmit={simpan} noValidate>
        <div>
          <h2>Form pendaftaran kelas praktikum</h2>
          <p className="redup">Lengkapi data kelas yang akan kamu ampu. Isian bertanda <i className="wajib">*</i> wajib diisi.</p>
        </div>

        <fieldset className="bagian-form">
          <legend>Mata kuliah</legend>
          <div className="grid-nama">
            {bidang('nama_praktikum', 'Nama mata kuliah', teks('nama_praktikum', 100, 'Contoh: Praktikum Basis Data'), 'Nama lengkap mata kuliah praktikum.')}
            {bidang('kode_praktikum', 'Kode mata kuliah', teks('kode_praktikum', 20, 'Contoh: SI4001'), 'Maksimal 20 karakter.')}
          </div>
        </fieldset>

        <fieldset className="bagian-form">
          <legend>Kelas</legend>
          <div className="grid-3">
            {bidang('kode_kelas', 'Kode kelas', teks('kode_kelas', 20, 'Contoh: IS-05-01'), 'Contoh: IS-05-01.')}
            {bidang('prodi', 'Prodi', (
              <select value={isian.prodi} onChange={ubah('prodi')} aria-invalid={Boolean(error.prodi)}>
                <option value="">— Pilih prodi —</option>
                {daftar?.prodi.map((p) => <option key={p}>{p}</option>)}
              </select>
            ), 'Prodi tempat kelas ini diselenggarakan.')}
            {bidang('periode', 'Periode', <input value={daftar?.periode ?? ''} readOnly />, 'Terisi otomatis.', false)}
          </div>
        </fieldset>

        <p className="catatan-info"><Ikon nama="info" ukuran={16} />Jika kode mata kuliah sudah terdaftar pada periode ini, kelas digabungkan ke mata kuliah tersebut.</p>
        <div className="aksi aksi-garis">
          <button type="button" className="tombol" onClick={onBatal}>Batal</button>
          <button className="tombol tombol-utama" disabled={proses}>{proses ? 'Menyimpan…' : 'Simpan kelas'}</button>
        </div>
      </form>
    </>
  );
}

/* UC06 langkah 3–8 (wireframe 9). */
function KelolaAsisten({ idKelas, onKembali }) {
  useSubJudul('Kelola Asisten');
  const { data, error, muat, cari, paging, urut } = useDaftar(`/kelas-praktikum/${idKelas}`);
  const kelas = data?.data;
  const [cariBaru, setCariBaru] = useState('');
  const [hasil, setHasil] = useState([]);
  const [dipilih, setDipilih] = useState(null);
  const [pesan, setPesan] = useState(TANPA_PESAN);
  const [proses, setProses] = useState(false);
  const kata = useTunda(cariBaru.trim());

  // Pencarian mahasiswa langsung saat mengetik (wireframe: kolom cari + tabel Pilih).
  useEffect(() => {
    setDipilih(null);
    if (!kata) { setHasil([]); return undefined; }
    let batal = false;
    api.get(`/asisten?cari=${encodeURIComponent(kata)}`)
      .then((r) => { if (!batal) { setHasil(r.data); setPesan(TANPA_PESAN); } })
      .catch((err) => { if (!batal) { setHasil([]); setPesan({ jenis: 'info', teks: err.message }); } });
    return () => { batal = true; };
  }, [kata]);

  async function simpan() {
    if (!dipilih) { setPesan({ jenis: 'error', teks: 'Pilih asisten terlebih dahulu.' }); return; }
    setProses(true);
    try {
      const r = await api.post(`/kelas-praktikum/${idKelas}/asisten`, { id_asisten: dipilih });
      setPesan({ jenis: 'sukses', teks: r.message });
      setDipilih(null);
      setHasil([]);
      setCariBaru('');
      muat();
    } catch (err) {
      setPesan({ jenis: 'error', teks: err.errors?.id_asisten?.[0] ?? err.message });
    } finally {
      setProses(false);
    }
  }

  return (
    <>
      <div className="kartu kartu-info">
        {[['Kode MK', kelas?.kode_praktikum], ['Mata kuliah', kelas?.nama_praktikum], ['Prodi', kelas?.prodi], ['Kelas', kelas?.kode_kelas], ['Periode', kelas?.periode]]
          .map(([label, nilai]) => (
            <div key={label}>
              <small className="label-kecil">{label}</small>
              <strong>{nilai ?? <Skel l={90} t={16} />}</strong>
            </div>
          ))}
      </div>

      <Pesan jenis={pesan.jenis} onTutup={() => setPesan(TANPA_PESAN)}>{pesan.teks || error}</Pesan>

      <div className="grid-2 rata-atas dua-kartu-tinggi">
        <section className="kartu">
          <h2>Asisten pada kelas ini</h2>
          <p className="redup">Asisten yang sudah ditetapkan pada kelas.</p>
          <div className="alat-tabel"><KotakCari {...cari} placeholder="Cari NIM atau nama…" /></div>
          {kelas?.asisten.length === 0 ? (
            <div className="kosong-tengah">{cari.nilai ? 'Tidak ada asisten yang cocok.' : 'Belum ada asisten pada kelas ini.'}</div>
          ) : (
          <div className="tabel-bungkus">
            <table className="tabel">
              <thead><tr><th>No</th><ThUrut k="nim" {...urut}>NIM</ThUrut><ThUrut k="nama" {...urut}>Nama Asisten</ThUrut></tr></thead>
              <tbody>
                {!kelas && !error && <BarisSkel kolom={3} />}
                {kelas?.asisten.map((a, i) => (
                  <tr key={a.id_pengguna}><td>{kelas.halaman.dari + i}</td><td>{a.nomor_induk}</td><td><b>{a.nama}</b></td></tr>
                ))}
              </tbody>
            </table>
          </div>
          )}
          <Halaman {...paging} halaman={kelas?.halaman} />
        </section>

        <section className="kartu">
          <h2>Tambah asisten</h2>
          <p className="redup">Cari mahasiswa, lalu pilih untuk ditetapkan ke kelas.</p>
          <div className="alat-tabel"><KotakCari nilai={cariBaru} onUbah={setCariBaru} placeholder="Cari NIM atau nama mahasiswa…" /></div>
          {hasil.length === 0 ? (
            <div className="kosong-tengah">{kata ? 'Tidak ada hasil.' : 'Ketik NIM atau nama untuk mencari.'}</div>
          ) : (
          <div className="tabel-bungkus">
            <table className="tabel">
              <thead><tr><th>NIM</th><th>Nama Mahasiswa</th><th>Pilih</th></tr></thead>
              <tbody>
                {hasil.map((a) => (
                  <tr key={a.id_pengguna}>
                    <td>{a.nomor_induk}</td><td><b>{a.nama}</b></td>
                    <td>
                      <button type="button" aria-pressed={dipilih === a.id_pengguna}
                        className={`tombol tombol-kecil ${dipilih === a.id_pengguna ? 'tombol-utama' : ''}`}
                        onClick={() => setDipilih(a.id_pengguna)}>
                        {dipilih === a.id_pengguna ? 'Dipilih' : 'Pilih'}
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          )}
          {hasil.length > 0 && <p className="redup catatan-hasil">Menampilkan {hasil.length} data (maksimal 20 hasil teratas)</p>}
          <div className="aksi aksi-garis">
            <button type="button" className="tombol" onClick={onKembali}>Batal</button>
            <button type="button" className="tombol tombol-utama" onClick={simpan} disabled={proses}>{proses ? 'Menyimpan…' : 'Simpan penetapan'}</button>
          </div>
        </section>
      </div>
    </>
  );
}
