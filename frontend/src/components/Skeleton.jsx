/* Penanda muat berbentuk kerangka: blok abu-abu berkilau yang meniru bentuk isi halaman. */
export function Skel({ l = '100%', t = 14, bulat = false, kelas = '' }) {
  return <span className={`skel ${bulat ? 'skel-bulat' : ''} ${kelas}`} style={{ width: l, height: t }} aria-hidden="true" />;
}

const LEBAR = ['72%', '90%', '58%', '82%', '66%'];

/* Baris isi tabel; judul kolom tetap yang asli sehingga lebar kolom sama dengan data sebenarnya. */
export function BarisSkel({ kolom, baris = 5 }) {
  return (
    <>
      <tr className="skel-sr"><td colSpan={kolom} role="status">Memuat data…</td></tr>
      {Array.from({ length: baris }, (_, i) => (
        <tr key={i} aria-hidden="true">
          {Array.from({ length: kolom }, (_, j) => <td key={j}><Skel l={j === 0 ? 24 : LEBAR[(i + j) % LEBAR.length]} /></td>)}
        </tr>
      ))}
    </>
  );
}

/* Pengganti penutup tabel "Menampilkan a–b dari N data" + nomor halaman. */
export function PaginasiSkel() {
  return (
    <div className="paginasi" aria-hidden="true">
      <Skel l={170} />
      <div className="paginasi-kanan"><Skel l={200} t={30} /></div>
    </div>
  );
}

/* Satu bidang isian (label + kotak) untuk kerangka formulir. */
export function BidangSkel({ lebar = '100%' }) {
  return (
    <div className="bidang" style={{ width: lebar }} aria-hidden="true">
      <Skel l={110} t={12} />
      <Skel t={44} kelas="skel-kotak" />
    </div>
  );
}

/* Tiga kartu statistik (Beranda, Rekapitulasi) saat data belum tiba. */
export const StatSkel = () => <Skel l={64} t={28} />;

const Kartu = ({ children, ...p }) => <div className="kartu skel-kerangka" role="status" aria-label="Memuat" {...p}>{children}</div>;
const Judul = () => <div><Skel l={220} t={20} /><div style={{ height: 8 }} /><Skel l="60%" t={13} /></div>;

/* Formulir sederhana (Nomor surat). */
export function FormSkel({ bidang = 3 }) {
  return (
    <Kartu className="form-lebar">
      <Judul />
      {Array.from({ length: bidang }, (_, i) => <BidangSkel key={i} />)}
      <div className="aksi aksi-garis"><Skel l={170} t={40} kelas="skel-kotak" /></div>
    </Kartu>
  );
}

/* Form presensi: kolom isian di kiri, area foto di kanan. */
export function FormPresensiSkel() {
  return (
    <Kartu>
      <Judul />
      <div className="form-dua-kolom">
        <div className="tumpuk">
          <div className="grid-2"><BidangSkel /><BidangSkel /></div>
          <div className="grid-2"><BidangSkel /><BidangSkel /></div>
          <BidangSkel />
          <div className="grid-2"><BidangSkel /><BidangSkel /></div>
        </div>
        <div className="bidang"><Skel l={100} t={12} /><Skel t={250} kelas="skel-kotak" /></div>
      </div>
    </Kartu>
  );
}

/* Form penilaian: kartu identitas asisten, tiga kartu kategori, lalu rataan akhir. */
export function FormPenilaianSkel() {
  return (
    <div className="skel-kerangka" role="status" aria-label="Memuat">
      <div className="kartu kartu-info info-asisten">
        <Skel l={52} t={52} bulat />
        {[0, 1, 2, 3, 4].map((i) => <div key={i}><Skel l={70} t={11} /><div style={{ height: 6 }} /><Skel l={110} t={16} /></div>)}
      </div>
      <div className="tiga-kolom">
        {[0, 1, 2].map((k) => (
          <div key={k} className="kartu kartu-kategori">
            <div><Skel l="70%" t={18} /><div style={{ height: 6 }} /><Skel l="45%" t={12} /></div>
            {[0, 1, 2, 3].map((i) => <div key={i} className="baris-komponen"><Skel l="55%" /><Skel l={70} t={36} kelas="skel-kotak" /></div>)}
          </div>
        ))}
      </div>
      <div className="kartu"><Skel l={140} t={28} /></div>
    </div>
  );
}

/* Layar pengaturan yang berisi satu kanvas besar (Atur posisi stempel). */
export function KanvasSkel() {
  return <Kartu><Judul /><Skel t={340} kelas="skel-kotak" /></Kartu>;
}

/* Kerangka seluruh aplikasi saat sesi sedang dipulihkan: sidebar, topbar, lalu kartu dan tabel. */
export function KerangkaSkel() {
  return (
    <div className="kerangka" role="status" aria-label="Memuat">
      <aside className="sidebar">
        <div className="merek"><Skel l={140} t={34} /></div>
        <div className="skel-kerangka" style={{ padding: '1rem .5rem', gap: '.4rem' }}>
          {[0, 1, 2, 3].map((i) => <div key={i} className="skel-menu"><Skel l={20} t={20} /><Skel l="60%" /></div>)}
        </div>
      </aside>
      <div className="utama">
        <header className="topbar"><div className="topbar-judul"><Skel l={160} t={20} /></div><Skel l={180} t={32} /></header>
        <main className="konten">
          <div className="skel-kerangka">
            <div className="ringkasan-kartu">{[0, 1, 2].map((i) => <div key={i} className="kartu statistik"><div><Skel l={110} t={13} /><div style={{ height: 8 }} /><Skel l={64} t={28} /></div></div>)}</div>
            <div className="kartu"><Skel l={200} t={18} /><div style={{ height: 14 }} /><table className="tabel"><tbody><BarisSkel kolom={5} baris={5} /></tbody></table></div>
          </div>
        </main>
      </div>
    </div>
  );
}
