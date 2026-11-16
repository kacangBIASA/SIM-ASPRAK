import { useEffect, useRef, useState } from 'react';
import { api } from '../api';
import { useDaftar } from '../hooks';
import { tanggal } from '../format';
import Dialog from '../components/Dialog';
import Halaman from '../components/Halaman';
import Ikon from '../components/Ikon';
import ThUrut from '../components/ThUrut';
import KotakCari from '../components/KotakCari';
import Pesan from '../components/Pesan';

const TIPE_FOTO = ['image/jpeg', 'image/png'];
const KOSONG = { id_kelas: '', kegiatan: '', jam_masuk: '', jam_keluar: '' };
const FORMAT_JAM = /^([01]\d|2[0-3]):[0-5]\d$/;

const rapikanJam = (v) => v.replace(/\D/g, '').slice(0, 4).replace(/^(\d{2})(\d)/, '$1:$2');

/* UC02 Mencatat presensi kegiatan (wireframe 5). */
export default function Presensi() {
  const [form, setForm] = useState(null);
  const [isian, setIsian] = useState(KOSONG);
  const [foto, setFoto] = useState(null);
  const [pratinjau, setPratinjau] = useState('');
  const [galat, setGalat] = useState({});
  const [pesan, setPesan] = useState({ jenis: '', teks: '' });
  const [proses, setProses] = useState(false);
  const [seret, setSeret] = useState(false);
  const [lihat, setLihat] = useState(null);
  const inputFoto = useRef(null);
  const riwayat = useDaftar('/presensi/riwayat');

  useEffect(() => {
    api.get('/presensi/form').then(setForm).catch((e) => setPesan({ jenis: 'galat', teks: e.message }));
  }, []);

  useEffect(() => () => pratinjau && URL.revokeObjectURL(pratinjau), [pratinjau]);

  const ubah = (k) => (e) => setIsian((s) => ({ ...s, [k]: e.target.value }));
  const ubahJam = (k) => (e) => setIsian((s) => ({ ...s, [k]: rapikanJam(e.target.value) }));
  // KF-03: kelas terisi otomatis sesuai penugasan pada praktikum yang dipilih.
  const kelas = form?.praktikum.find((p) => String(p.id_kelas) === isian.id_kelas)?.kode_kelas ?? '';

  function pilihFoto(f) {
    setFoto(f);
    setPratinjau(f ? URL.createObjectURL(f) : '');
  }

  function batal() {
    setIsian(KOSONG);
    setFoto(null);
    setPratinjau('');
    setGalat({});
    setPesan({ jenis: '', teks: '' });
    inputFoto.current.value = '';
  }

  async function lihatFoto(r) {
    try {
      setLihat({ tanggal: r.tanggal, url: (await api.get(`/presensi/${r.id_presensi}/foto`)).url });
    } catch (err) {
      setPesan({ jenis: 'galat', teks: err.message });
    }
  }

  function validasi() {
    const g = {};
    if (!isian.id_kelas) g.id_kelas = 'Praktikum wajib dipilih.';
    if (!isian.kegiatan.trim()) g.kegiatan = 'Modul praktikum wajib diisi.';
    if (!isian.jam_masuk) g.jam_masuk = 'Jam masuk wajib diisi.';
    else if (!FORMAT_JAM.test(isian.jam_masuk)) g.jam_masuk = 'Format jam masuk harus JJ:MM (00:00–23:59).';
    if (!isian.jam_keluar) g.jam_keluar = 'Jam keluar wajib diisi.';
    else if (!FORMAT_JAM.test(isian.jam_keluar)) g.jam_keluar = 'Format jam keluar harus JJ:MM (00:00–23:59).';
    if (!g.jam_masuk && !g.jam_keluar && isian.jam_keluar <= isian.jam_masuk) {
      g.jam_keluar = 'Jam keluar harus lebih akhir dari jam masuk.';
    }
    if (!foto) g.foto_kegiatan = 'Foto kegiatan wajib diunggah.';
    else if (!TIPE_FOTO.includes(foto.type)) g.foto_kegiatan = 'Format foto harus JPG atau PNG.';
    else if (foto.size > form.maks_foto_kb * 1024) g.foto_kegiatan = 'Ukuran foto maksimal 2 MB.';
    return g;
  }

  async function simpan(e) {
    e.preventDefault();
    setPesan({ jenis: '', teks: '' });
    const g = validasi();
    setGalat(g);
    if (Object.keys(g).length) {
      setPesan({ jenis: 'galat', teks: 'Periksa kembali isian Anda.' });
      return;
    }

    const data = new FormData();
    Object.entries(isian).forEach(([k, v]) => data.append(k, v));
    data.append('foto_kegiatan', foto);

    setProses(true);
    try {
      const r = await api.post('/presensi', data);
      batal();
      setPesan({ jenis: 'sukses', teks: r.message });
      riwayat.muat();
    } catch (err) {
      setGalat(Object.fromEntries(Object.entries(err.errors ?? {}).map(([k, v]) => [k, v[0]])));
      setPesan({ jenis: 'galat', teks: err.status === 422 ? 'Periksa kembali isian Anda.' : err.message });
    } finally {
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

  return (
    <>
      <Pesan jenis={pesan.jenis} onTutup={() => setPesan({ jenis: '', teks: '' })}>{pesan.teks}</Pesan>

      {form && (
        <form className="kartu" onSubmit={simpan} noValidate>
          <div>
            <h2>Form presensi kegiatan</h2>
            <p className="redup">Diisi setiap kali selesai melaksanakan kegiatan praktikum.</p>
          </div>

          {form.praktikum.length === 0 ? (
            <Pesan jenis="info">Anda belum ditetapkan pada kelas praktikum di periode yang sedang berjalan.</Pesan>
          ) : (
            <>
              <div className="form-dua-kolom">
                <div className="tumpuk">
                  <div className="grid-2">
                    {bidang('tanggal', 'Tanggal', <input value={tanggal(form.tanggal)} readOnly />)}
                    {bidang('nama', 'Nama asisten (terisi otomatis)', <input value={form.asisten.nama} readOnly />)}
                  </div>
                  <div className="grid-2">
                    {bidang('id_kelas', 'Praktikum', (
                      <select value={isian.id_kelas} onChange={ubah('id_kelas')} aria-invalid={Boolean(galat.id_kelas)}>
                        <option value="">— Pilih praktikum —</option>
                        {form.praktikum.map((p) => (
                          <option key={p.id_kelas} value={p.id_kelas}>{p.kode_praktikum} · {p.nama_praktikum}</option>
                        ))}
                      </select>
                    ))}
                    {bidang('kelas', 'Kelas (terisi otomatis sesuai penugasan)', <input value={kelas} readOnly />)}
                  </div>
                  {bidang('kegiatan', 'Modul praktikum', (
                    <input value={isian.kegiatan} maxLength={150} onChange={ubah('kegiatan')} placeholder="Contoh: Modul 3"
                      aria-invalid={Boolean(galat.kegiatan)} />
                  ))}
                  <div className="grid-2">
                    {bidang('jam_masuk', 'Jam masuk', (
                      <input inputMode="numeric" placeholder="Contoh: 13:30" maxLength={5} value={isian.jam_masuk}
                        onChange={ubahJam('jam_masuk')} aria-invalid={Boolean(galat.jam_masuk)} />
                    ))}
                    {bidang('jam_keluar', 'Jam keluar', (
                      <input inputMode="numeric" placeholder="Contoh: 16:30" maxLength={5} value={isian.jam_keluar}
                        onChange={ubahJam('jam_keluar')} aria-invalid={Boolean(galat.jam_keluar)} />
                    ))}
                  </div>
                </div>

                <div className="bidang">
                  <span>Foto kegiatan</span>
                  <div className={`area-foto area-foto-tinggi ${galat.foto_kegiatan ? 'tidak-valid' : ''} ${seret ? 'seret' : ''}`}
                    onDragOver={(e) => { e.preventDefault(); setSeret(true); }} onDragLeave={() => setSeret(false)}
                    onDrop={(e) => { e.preventDefault(); setSeret(false); pilihFoto(e.dataTransfer.files?.[0] ?? null); }}>
                    {pratinjau
                      ? <img className="pratinjau-foto" src={pratinjau} alt="Pratinjau foto kegiatan" />
                      : <span className="area-foto-ikon"><Ikon nama="foto" ukuran={24} /></span>}
                    <strong>Tarik foto ke sini</strong>
                    <small className="redup">{foto ? foto.name : 'JPG atau PNG, maksimal 2 MB'}</small>
                    <button type="button" className="tombol tombol-kecil" onClick={() => inputFoto.current.click()}>Pilih foto</button>
                    <input ref={inputFoto} type="file" accept="image/jpeg,image/png" onChange={(e) => pilihFoto(e.target.files?.[0] ?? null)} hidden
                      aria-label="Foto kegiatan" />
                  </div>
                  {galat.foto_kegiatan && <small className="galat">{galat.foto_kegiatan}</small>}
                </div>
              </div>

              <div className="aksi aksi-garis">
                <button type="button" className="tombol" onClick={batal}>Batal</button>
                <button className="tombol tombol-utama" disabled={proses}>{proses ? 'Menyimpan…' : 'Simpan presensi'}</button>
              </div>
            </>
          )}
        </form>
      )}

      <section className="kartu">
        <h2>Riwayat presensi</h2>
        <p className="redup">Presensi yang sudah tercatat pada periode aktif.</p>
        <Pesan jenis="galat">{riwayat.galat}</Pesan>
        <div className="alat-tabel"><KotakCari {...riwayat.cari} placeholder="Cari praktikum, modul, atau tanggal…" /></div>
        <div className="tabel-bungkus">
          <table className="tabel">
            <thead><tr><th>No</th><ThUrut k="tanggal" {...riwayat.urut}>Tanggal</ThUrut><ThUrut k="praktikum" {...riwayat.urut}>Praktikum</ThUrut><ThUrut k="modul" {...riwayat.urut}>Modul</ThUrut><ThUrut k="jam_masuk" {...riwayat.urut}>Jam masuk</ThUrut><ThUrut k="jam_keluar" {...riwayat.urut}>Jam keluar</ThUrut><th>Foto</th></tr></thead>
            <tbody>
              {riwayat.data?.data.length === 0 && <tr><td colSpan={7} className="redup">Belum ada presensi.</td></tr>}
              {riwayat.data?.data.map((r, i) => (
                <tr key={r.id_presensi}>
                  <td>{riwayat.data.halaman.dari + i}</td><td className="nowrap">{tanggal(r.tanggal)}</td><td><b>{r.praktikum}</b></td><td>{r.modul}</td>
                  <td>{r.jam_masuk}</td><td>{r.jam_keluar}</td>
                  <td><button type="button" className="tombol tombol-kecil" onClick={() => lihatFoto(r)}>Lihat</button></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <Halaman {...riwayat.paging} />
      </section>

      {lihat && (
        <Dialog judul={`Foto kegiatan · ${tanggal(lihat.tanggal)}`} onTutup={() => setLihat(null)}
          aksi={<button type="button" className="tombol" onClick={() => setLihat(null)}>Tutup</button>}>
          <img className="foto-dialog" src={lihat.url} alt="Foto kegiatan" />
        </Dialog>
      )}
    </>
  );
}
