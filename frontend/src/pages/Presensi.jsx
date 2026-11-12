import { useEffect, useRef, useState } from 'react';
import { api } from '../api';
import { tanggal } from '../format';
import Pesan from '../components/Pesan';
import Skeleton from '../components/Skeleton';
import Ikon from '../components/Ikon';

const TIPE_FOTO = ['image/jpeg', 'image/png'];
const KOSONG = { id_kelas: '', kegiatan: '', jam_masuk: '', jam_keluar: '' };
const FORMAT_JAM = /^([01]\d|2[0-3]):[0-5]\d$/; // 24 jam, JJ:MM

/** Karakter selain angka dihapus dan titik dua disisipkan otomatis. */
const rapikanJam = (v) => v.replace(/\D/g, '').slice(0, 4).replace(/^(\d{2})(\d)/, '$1:$2');

/** UC02 Mencatat presensi kegiatan (wireframe 5). */
export default function Presensi() {
  const [form, setForm] = useState(null);
  const [isian, setIsian] = useState(KOSONG);
  const [foto, setFoto] = useState(null);
  const [pratinjau, setPratinjau] = useState('');
  const [galat, setGalat] = useState({});
  const [pesan, setPesan] = useState({ jenis: '', teks: '' });
  const [proses, setProses] = useState(false);
  const inputFoto = useRef(null);

  // Basic course langkah 2: tanggal, identitas asisten, dan penugasan (praktikum + kelas).
  useEffect(() => {
    api.get('/presensi/form').then(setForm).catch((e) => setPesan({ jenis: 'galat', teks: e.message }));
  }, []);

  useEffect(() => () => pratinjau && URL.revokeObjectURL(pratinjau), [pratinjau]);

  const ubah = (k) => (e) => setIsian((s) => ({ ...s, [k]: e.target.value }));
  const ubahJam = (k) => (e) => setIsian((s) => ({ ...s, [k]: rapikanJam(e.target.value) }));
  // KF-03: kelas terisi otomatis sesuai penugasan pada praktikum yang dipilih.
  const kelas = form?.praktikum.find((p) => String(p.id_kelas) === isian.id_kelas)?.kode_kelas ?? '';

  function pilihFoto(e) {
    const f = e.target.files?.[0] ?? null;
    setFoto(f);
    setPratinjau(f ? URL.createObjectURL(f) : '');
  }

  /** Validasi (alternate course 4a & 4b). */
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
      setPesan({ jenis: 'sukses', teks: r.message }); // Basic course langkah 5: konfirmasi.
      setIsian(KOSONG);
      setFoto(null);
      setPratinjau('');
      inputFoto.current.value = '';
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

      {!form && pesan.jenis !== 'galat' && <Skeleton baris={5} />}

      {form && (
        <form className="kartu form-presensi" onSubmit={simpan} noValidate>
          <h2>Form Presensi Kegiatan</h2>

          <div className="grid-2">
            {bidang('tanggal', 'Tanggal', <input value={tanggal(form.tanggal)} readOnly />)}
            {bidang('nama', 'Nama Asisten', <input value={form.asisten.nama} readOnly />)}
          </div>

          {form.praktikum.length === 0 ? (
            <Pesan jenis="info">Anda belum ditetapkan pada kelas praktikum di periode yang sedang berjalan.</Pesan>
          ) : (
            <>
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

              {bidang('kegiatan', 'Modul Praktikum', (
                <input value={isian.kegiatan} maxLength={150} onChange={ubah('kegiatan')} placeholder="Contoh: Modul 3"
                  aria-invalid={Boolean(galat.kegiatan)} />
              ))}

              <div className="grid-2">
                {bidang('jam_masuk', 'Jam Masuk', (
                  <input inputMode="numeric" placeholder="Contoh: 13:30" maxLength={5} value={isian.jam_masuk}
                    onChange={ubahJam('jam_masuk')} aria-invalid={Boolean(galat.jam_masuk)} />
                ))}
                {bidang('jam_keluar', 'Jam Keluar', (
                  <input inputMode="numeric" placeholder="Contoh: 16:30" maxLength={5} value={isian.jam_keluar}
                    onChange={ubahJam('jam_keluar')} aria-invalid={Boolean(galat.jam_keluar)} />
                ))}
              </div>

              <div className="bidang">
                <span>Foto Kegiatan</span>
                <div className={`area-foto ${galat.foto_kegiatan ? 'tidak-valid' : ''}`}>
                  {pratinjau
                    ? <img className="pratinjau-foto" src={pratinjau} alt="Pratinjau foto kegiatan" />
                    : <span className="area-foto-ikon"><Ikon nama="foto" ukuran={40} /></span>}
                  <button type="button" className="tombol" onClick={() => inputFoto.current.click()}>Pilih Foto</button>
                  <small className="redup">{foto ? foto.name : 'Format JPG/PNG, maksimal 2 MB'}</small>
                  <input ref={inputFoto} type="file" accept="image/jpeg,image/png" onChange={pilihFoto} hidden
                    aria-label="Foto kegiatan" />
                </div>
                {galat.foto_kegiatan && <small className="galat">{galat.foto_kegiatan}</small>}
              </div>

              <div className="aksi">
                <button className="tombol tombol-utama" disabled={proses}>{proses ? 'Menyimpan…' : 'Simpan'}</button>
              </div>
            </>
          )}
        </form>
      )}
    </>
  );
}
