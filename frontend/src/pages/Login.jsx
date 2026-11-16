import { useState } from 'react';
import { useAuth } from '../auth';
import Pesan from '../components/Pesan';
import logo from '../components/logo/Logo Vertikal Telkom University.png';

/* UC01 Login. */
export default function Login() {
  const { masuk } = useAuth();
  const [email, setEmail] = useState('');
  const [kataSandi, setKataSandi] = useState('');
  const [galat, setGalat] = useState({});
  const [pesan, setPesan] = useState('');
  const [proses, setProses] = useState(false);

  async function kirim(e) {
    e.preventDefault();
    setPesan('');

    const g = {};
    if (!email.trim()) g.login = 'Email atau username wajib diisi.';
    if (!kataSandi) g.kata_sandi = 'Kata sandi wajib diisi.';
    setGalat(g);
    if (Object.keys(g).length) return;

    setProses(true);
    try {
      await masuk(email.trim(), kataSandi);
    } catch (err) {
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
          <p className="login-petunjuk">Peran pengguna dikenali otomatis dari akun yang dipakai.</p>
        </form>
        <p className="login-hakcipta">© 2026 Laboratorium Telkom University Surabaya</p>
      </div>
    </div>
  );
}
