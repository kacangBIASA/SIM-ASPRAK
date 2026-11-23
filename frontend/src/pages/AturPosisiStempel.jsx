import { useEffect, useRef, useState } from 'react';
import { api } from '../api';
import Ikon from '../components/Ikon';
import Pesan from '../components/Pesan';
import { KanvasSkel } from '../components/Skeleton';
import latar from '../assets/latar-sertifikat.jpg';
import logo from '../assets/logo-telu-yayasan.png';

const LEBAR = 584, TINGGI = 413; // kanvas pratinjau (A4 lanskap), px
const ASPEK = LEBAR / TINGGI;
const TTD_MIN = 12, TTD_MAKS = 36; // % lebar halaman; sama dengan PengesahanController::TTD_RENTANG
const TTD_PUSAT_X = 655, TTD_PUSAT_Y = 493, TTD_RASIO = 58 / 220; // pt halaman; sama dengan SertifikatController
const K = LEBAR / 841.89; // pt halaman PDF → px kanvas; tata letak sama dengan template PDF sertifikat
const pct = (n) => `${n.toFixed(1).replace('.', ',')}%`;
const jepit = (n, min, max) => Math.min(Math.max(n, min), max);

/* UC09 Atur posisi stempel (US10, wireframe 6): geser stempel pada pratinjau atau pakai penggeser; nilai dalam persen halaman. */
export default function AturPosisiStempel({ onKembali }) {
  const [data, setData] = useState(null);
  const [p, setP] = useState(null);
  const [pesan, setPesan] = useState({ jenis: '', teks: '' });
  const [proses, setProses] = useState(false);
  const kanvas = useRef(null);
  const seret = useRef(null);
  const ubahTtd = useRef(false);
  const ubah = useRef(null); // ubah ukuran lewat sudut: titik diam = sudut seberangnya
  const latarRef = useRef(null);
  const [skala, setSkala] = useState(1); // kanvas diperkecil agar muat di layar sempit; koordinat internal tetap LEBAR×TINGGI

  useEffect(() => {
    api.get('/sertifikat/pengesahan').then((r) => { setData(r); setP(r.posisi); }).catch((e) => setPesan({ jenis: 'error', teks: e.message }));
  }, []);

  const siap = Boolean(data && p);
  useEffect(() => {
    const el = latarRef.current;
    if (!el) return undefined;
    const hitung = () => setSkala(Math.min(1, (el.clientWidth - 32) / LEBAR)); // 32 = padding kiri + kanan .sert-latar
    hitung();
    const ro = new ResizeObserver(hitung);
    ro.observe(el);
    return () => ro.disconnect();
  }, [siap]);

  if (!data || !p) return pesan.teks ? <Pesan jenis="error">{pesan.teks}</Pesan> : <KanvasSkel />;

  const maksX = 100 - p.ukuran;
  const maksY = 100 - p.ukuran * ASPEK;
  const kotor = ['x', 'y', 'ukuran', 'ttd_ukuran'].some((k) => Math.abs(p[k] - data.posisi[k]) > 0.001);
  const atur = (k, v) => setP((s) => {
    const n = { ...s, [k]: Number(v) };
    return { ...n, x: jepit(n.x, 0, 100 - n.ukuran), y: jepit(n.y, 0, 100 - n.ukuran * ASPEK) };
  });

  function mulaiSeret(e) {
    e.preventDefault(); // cegah seleksi teks saat menyeret
    e.currentTarget.setPointerCapture(e.pointerId);
    seret.current = { px: e.clientX, py: e.clientY, x: p.x, y: p.y };
  }
  function geser(e) {
    const s = seret.current;
    if (!s) return;
    const r = kanvas.current.getBoundingClientRect();
    setP((cur) => ({ ...cur, x: jepit(s.x + ((e.clientX - s.px) / r.width) * 100, 0, maksX), y: jepit(s.y + ((e.clientY - s.py) / r.height) * 100, 0, maksY) }));
  }

  function mulaiUbah(e, kanan, bawah) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.setPointerCapture(e.pointerId);
    const sisi = (p.ukuran / 100) * LEBAR;
    ubah.current = { kanan, bawah, ax: kanan ? kiri : kiri + sisi, ay: bawah ? atas : atas + sisi };
  }
  function ubahUkuran(e) {
    const u = ubah.current;
    if (!u) return;
    e.stopPropagation();
    const r = kanvas.current.getBoundingClientRect();
    const mx = ((e.clientX - r.left) / r.width) * LEBAR, my = ((e.clientY - r.top) / r.height) * TINGGI;
    const ruang = Math.min(u.kanan ? LEBAR - u.ax : u.ax, u.bawah ? TINGGI - u.ay : u.ay); // sudut diam tetap, stempel tidak keluar halaman
    const sisi = jepit(Math.max((u.kanan ? mx - u.ax : u.ax - mx), (u.bawah ? my - u.ay : u.ay - my)), 0.05 * LEBAR, Math.max(0.05 * LEBAR, Math.min(0.4 * LEBAR, ruang)));
    const ukuran = (sisi / LEBAR) * 100;
    const x = ((u.kanan ? u.ax : u.ax - sisi) / LEBAR) * 100, y = ((u.bawah ? u.ay : u.ay - sisi) / TINGGI) * 100;
    setP((cur) => ({ ...cur, ukuran, x: jepit(x, 0, 100 - ukuran), y: jepit(y, 0, 100 - ukuran * ASPEK) }));
  }

  function ubahTandaTangan(e) {
    if (!ubahTtd.current) return;
    const r = kanvas.current.getBoundingClientRect();
    const mx = ((e.clientX - r.left) / r.width) * LEBAR;
    setP((cur) => ({ ...cur, ttd_ukuran: jepit(((2 * Math.abs(mx - TTD_PUSAT_X * K)) / LEBAR) * 100, TTD_MIN, TTD_MAKS) }));
  }

  async function simpan() {
    setProses(true);
    try {
      const r = await api.post('/sertifikat/pengesahan/posisi', { x: p.x, y: p.y, ukuran: p.ukuran, ttd_ukuran: p.ttd_ukuran });
      setData(r); setP(r.posisi);
      setPesan({ jenis: 'sukses', teks: r.message });
    } catch (err) {
      setPesan({ jenis: 'error', teks: err.message });
    } finally {
      setProses(false);
    }
  }

  const ukuranPx = (p.ukuran / 100) * LEBAR;
  const kiri = (p.x / 100) * LEBAR, atas = (p.y / 100) * TINGGI;
  const PENGGESER = [['Posisi horizontal', 'x', maksX], ['Posisi vertikal', 'y', maksY], ['Ukuran stempel', 'ukuran', 40], ['Ukuran tanda tangan', 'ttd_ukuran', TTD_MAKS]];

  return (
    <>
      <Pesan jenis={pesan.jenis || 'error'} onTutup={() => setPesan({ jenis: '', teks: '' })}>{pesan.teks}</Pesan>
      <div className="dua-kolom dua-kolom-posisi">
        <section className="kartu">
          <h2>Pratinjau sertifikat</h2>
          <p className="redup kecil">Geser stempel pada pratinjau. Tarik sudut stempel atau tanda tangan untuk mengubah ukurannya, atau gunakan penggeser di samping.</p>
          <div className="sert-latar" ref={latarRef}>
            <div style={{ width: LEBAR * skala, height: TINGGI * skala }}>
            <div className="sert-kanvas" ref={kanvas} style={{ width: LEBAR, height: TINGGI, transform: `scale(${skala})`, transformOrigin: 'top left', '--balik': 1 / skala }}>
              <img src={latar} alt="" className="sert-t" style={{ left: 0, top: 0, width: LEBAR, height: TINGGI }} />
              <img src={logo} alt="" className="sert-t" style={{ left: 120 * K, top: 44 * K, width: 150 * K }} />
              <i className="sert-t" style={{ left: 120 * K, top: 172 * K, width: 64 * K, height: 4 * K, background: '#c8102e' }} />
              <i className="sert-t" style={{ left: 120 * K, top: 280 * K, width: 400 * K, height: 1, background: '#cfd3da' }} />
              <i className="sert-t" style={{ left: 570 * K, top: 296 * K, width: 190 * K, height: 96 * K, border: '1px solid #e2d0d4', borderRadius: 10 * K, background: '#fff8f9', boxSizing: 'border-box' }} />
              <i className="sert-t" style={{ left: 540 * K, top: 524 * K, width: 230 * K, height: 1, background: '#1f2430' }} />
              {[
                // [top, teks, gaya]; rata kiri pada x=120 kecuali ada `kiri`/`lebar`/`kanan`
                [70, `NO: ${data.nomor_surat ?? '—'}`, { kanan: 52, fontSize: 11, letterSpacing: 2, abu: 1 }],
                [112, 'SERTIFIKAT', { fontFamily: "'Archivo Black', sans-serif", fontSize: 44, lh: 54, letterSpacing: 3 }],
                [192, 'Diberikan Kepada:', { abu: 1 }],
                [212, 'Nama Asisten', { fontFamily: "'Great Vibes', cursive", fontSize: 46, lh: 62, color: '#c8102e' }],
                [296, 'Sebagai Asisten Praktikum', { abu: 1 }],
                [314, 'Nama Praktikum', { fontSize: 20, lh: 28, fontWeight: 700 }],
                [346, 'Pada Program Studi Sistem Informasi', {}],
                [364, 'Universitas Telkom Kampus Surabaya', {}],
                [382, `Periode Semester ${data.periode.replace('/', '-')}`, { abu: 1 }],
                [428, 'Surabaya, 19 November 2026', {}],
                [304, 'NILAI', { kiri: 570, lebar: 190, tengah: 1, fontSize: 9.5, letterSpacing: 3, abu: 1 }],
                [316, '89', { kiri: 570, lebar: 190, tengah: 1, fontFamily: "'Archivo Black', sans-serif", fontSize: 40, lh: 52, color: '#c8102e' }],
                [368, 'Baik Sekali', { kiri: 570, lebar: 190, tengah: 1, fontWeight: 700 }],
                [428, 'Direktur Universitas', { kiri: 520, lebar: 270, tengah: 1 }],
                [445, 'Telkom Kampus Surabaya', { kiri: 520, lebar: 270, tengah: 1 }],
                [529, data.nama_penandatangan ?? 'Nama penandatangan', { kiri: 520, lebar: 270, tengah: 1, fontSize: 11.5, fontWeight: 700 }],
                [545, `NIP : ${data.nip_penandatangan ?? '—'}`, { kiri: 520, lebar: 270, tengah: 1, fontSize: 11, abu: 1 }],
                [546, 'D0001LABTUS0012026', { fontSize: 9, letterSpacing: 1, abu: 1 }],
              ].map(([top, teks, { fontSize = 13, lh = 19, kiri = 120, lebar, kanan, tengah, abu, ...gaya }], i) => (
                <p key={i} className="sert-t" style={{
                  top: top * K, fontSize: fontSize * K, lineHeight: `${lh * K}px`, fontFamily: "'Open Sans', sans-serif", color: abu ? '#5b6170' : '#1f2430',
                  ...(kanan != null ? { right: kanan * K } : { left: kiri * K }), ...(lebar ? { width: lebar * K } : {}), ...(tengah ? { textAlign: 'center' } : {}), ...gaya,
                }}>{teks}</p>
              ))}
              {data.tanda_tangan && (() => {
                const w = (p.ttd_ukuran / 100) * LEBAR, h = w * TTD_RASIO;
                return (
                  <div className="sert-stempel sert-ttd" style={{ left: TTD_PUSAT_X * K - w / 2, top: TTD_PUSAT_Y * K - h / 2, width: w, height: h }}>
                    <img src={data.tanda_tangan} alt="Tanda tangan" draggable={false} />
                    {[false, true].map((kanan) => (
                      <i key={kanan} title="Seret untuk mengubah ukuran tanda tangan" style={{ left: kanan ? 'auto' : -6, right: kanan ? -6 : 'auto', top: -6, cursor: kanan ? 'nesw-resize' : 'nwse-resize' }}
                        onPointerDown={(e) => { e.preventDefault(); e.currentTarget.setPointerCapture(e.pointerId); ubahTtd.current = true; }}
                        onPointerMove={ubahTandaTangan} onPointerUp={() => { ubahTtd.current = false; }} onPointerCancel={() => { ubahTtd.current = false; }} />
                    ))}
                  </div>
                );
              })()}
              {data.stempel && (
                <div className="sert-stempel" style={{ left: kiri, top: atas, width: ukuranPx, height: ukuranPx }}
                  onPointerDown={mulaiSeret} onPointerMove={geser} onPointerUp={() => { seret.current = null; }} onPointerCancel={() => { seret.current = null; }}>
                  <img src={data.stempel} alt="Stempel" draggable={false} />
                  {[[false, false], [true, false], [false, true], [true, true]].map(([kanan, bawah]) => (
                    <i key={`${kanan}${bawah}`} title="Seret untuk mengubah ukuran" style={{ left: kanan ? 'auto' : -6, right: kanan ? -6 : 'auto', top: bawah ? 'auto' : -6, bottom: bawah ? -6 : 'auto', cursor: kanan === bawah ? 'nwse-resize' : 'nesw-resize' }}
                      onPointerDown={(e) => mulaiUbah(e, kanan, bawah)} onPointerMove={ubahUkuran} onPointerUp={() => { ubah.current = null; }} onPointerCancel={() => { ubah.current = null; }} />
                  ))}
                </div>
              )}
            </div>
            </div>
          </div>
          <p className="redup kecil" style={{ margin: 0, display: 'flex', gap: 8, alignItems: 'center' }}><Ikon nama="info" ukuran={16} />Stempel tidak dapat keluar dari area sertifikat. Teks pada pratinjau hanya contoh.</p>
        </section>

        <section className="kartu panel-posisi">
          <div>
            <h2>Posisi dan ukuran</h2>
            <p className="redup kecil">Nilai berupa persen terhadap lebar halaman.</p>
          </div>
          {PENGGESER.map(([label, k, maks]) => (
            <label className="penggeser" key={k}>
              <span><b>{label}</b><span className="redup">{pct(p[k])}</span></span>
              <input type="range" min={k === 'ukuran' ? 5 : k === 'ttd_ukuran' ? TTD_MIN : 0} max={maks} step="0.5" value={p[k]} onChange={(e) => atur(k, e.target.value)} />
            </label>
          ))}
          <hr className="garis-pisah" />
          <div style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
            <small className="redup"><b>Status</b></small>
            <span className={`lencana ${kotor ? 'lencana-kuning' : 'lencana-hijau'}`}>{kotor ? 'Belum disimpan' : 'Tersimpan'}</span>
          </div>
          <button className="tombol tombol-utama tombol-penuh" onClick={simpan} disabled={proses || !kotor}>{proses ? 'Menyimpan…' : 'Simpan posisi'}</button>
          <button className="tombol tombol-penuh" onClick={() => setP(data.posisi)} disabled={!kotor}>Kembalikan ke posisi awal</button>
          <button className="tombol-teks tombol-tengah" onClick={onKembali}><Ikon nama="kembali" ukuran={16} />Kembali ke Tanda tangan dan stempel</button>
        </section>
      </div>
    </>
  );
}
