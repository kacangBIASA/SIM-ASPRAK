import { useState, useEffect, useRef } from 'react';
import { useAuth } from '../auth';
import Pesan from '../components/Pesan';
import logo from '../components/logo/Logo Vertikal Telkom University.png';

// akun user
const AKUN = [
  {peran: 'laboran', username: 'laboran', email: 'laboran@gmail.com'},
  {peran: 'asisten', username: 'ridhonasrullah', email: 'asisten1@gmail.com'},
  {peran: 'asisten', username: 'asisten2', email: 'asisten2@gmail.com'},
  {peran: 'asisten', username: 'asisten3', email: 'asisten3@gmail.com'},
  {peran: 'asisten', username: 'asisten4', email: 'asisten4@gmail.com'},
  {peran: 'dosen', username: 'dosen1', email: 'dosen1@gmail.com'},
  {peran: 'dosen', username: 'dosen2', email: 'dosen2@gmail.com'},
]

const kata_sandi = 'password';
const TAMPILAN_AKUN = import.meta.env.DEV || import.meta.env.VITE_TAMPILKAN_AKUN === 'true';

/** UC01 Login. */
export default function Login() {
  const { masuk } = useAuth();
  const [email, setEmail] = useState('');
  const [kataSandi, setKataSandi] = useState('');
  const [galat, setGalat] = useState({});
  const [pesan, setPesan] = useState('');
  const [proses, setProses] = useState(false);
  const [akun, setAkun] = useState(false);

  const akunRef = useRef (null);
  useEffect(()=>{
    if(!akun) return;
    const tutup = (e) => {
      if (e.type === 'keydown' ? e.key === 'Escape' : !akunRef.current?.constains(e.target)) setAkun(false);
    };
    document.addEventListener('mousedown', tutup);
    document.addEventListener('keydown', tutup);
    return () => {document.removeEventListener('mousedown', tutup); document.removeEventListener('keydown', tutup);};
  }, [akun]);

  async function kirim(e) {
    e.preventDefault();
    setPesan('');

    // Alternate course 3a: isian wajib diisi.
    const g = {};
    if (!email.trim()) g.login = 'Email atau username wajib diisi.';
    if (!kataSandi) g.kata_sandi = 'Kata sandi wajib diisi.';
    setGalat(g);
    if (Object.keys(g).length) return;

    setProses(true);
    try {
      await masuk(email.trim(), kataSandi);
    } catch (err) {
      // Alternate course 4a: kredensial tidak sesuai.
      if (err.status === 422 && err.errors?.login?.[0] === 'Email atau username atau kata sandi salah.') {
        setPesan(err.errors.login[0]);
      } else if (err.errors && Object.keys(err.errors).length) {
        setGalat(Object.fromEntries(Object.entries(err.errors).map(([k, v]) => [k, v[0]])));
      } else {
        setPesan(err.message);
      }
    } finally {
      setProses(false);
    }
  }

  return (
    <div className="halaman-login">
      <div className="login-panel">
        <form onSubmit={kirim} noValidate className="kartu">
          <div className="login-merek">
            <img className="logo-login" src={logo} alt='Logo Telkom University'/>
            <h1>Masuk ke Sistem</h1>
            <p>Sistem Informasi Manajemen Asisten Praktikum <br/> Laboratorium Telkom University Surabaya</p>
          </div>
          <Pesan jenis="galat">{pesan}</Pesan>

          <label className="bidang">
            <span>Email atau Username</span>
            <input type="text" value={email} autoComplete="username" autoFocus
              onChange={(e) => setEmail(e.target.value)} aria-invalid={Boolean(galat.login)} />
            {galat.login && <small className="galat">{galat.login}</small>}
          </label>

          <label className="bidang">
            <span>Kata sandi</span>
            <input type="password" value={kataSandi} autoComplete="current-password"
              onChange={(e) => setKataSandi(e.target.value)} aria-invalid={Boolean(galat.kata_sandi)} />
            {galat.kata_sandi && <small className="galat">{galat.kata_sandi}</small>}
          </label>

          <button className="tombol tombol-utama tombol-penuh" disabled={proses}>
            {proses ? 'Memproses…' : 'Masuk'}
          </button>
        </form>

        {TAMPILAN_AKUN && (
          <div className="tampilan-akun" ref={akunRef}>
            <button type="button" className="akun-toggle" aria-expanded={akun}
            onClick={()=> setAkun((v)=> !v)}> <span aria-hidden="true">{akun? '>':'<'}</span>
            </button>
            {akun && (
              <div className="akun-list">
                <table>
                  <thead>
                    <tr>
                      <th>Peran</th>
                      <th>Username</th>
                      <th>Email</th>
                      <th>Kata Sandi</th>
                    </tr>
                  </thead>
                  <tbody>
                    {AKUN.map((a)=>
                      <tr key={a.email}>
                        <td>{a.peran}</td>
                        <td>{a.username}</td>
                        <td>{a.email}</td>
                        <td>{kata_sandi}</td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
