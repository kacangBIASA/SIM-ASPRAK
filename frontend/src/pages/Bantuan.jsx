import { useEffect, useRef, useState } from 'react';
import { api } from '../api';
import Ikon from '../components/Ikon';
import { useAuth } from '../auth';

const CONTOH = {
  asisten: 'Bagaimana cara mengisi presensi?',
  mahasiswa: 'Bagaimana cara mengunduh sertifikat?',
  dosen: 'Bagaimana cara menetapkan asisten praktikum?',
  admin: 'Bagaimana cara menerbitkan sertifikat?',
};

function Inline({ teks }) {
  return teks.split(/(\*\*[^*]+\*\*)/g).map((s, i) => (/^\*\*[^*]+\*\*$/.test(s) ? <strong key={i}>{s.slice(2, -2)}</strong> : s));
}

function Markdown({ teks }) {
  const blok = [];
  let daftar = null;
  const tutup = () => { if (daftar) { blok.push(daftar); daftar = null; } };

  teks.split(/\r?\n/).forEach((baris) => {
    const butir = baris.match(/^\s*(?:([-*•])|(\d+)[.)])\s+(.*)$/);
    if (butir) {
      const urut = Boolean(butir[2]);
      if (daftar && daftar.urut !== urut) tutup();
      daftar ??= { urut, butir: [] };
      daftar.butir.push(butir[3]);
    } else {
      tutup();
      if (baris.trim()) blok.push({ paragraf: baris.trim().replace(/^#{1,6}\s+/, '') });
    }
  });
  tutup();

  return blok.map((b, i) => {
    if (b.paragraf !== undefined) return <p key={i}><Inline teks={b.paragraf} /></p>;
    const Tag = b.urut ? 'ol' : 'ul';
    return <Tag key={i}>{b.butir.map((t, j) => <li key={j}><Inline teks={t} /></li>)}</Tag>;
  });
}

/* US15 Bertanya kepada chatbot (KF-23, KF-24), US16 pesan di luar cakupan (KF-25) — wireframe 4 dan 5. Riwayat hanya selama halaman terbuka. */
export default function Bantuan() {
  const { pengguna } = useAuth();
  const contoh = CONTOH[pengguna.peran];
  const [pesan, setPesan] = useState([
    { dari: 'bot', teks: `Halo! Tanyakan prosedur penggunaan sistem${contoh ? `, misalnya "${contoh}"` : ''}.` },
  ]);
  const [tanya, setTanya] = useState('');
  const [proses, setProses] = useState(false);
  const bawah = useRef(null);

  useEffect(() => { bawah.current?.scrollIntoView({ behavior: 'smooth' }); }, [pesan]);

  async function kirim(isi) {
    const teks = isi.trim();
    if (!teks || proses) return;
    setPesan((p) => [...p, { dari: 'saya', teks }]);
    setTanya('');
    setProses(true);
    try {
      const r = await api.post('/chatbot/tanya', { pertanyaan: teks });
      setPesan((p) => [...p, { dari: 'bot', teks: r.jawaban, luar: r.di_luar_cakupan }]);
    } catch (err) {
      setPesan((p) => [...p, { dari: 'bot', teks: err.message, luar: true }]);
    } finally {
      setProses(false);
    }
  }

  const luar = pesan.some((m) => m.luar);

  return (
    <section className="kartu tumpuk">
      <div>
        <h2>Tanya prosedur penggunaan sistem</h2>
        <p className="redup">Jawaban disusun dari dokumen sumber yang dikelola Admin.</p>
      </div>
      <div className="obrolan" aria-live="polite">
        {pesan.map((m, i) => (m.dari === 'saya' ? (
          <div key={i} className="gelembung gelembung-saya">{m.teks}</div>
        ) : (
          <div key={i} className="baris-bot">
            <span className="avatar-bot" aria-hidden="true"><Ikon nama="obrolan" ukuran={18} /></span>
            <div className={`gelembung gelembung-bot ${m.luar ? 'luar' : ''}`}>
              {m.luar && <span className="lencana lencana-kuning">Di luar cakupan</span>}
              <Markdown teks={m.teks} />
            </div>
          </div>
        )))}
        {proses && <div className="baris-bot"><span className="avatar-bot" aria-hidden="true"><Ikon nama="obrolan" ukuran={18} /></span><div className="gelembung gelembung-bot redup">Mengetik…</div></div>}
        <div ref={bawah} />
      </div>

      {contoh && (
        <div className="contoh-tanya">
          <small className="label-kecil">Contoh pertanyaan</small>
          <button type="button" onClick={() => kirim(contoh)} disabled={proses}>{contoh}</button>
        </div>
      )}

      <form className="baris-form" onSubmit={(e) => { e.preventDefault(); kirim(tanya); }}>
        <div className="bidang bidang-lebar">
          <input value={tanya} maxLength={500} onChange={(e) => setTanya(e.target.value)} placeholder="Tulis pertanyaan…" aria-label="Pertanyaan" />
        </div>
        <button className="tombol tombol-utama" disabled={proses || !tanya.trim()}><Ikon nama="kirim" ukuran={16} />Kirim</button>
      </form>
      <small className="redup">{luar
        ? 'Chatbot tidak mengarang jawaban. Pesan ini tampil saat tidak ada bagian dokumen yang relevan.'
        : 'Jawaban disusun dari dokumen sumber. Halaman ini juga tersedia untuk Dosen Pengampu dan Admin.'}</small>
    </section>
  );
}
