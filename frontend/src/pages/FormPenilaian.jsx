import { useEffect, useState } from 'react';
import { api } from '../api';
import { angka, tanggal } from '../format';
import { useSubJudul } from '../subjudul';
import Dialog from '../components/Dialog';
import Ikon from '../components/Ikon';
import Pesan from '../components/Pesan';
import { FormPenilaianSkel } from '../components/Skeleton';

const STATUS = { belum: ['Belum dinilai', ''], draft: ['Draft', 'lencana-kuning'], terkirim: ['Terkirim', 'lencana-hijau'] };
const inisial = (nama) => nama.split(' ').filter(Boolean).slice(0, 2).map((k) => k[0]).join('').toUpperCase();
const sah = (v) => /^\d+$/.test(v) && Number(v) <= 100;

/* Rata-rata kategori dan rataan akhir; rumus sama dengan Penilaian::hitung di backend (yang menjadi acuan saat disimpan). */
function hitung(kategori, nilai) {
  const rata = kategori.map((k) => {
    const isi = k.komponen.map((c) => nilai[c.id]).filter((v) => v !== '' && sah(v)).map(Number);
    return isi.length === k.komponen.length ? isi.reduce((a, b) => a + b, 0) / isi.length : null; // kategori belum lengkap → belum ada rata-rata
  });
  const dua = (n) => (n === null ? null : Math.round(n * 100) / 100);
  return { rata: rata.map(dua), akhir: rata.includes(null) ? null : dua(rata.reduce((a, b) => a + b, 0) / rata.length) };
}

/* UC05–UC07 (US08–US09, wireframe 2–3): form penilaian satu asisten; draft dapat dilanjutkan, penilaian terkirim hanya dapat dibaca. */
export default function FormPenilaian({ idKelas, idAsisten, onKembali }) {
  const [data, setData] = useState(null);
  const [nilai, setNilai] = useState({});
  const [galat, setGalat] = useState({});
  const [pesan, setPesan] = useState({ jenis: '', teks: '' });
  const [konfirmasi, setKonfirmasi] = useState(false);
  const [proses, setProses] = useState(false);
  useSubJudul(data?.status === 'terkirim' ? 'Lihat Nilai' : 'Form Nilai');

  const muat = (d) => {
    setData(d);
    setNilai(Object.fromEntries(d.kategori.flatMap((k) => k.komponen.map((c) => [c.id, c.nilai === null ? '' : String(c.nilai)]))));
  };
  useEffect(() => {
    api.get(`/penilaian/${idKelas}/${idAsisten}`).then(muat).catch((e) => setPesan({ jenis: 'error', teks: e.message }));
  }, [idKelas, idAsisten]);

  if (!data) return pesan.teks ? <Pesan jenis="error">{pesan.teks}</Pesan> : <FormPenilaianSkel />;

  const baca = data.status === 'terkirim';
  const semua = data.kategori.flatMap((k) => k.komponen);
  const kosong = semua.filter((c) => nilai[c.id] === '').length;
  const { rata, akhir } = hitung(data.kategori, nilai);
  const [teksStatus, kelasStatus] = STATUS[data.status];

  const ubah = (id, v) => {
    setNilai((n) => ({ ...n, [id]: v.replace(/\D/g, '').slice(0, 3) }));
    setGalat((g) => ({ ...g, [id]: undefined }));
  };

  // Langkah 8a: nilai di luar rentang 0–100 langsung ditandai pada isiannya; simpan draft dan kirim ditahan sampai diperbaiki.
  const salah = Object.fromEntries(semua.filter((c) => nilai[c.id] !== '' && !sah(nilai[c.id])).map((c) => [c.id, 'Nilai harus antara 0 sampai 100.']));
  const adaSalah = Object.keys(salah).length;
  const pesanKomponen = (c) => salah[c.id] ?? galat[c.id];

  async function kirim(aksi) {
    setPesan({ jenis: '', teks: '' });
    setProses(true);
    try {
      const body = { nilai: Object.fromEntries(semua.map((c) => [c.id, nilai[c.id] === '' ? null : Number(nilai[c.id])])) };
      const r = await api.post(`/penilaian/${idKelas}/${idAsisten}/${aksi}`, body);
      muat(r);
      setPesan({ jenis: 'sukses', teks: r.message });
    } catch (err) {
      setGalat(Object.fromEntries(Object.entries(err.errors ?? {}).map(([k, v]) => [k.replace('nilai.', ''), v[0]])));
      setPesan({ jenis: 'error', teks: err.message });
    } finally {
      setProses(false);
      setKonfirmasi(false);
    }
  }

  return (
    <>
      <Pesan jenis={pesan.jenis || 'info'} onTutup={() => setPesan({ jenis: '', teks: '' })}>{pesan.teks}</Pesan>
      {baca && !pesan.teks && <Pesan jenis="info">Penilaian sudah terkirim pada {tanggal(data.dikirim_pada).replace(/^0/, '')} dan tidak dapat diubah.</Pesan>}

      <section className="kartu kartu-info info-asisten">
        <span className="avatar-besar">{inisial(data.asisten.nama)}</span>
        <div><small className="label-kecil">Nama asisten</small><b>{data.asisten.nama}</b></div>
        <div><small className="label-kecil">NIM</small><b>{data.asisten.nim}</b></div>
        <div><small className="label-kecil">Praktikum</small><b>{data.praktikum}</b></div>
        <div><small className="label-kecil">Kelas</small><b>{data.kelas}</b></div>
        <div><small className="label-kecil">Status penilaian</small><span className={`lencana ${kelasStatus}`}>{teksStatus}</span></div>
      </section>

      <div className="tiga-kolom">
        {data.kategori.map((k, i) => (
          <section key={k.kode} className="kartu kartu-kategori">
            <div><h2>{k.kode}. {k.nama}</h2><p className="redup">Nilai 0–100 per komponen</p></div>
            {k.komponen.map((c) => (
              <div key={c.id} className="baris-komponen">
                <label htmlFor={`nilai-${c.id}`}>{c.nama}</label>
                <input id={`nilai-${c.id}`} className="isian-nilai" inputMode="numeric" placeholder="0–100" value={nilai[c.id] ?? ''} readOnly={baca}
                  aria-invalid={Boolean(pesanKomponen(c))} title={pesanKomponen(c)} onChange={(e) => ubah(c.id, e.target.value)} />
              </div>
            ))}
            {k.komponen.some(pesanKomponen) && <p className="error" role="alert">{k.komponen.map(pesanKomponen).find(Boolean)}</p>}
            <div className="garis-pisah" />
            <div className="rataan-kategori"><span>Rata-rata kategori</span><b>{angka(rata[i])}</b></div>
          </section>
        ))}
      </div>

      <section className="kartu rataan-akhir">
        <div className="rataan-nilai">
          <div><small className="label-kecil">Rataan akhir</small><b className={baca ? 'hijau' : ''}>{angka(akhir)}</b></div>
          {!baca && <p className="redup">{adaSalah ? `Perbaiki ${adaSalah} isian di luar rentang 0–100.` : kosong ? `Lengkapi ${kosong} komponen lagi untuk mengirim penilaian.` : 'Semua komponen sudah terisi. Penilaian siap dikirim.'}</p>}
        </div>
        <div className="aksi">
          {baca ? <button type="button" className="tombol" onClick={onKembali}>Kembali</button> : (
            <>
              <button type="button" className="tombol" onClick={onKembali} disabled={proses}>Batal</button>
              <button type="button" className="tombol" onClick={() => kirim('draft')} disabled={proses || adaSalah > 0}>Simpan draft</button>
              <button type="button" className="tombol tombol-utama tombol-tengah" onClick={() => setKonfirmasi(true)} disabled={proses || kosong > 0 || adaSalah > 0}><Ikon nama="kirim" ukuran={18} />Kirim penilaian</button>
            </>
          )}
        </div>
      </section>

      {konfirmasi && (
        <Dialog judul="Kirim penilaian?" onTutup={() => setKonfirmasi(false)} aksi={(
          <>
            <button type="button" className="tombol" onClick={() => setKonfirmasi(false)} disabled={proses}>Periksa lagi</button>
            <button type="button" className="tombol tombol-utama" onClick={() => kirim('kirim')} disabled={proses}>Kirim</button>
          </>
        )}>
          <p>Penilaian untuk <b>{data.asisten.nama}</b> dengan rataan akhir <b>{angka(akhir)}</b> akan dikirim dan tidak dapat diubah lagi.</p>
        </Dialog>
      )}
    </>
  );
}
