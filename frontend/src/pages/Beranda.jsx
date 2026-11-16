import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../auth';
import { useDaftar } from '../hooks';
import { NAMA_PERAN, tanggal } from '../format';
import Halaman from '../components/Halaman';
import Ikon from '../components/Ikon';
import ThUrut from '../components/ThUrut';
import KotakCari from '../components/KotakCari';
import Pesan from '../components/Pesan';

const BULAN = new Date().toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
const STATUS = { belum: ['Belum dinilai', ''], draft: ['Draft', 'lencana-kuning'], terkirim: ['Terkirim', 'lencana-hijau'] };

/* UC01 langkah 5: beranda sesuai peran (wireframe 2–4) — kartu sambutan, tiga kartu statistik, satu tabel yang dapat dicari. */
const ISI = {
  asisten: {
    statistik: [['Kehadiran bulan ini', 'tugas', `kali hadir · ${BULAN}`], ['Total jam bulan ini', 'jam', 'jam kegiatan praktikum'], ['Praktikum yang ditugaskan', 'pengguna', 'kelas pada periode aktif']],
    sambutan: (n, p) => `Kamu bertugas pada ${n} kelas praktikum periode ${p}.`,
    aksi: ['/presensi', 'Catat presensi', 'tambah'],
    tabel: 'Presensi terakhir',
    deskripsi: 'Catatan presensi terbaru milik asisten yang sedang masuk.',
    cari: 'Cari praktikum atau tanggal…',
    kolom: [['Tanggal', (b) => tanggal(b.tanggal), 'tanggal'], ['Praktikum', (b) => b.praktikum, 'praktikum'], ['Kelas', (b) => b.kelas, 'kelas'], ['Jam masuk', (b) => b.jam_masuk, 'jam_masuk'], ['Jam keluar', (b) => b.jam_keluar, 'jam_keluar']],
  },
  dosen: {
    statistik: [['Praktikum yang diampu', 'pengguna', 'kelas pada periode aktif'], ['Jumlah asisten', 'sertifikat', 'asisten di semua kelas'], ['Penilaian belum terkirim', 'bintang', 'pada periode ini', true]],
    sambutan: (n) => `Ada ${n} penilaian asisten yang belum terkirim pada periode ini.`,
    aksi: ['/kelas-praktikum', 'Buka kelas praktikum', 'folder'],
    tabel: 'Praktikum yang diampu',
    deskripsi: 'Kelas praktikum yang diampu pada periode aktif beserta status penilaiannya.',
    cari: 'Cari praktikum atau kelas…',
    kolom: [['Kode MK', (b) => b.kode_mk, 'kode_mk'], ['Praktikum', (b) => b.praktikum, 'praktikum'], ['Kelas', (b) => b.kelas, 'kelas'], ['Jumlah asisten', (b) => `${b.jumlah_asisten} asisten`, 'jumlah_asisten'],
      ['Status penilaian', (b) => <span className={`lencana ${STATUS[b.status][1]}`}>{STATUS[b.status][0]}</span>]],
  },
  admin: {
    statistik: [['Asisten aktif', 'pengguna', 'asisten pada periode aktif'], ['Kelas praktikum', 'folder', 'kelas pada periode aktif'], ['Presensi bulan ini', 'tugas', `catatan · ${BULAN}`]],
    sambutan: () => 'Pantau kehadiran asisten dan isi nomor surat sertifikat pada periode ini.',
    aksi: ['/sertifikat', 'Isi nomor surat', 'sertifikat'],
    tabel: 'Kelas praktikum berjalan',
    deskripsi: 'Seluruh kelas praktikum pada periode aktif.',
    cari: 'Cari praktikum, kelas, atau dosen…',
    kolom: [['Kode MK', (b) => b.kode_mk, 'kode_mk'], ['Praktikum', (b) => b.praktikum, 'praktikum'], ['Kelas', (b) => b.kelas, 'kelas'], ['Prodi', (b) => b.prodi, 'prodi'], ['Dosen pengampu', (b) => b.dosen, 'dosen'], ['Jumlah asisten', (b) => `${b.jumlah_asisten} asisten`, 'jumlah_asisten']],
  },
};

function BerandaMahasiswa() {
  const { pengguna } = useAuth();

  return (
    <div className="sambutan-merah">
      <div>
        <h2>Selamat datang, {pengguna.nama.split(' ')[0]}</h2>
        <p>Kamu belum ditugaskan pada kelas praktikum. Setelah dosen pengampu menetapkanmu sebagai asisten, menu Presensi dan BAP akan muncul otomatis.</p>
      </div>
    </div>
  );
}

export default function Beranda() {
  const { pengguna } = useAuth();
  return pengguna.peran === 'mahasiswa' ? <BerandaMahasiswa /> : <BerandaPeran />;
}

function BerandaPeran() {
  const { pengguna } = useAuth();
  const isi = ISI[pengguna.peran];
  const [praktikum, setPraktikum] = useState('');
  const [prodi, setProdi] = useState('');
  const { data, galat, cari, paging, urut } = useDaftar('/beranda', pengguna.peran === 'admin' ? { praktikum, prodi } : {});
  const filter = [['Praktikum', praktikum, setPraktikum, data?.praktikum_opsi, 'Semua praktikum'], ['Prodi', prodi, setProdi, data?.prodi_opsi, 'Semua prodi']];

  return (
    <>
      <div className="sambutan-merah">
        <div>
          <h2>Selamat datang, {pengguna.nama.split(' ')[0]}</h2>
          <p>{isi.sambutan(data ? data.statistik[2] : '…', pengguna.periode)}</p>
        </div>
        <Link to={isi.aksi[0]} className="tombol"><Ikon nama={isi.aksi[2]} />{isi.aksi[1]}</Link>
      </div>

      <Pesan jenis="galat">{galat}</Pesan>

      <div className="ringkasan-kartu">
        {isi.statistik.map(([s, ikon, ket, kuning], i) => (
          <div key={s} className={`kartu statistik ${kuning ? 'kuning' : ''}`}>
            <div>
              <small>{s}</small>
              <strong>{data ? data.statistik[i] : '…'}</strong>
              <span className="keterangan">{ket}</span>
            </div>
            <span className="ikon-statistik"><Ikon nama={ikon} ukuran={22} /></span>
          </div>
        ))}
      </div>

      <div className="kartu">
        <h2>{isi.tabel}</h2>
        <p className="redup">{isi.deskripsi}</p>
        <div className="alat-tabel">
          <KotakCari {...cari} placeholder={isi.cari} />
          {filter.map(([label, nilai, ubah, opsi, semua]) => opsi && (
            <label key={label} className="bidang">
              <span>{label}</span>
              <select value={nilai} onChange={(e) => ubah(e.target.value)}>
                <option value="">{semua}</option>
                {opsi.map((o) => <option key={o.id} value={o.id}>{o.nama}</option>)}
              </select>
            </label>
          ))}
        </div>
        <div className="tabel-bungkus">
          <table className="tabel">
            <thead>
              <tr><th>No</th>{isi.kolom.map(([k, , u]) => (u ? <ThUrut key={k} k={u} {...urut}>{k}</ThUrut> : <th key={k}>{k}</th>))}</tr>
            </thead>
            <tbody>
              {data?.baris.length === 0 && (
                <tr><td colSpan={isi.kolom.length + 1} className="redup">Belum ada data.</td></tr>
              )}
              {data?.baris.map((b, i) => (
                <tr key={i}>
                  <td>{data.halaman.dari + i}</td>
                  {isi.kolom.map(([k, sel]) => <td key={k}>{k === 'Praktikum' ? <b>{sel(b)}</b> : sel(b)}</td>)}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <Halaman {...paging} />
      </div>
    </>
  );
}
