import { useState } from 'react';
import { useDaftar } from '../hooks';
import { angka } from '../format';
import Halaman from '../components/Halaman';
import KotakCari from '../components/KotakCari';
import Pesan from '../components/Pesan';
import { BarisSkel, Skel } from '../components/Skeleton';
import DialogPresensiAsisten from '../components/DialogPresensiAsisten';
import FormPenilaian from './FormPenilaian';

const STATUS = { belum: ['Belum dinilai', ''], draft: ['Draft', 'lencana-kuning'], terkirim: ['Terkirim', 'lencana-hijau'] };
// Tombol aksi per status (wireframe 1): Lihat untuk yang terkirim, Lanjutkan untuk draft, Nilai untuk yang belum dinilai.
const AKSI = { terkirim: ['Lihat', ''], draft: ['Lanjutkan', ''], belum: ['Nilai', 'tombol-utama'] };

/* UC05 langkah 1–4 (US07): dosen memilih praktikum yang diampu, lalu melihat daftar asistennya beserta status penilaian. */
export default function Penilaian() {
  const [kelas, setKelas] = useState('');
  const [presensi, setPresensi] = useState(null); // asisten yang presensinya sedang dilihat
  const [form, setForm] = useState(null); // { idKelas, idAsisten } saat form penilaian dibuka
  const { data, error, muat, cari, paging } = useDaftar('/penilaian', { kelas }, 5); // wireframe 1: 5 baris per halaman
  const dipilih = kelas || String(data?.id_kelas ?? '');

  const [cariKelas, setCariKelas] = useState('');
  const kata = cariKelas.trim().toLowerCase();
  const tampil = data?.kelas.filter((k) => `${k.nama} ${k.prodi} ${k.kelas}`.toLowerCase().includes(kata));

  const item = (k) => {
    const aktif = String(k.id_kelas) === dipilih;
    const penuh = k.jumlah_asisten > 0 && k.terkirim === k.jumlah_asisten;
    return (
      <button key={k.id_kelas} type="button" className={`item-kelas ${aktif ? 'terpilih' : ''}`} aria-pressed={aktif} onClick={() => setKelas(String(k.id_kelas))}>
        <b>{k.nama}</b>
        <span className="pilihan-info">{k.prodi} · {k.kelas}</span>
        <span className="item-kelas-progres">
          <span className={`progres ${penuh ? 'penuh' : ''}`}><span style={{ width: `${k.jumlah_asisten ? (k.terkirim / k.jumlah_asisten) * 100 : 0}%` }} /></span>
          <small>{k.terkirim}/{k.jumlah_asisten}</small>
        </span>
      </button>
    );
  };

  if (form) return <FormPenilaian {...form} onKembali={() => { setForm(null); muat(); }} />;

  return (
    <>
      <Pesan jenis="error">{error}</Pesan>

      {data?.kelas.length === 0 && (
        <section className="kartu">
          <h2>Praktikum yang diampu</h2>
          <p className="redup">Anda belum mendaftarkan kelas praktikum pada periode ini.</p>
        </section>
      )}

      <div className="master-detail">
        <aside className={`kartu panel-kelas ${data?.kelas.length === 0 ? 'tersembunyi' : ''}`}>
          <h2>Praktikum yang diampu</h2>
          <p className="redup">Pilih kelas untuk melihat daftar asistennya.</p>
          <KotakCari nilai={cariKelas} onUbah={setCariKelas} placeholder="Cari kelas atau prodi…" />
          <label className="bidang pilih-kelas-hp">
            <span>Kelas praktikum</span>
            <select value={dipilih} onChange={(e) => setKelas(e.target.value)}>
              {data?.kelas.map((k) => <option key={k.id_kelas} value={k.id_kelas}>{k.nama} · {k.prodi} · {k.kelas}</option>)}
            </select>
          </label>
          <div className="daftar-kelas">
            {!data && !error && [0, 1, 2, 3].map((i) => (
              <div key={i} className="item-kelas"><Skel l="70%" t={15} /><Skel l="50%" t={12} /><Skel t={6} /></div>
            ))}
            {tampil?.map(item)}
            {tampil?.length === 0 && data.kelas.length > 0 && <div className="kosong-tengah">Tidak ada kelas yang cocok.</div>}
          </div>
        </aside>

      {(data ? data.id_kelas : !error) && (
        <section className="kartu detail-asisten">
          <h2>Daftar asisten · {data?.judul}</h2>
          <p className="redup">Asisten pada kelas yang dipilih beserta status penilaiannya.</p>
          <div className="alat-tabel"><KotakCari {...cari} placeholder="Cari NIM atau nama asisten…" /></div>
          {data?.asisten.length === 0 ? (
            <div className="kosong-tengah">{cari.nilai ? 'Tidak ada asisten yang cocok.' : 'Belum ada asisten pada kelas ini.'}</div>
          ) : (
          <div className="tabel-bungkus">
            <table className="tabel tabel-penilaian">
              <thead><tr><th style={{ width: 56 }}>No</th><th style={{ width: 120 }}>NIM</th><th>Nama asisten</th><th style={{ width: 130 }}>Status penilaian</th><th style={{ width: 80 }}>Rataan</th><th style={{ width: 80 }}>Hadir</th><th style={{ width: 220 }}>Aksi</th></tr></thead>
              <tbody>
                {!data && <BarisSkel kolom={7} />}
                {data?.asisten.map((a, i) => (
                  <tr key={a.id_asisten}>
                    <td className="redup">{data.halaman.dari + i}</td>
                    <td>{a.nim}</td>
                    <td><b>{a.nama}</b></td>
                    <td><span className={`lencana ${STATUS[a.status][1]}`}>{STATUS[a.status][0]}</span></td>
                    <td><b>{angka(a.rataan)}</b></td>
                    <td><b>{a.hadir} kali</b></td>
                    <td><div className="sel-aksi">
                      <button type="button" className="tombol tombol-kecil" onClick={() => setPresensi(a)}>Presensi</button>
                      <button type="button" className={`tombol tombol-kecil ${AKSI[a.status][1]}`} onClick={() => setForm({ idKelas: data.id_kelas, idAsisten: a.id_asisten })}>{AKSI[a.status][0]}</button>
                    </div></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          )}
          <Halaman {...paging} />
        </section>
      )}
      </div>
      {presensi && <DialogPresensiAsisten idKelas={data.id_kelas} asisten={presensi} onTutup={() => setPresensi(null)} />}
    </>
  );
}
