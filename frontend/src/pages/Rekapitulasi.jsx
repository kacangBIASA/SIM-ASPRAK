import { useEffect, useState } from 'react';
import { useDaftar } from '../hooks';
import Halaman from '../components/Halaman';
import Ikon from '../components/Ikon';
import KotakCari from '../components/KotakCari';
import Pesan from '../components/Pesan';
import ThUrut from '../components/ThUrut';
import { BarisSkel, StatSkel } from '../components/Skeleton';

const STATISTIK = [['Total asisten', 'pengguna', 'asisten pada periode aktif', (n) => n], ['Total kehadiran', 'tugas', 'presensi tercatat dalam BAP', (n) => n], ['Total jam', 'jam', 'jam kegiatan praktikum', (n) => String(n).replace('.', ',')]];
const jam = (n) => `${String(n).replace('.', ',')} jam`;

/* UC04 Rekapitulasi kehadiran dalam BAP (US04): jumlah hadir dan total jam per asisten, difilter praktikum dan periode BAP. */
export default function Rekapitulasi() {
  const [pilih, setPilih] = useState({ praktikum: '', dari: '', sampai: '' });
  const [terapkan, setTerapkan] = useState({ praktikum: '', dari: '', sampai: '' });
  const { data, error, cari, paging, urut } = useDaftar('/rekapitulasi', terapkan);

  // Rentang bawaan dari server mengisi isian saat pertama dimuat.
  useEffect(() => { if (data && !pilih.dari) setPilih((p) => ({ ...p, ...data.rentang })); }, [data]);

  return (
    <>
      <Pesan jenis="error">{error}</Pesan>

      <div className="ringkasan-kartu">
        {STATISTIK.map(([label, ikon, ket, format], i) => (
          <div key={label} className="kartu statistik">
            <div>
              <small>{label}</small>
              <strong>{data ? format(data.statistik[i]) : <StatSkel />}</strong>
              <span className="keterangan">{ket}</span>
            </div>
            <span className="ikon-statistik"><Ikon nama={ikon} ukuran={22} /></span>
          </div>
        ))}
      </div>

      <form className="kartu" onSubmit={(e) => { e.preventDefault(); setTerapkan(pilih); }}>
        <div>
          <h2>Filter</h2>
          <p className="redup kecil">Rekapitulasi dihitung dari data presensi dalam BAP pada rentang tanggal yang dipilih.</p>
        </div>
        <div className="baris-filter">
          <label className="bidang">
            <span>Praktikum</span>
            <select value={pilih.praktikum} onChange={(e) => setPilih((p) => ({ ...p, praktikum: e.target.value }))}>
              <option value="">Semua praktikum</option>
              {data?.praktikum_opsi.map((o) => <option key={o.id} value={o.id}>{o.nama}</option>)}
            </select>
          </label>
          <label className="bidang">
            <span>Dari tanggal</span>
            <input type="date" value={pilih.dari} onChange={(e) => setPilih((p) => ({ ...p, dari: e.target.value }))} />
          </label>
          <label className="bidang">
            <span>Sampai tanggal</span>
            <input type="date" value={pilih.sampai} min={pilih.dari} onChange={(e) => setPilih((p) => ({ ...p, sampai: e.target.value }))} />
          </label>
          <button className="tombol tombol-utama" disabled={!data}>Tampilkan</button>
        </div>
      </form>

      <section className="kartu">
        <h2>Rekapitulasi per asisten</h2>
        <p className="redup">Jumlah kehadiran dan total jam setiap asisten.</p>
        <div className="alat-tabel"><KotakCari {...cari} placeholder="Cari NIM atau nama asisten…" /></div>
        <div className="tabel-bungkus">
          <table className="tabel">
            <thead>
              <tr>
                <th>No</th><ThUrut k="nim" {...urut}>NIM</ThUrut><ThUrut k="nama" {...urut}>Nama asisten</ThUrut><ThUrut k="praktikum" {...urut}>Praktikum</ThUrut>
                <ThUrut k="prodi" {...urut}>Prodi</ThUrut><ThUrut k="kelas" {...urut}>Kelas</ThUrut><ThUrut k="hadir" {...urut}>Jumlah hadir</ThUrut><ThUrut k="jam" {...urut}>Total jam</ThUrut>
              </tr>
            </thead>
            <tbody>
                {!data && !error && <BarisSkel kolom={8} />}
              {data?.baris.length === 0 && <tr><td colSpan={8} className="redup">Belum ada data kehadiran dalam BAP pada pilihan ini.</td></tr>}
              {data?.baris.map((b, i) => (
                <tr key={`${b.id_asisten}-${b.praktikum}-${b.kelas}`}>
                  <td className="redup">{data.halaman.dari + i}</td>
                  <td>{b.nim}</td>
                  <td><b>{b.nama}</b></td>
                  <td>{b.praktikum}</td>
                  <td>{b.prodi}</td>
                  <td>{b.kelas}</td>
                  <td>{b.hadir} kali</td>
                  <td><b>{jam(b.jam)}</b></td>
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
