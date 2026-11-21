import { useRef, useState } from 'react';
import { api } from '../api';
import { useDaftar } from '../hooks';
import { tanggal } from '../format';
import Dialog from '../components/Dialog';
import Halaman from '../components/Halaman';
import Ikon from '../components/Ikon';
import ThUrut from '../components/ThUrut';
import KotakCari from '../components/KotakCari';
import Pesan from '../components/Pesan';
import { BarisSkel } from '../components/Skeleton';

/* US14 Mengelola dokumen sumber chatbot (KF-21, KF-22) — wireframe 2 dan 3. */
export default function DokumenChatbot() {
  const { data, error, muat, cari, paging, urut } = useDaftar('/chatbot/dokumen');
  const [berkas, setBerkas] = useState(null);
  const [akanHapus, setAkanHapus] = useState(null);
  const [pesan, setPesan] = useState({ jenis: '', teks: '' });
  const [proses, setProses] = useState(false);
  const [seret, setSeret] = useState(false);
  const input = useRef(null);

  async function unggah() {
    if (!berkas) {
      setPesan({ jenis: 'error', teks: 'Pilih berkas dokumen terlebih dahulu.' });
      return;
    }
    setProses(true);
    try {
      const fd = new FormData();
      fd.append('berkas', berkas);
      const r = await api.post('/chatbot/dokumen', fd);
      setPesan({ jenis: 'sukses', teks: r.message });
      setBerkas(null);
      input.current.value = '';
      muat();
    } catch (err) {
      setPesan({ jenis: 'error', teks: err.errors?.berkas?.[0] ?? err.message });
    } finally {
      setProses(false);
    }
  }

  async function hapus() {
    const d = akanHapus;
    setAkanHapus(null);
    try {
      const r = await api.hapus(`/chatbot/dokumen/${d.id_dokumen}`);
      setPesan({ jenis: 'sukses', teks: r.message });
      muat();
    } catch (err) {
      setPesan({ jenis: 'error', teks: err.message });
    }
  }

  return (
    <>
      <Pesan jenis={pesan.jenis} onTutup={() => setPesan({ jenis: '', teks: '' })}>{pesan.teks || error}</Pesan>

      <section className="kartu">
        <h2>Unggah dokumen sumber</h2>
        <p className="redup">Format PDF, DOCX, TXT, atau MD, maksimal 10 MB. Dokumen diproses terlebih dahulu sebelum dipakai chatbot.</p>
        <div className={`zona-unggah ${seret ? 'seret' : ''}`}
          onDragOver={(e) => { e.preventDefault(); setSeret(true); }} onDragLeave={() => setSeret(false)}
          onDrop={(e) => { e.preventDefault(); setSeret(false); setBerkas(e.dataTransfer.files?.[0] ?? null); }}>
          <span className="area-foto-ikon"><Ikon nama="unggah" ukuran={22} /></span>
          <div>
            <strong>{berkas ? berkas.name : 'Tarik berkas ke sini atau pilih berkas'}</strong>
            {berkas && <small className="redup" style={{ display: 'block' }}>{(berkas.size / 1024 / 1024).toFixed(2)} MB</small>}
          </div>
          <div className="aksi">
            <button type="button" className="tombol" onClick={() => input.current.click()}>Pilih berkas</button>
            <button type="button" className="tombol tombol-utama" onClick={unggah} disabled={proses}>{proses ? 'Memproses…' : <><Ikon nama="unggah" ukuran={16} />Unggah</>}</button>
          </div>
          <input ref={input} type="file" accept=".pdf,.docx,.txt,.md" hidden aria-label="Dokumen sumber" onChange={(e) => setBerkas(e.target.files[0] ?? null)} />
        </div>
      </section>

      <section className="kartu">
        <h2>Daftar dokumen sumber</h2>
        <p className="redup">Dokumen berstatus siap digunakan menjadi sumber jawaban chatbot.</p>
        <div className="alat-tabel"><KotakCari {...cari} placeholder="Cari nama dokumen…" /></div>
        <div className="tabel-bungkus">
          <table className="tabel">
            <thead><tr><th>No</th><ThUrut k="nama" {...urut}>Nama Dokumen</ThUrut><ThUrut k="tanggal" {...urut}>Tanggal Unggah</ThUrut><ThUrut k="bagian" className="kanan" {...urut}>Jumlah Bagian</ThUrut><th>Status Pemrosesan</th><th>Aksi</th></tr></thead>
            <tbody>
                {!data && !error && <BarisSkel kolom={6} />}
              {data?.data.length === 0 && (
                <tr><td colSpan={6} className="redup">{cari.nilai ? 'Tidak ada dokumen yang cocok.' : 'Belum ada dokumen. Chatbot akan menjawab "di luar cakupan" untuk semua pertanyaan.'}</td></tr>
              )}
              {data?.data.map((d, i) => (
                <tr key={d.id_dokumen}>
                  <td>{data.halaman.dari + i}</td><td><b>{d.nama_berkas}</b></td><td className="nowrap">{tanggal(d.tanggal_unggah)}</td>
                  <td className="kanan">{d.jumlah_potongan}</td>
                  <td><span className="lencana lencana-hijau">Siap digunakan</span></td>
                  <td><button type="button" className="tombol tombol-kecil" onClick={() => setAkanHapus(d)}>Hapus</button></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <Halaman {...paging} />
      </section>

      {akanHapus && (
        <Dialog judul="Hapus dokumen sumber?" onTutup={() => setAkanHapus(null)}
          aksi={<>
            <button type="button" className="tombol" onClick={() => setAkanHapus(null)}>Batal</button>
            <button type="button" className="tombol tombol-utama" onClick={hapus}>Hapus</button>
          </>}>
          <p><small className="label-kecil">Dokumen</small> {akanHapus.nama_berkas}</p>
          <p className="redup">Dokumen yang dihapus tidak lagi dipakai chatbot sebagai sumber jawaban. Tindakan ini tidak dapat dibatalkan.</p>
        </Dialog>
      )}
    </>
  );
}
