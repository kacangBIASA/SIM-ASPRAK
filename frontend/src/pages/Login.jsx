import { useState } from 'react';
import { useAuth } from '../auth';
import Ikon from '../components/Ikon';
import Pesan from '../components/Pesan';
import logo from '../components/logo/Logo Vertikal Telkom University.png';

/* UC01 Login. */
export default function Login() {
  const { masuk } = useAuth();
  const [email, setEmail] = useState('');
  const [kataSandi, setKataSandi] = useState('');
  const [intip, setIntip] = useState(false);
  const [error, setError] = useState({});
  const [pesan, setPesan] = useState('');
  const [proses, setProses] = useState(false);

  async function kirim(e) {
    e.preventDefault();
    setPesan('');

    const g = {};
    if (!email.trim()) g.login = 'Email atau username wajib diisi.';
    if (!kataSandi) g.kata_sandi = 'Kata sandi wajib diisi.';
    setError(g);
    if (Object.keys(g).length) return;

    setProses(true);
    try {
      await masuk(email.trim(), kataSandi);
    } catch (err) {
      if (err.status === 422 && err.errors?.login?.[0] === 'Email atau username atau kata sandi salah.') {
        setPesan(err.errors.login[0]);
      } else if (err.errors && Object.keys(err.errors).length) {
        setError(Object.fromEntries(Object.entries(err.errors).map(([k, v]) => [k, v[0]])));
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
          <Pesan jenis="error">{pesan}</Pesan>

          <label className="bidang">
            <span>Email atau Username</span>
            <input type="text" value={email} autoComplete="username" autoFocus placeholder="Masukkan email atau username"
              onChange={(e) => setEmail(e.target.value)} aria-invalid={Boolean(error.login)} />
            {error.login && <small className="error">{error.login}</small>}
          </label>

          <label className="bidang">
            <span>Kata sandi</span>
            <span className="input-sandi">
              <input type={intip ? 'text' : 'password'} value={kataSandi} autoComplete="current-password"
                placeholder="Masukkan kata sandi" onChange={(e) => setKataSandi(e.target.value)} aria-invalid={Boolean(error.kata_sandi)} />
              <button type="button" className="intip-sandi" onClick={() => setIntip((v) => !v)}
                aria-label={intip ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'} aria-pressed={intip}>
                <Ikon nama={intip ? 'mataTutup' : 'mata'} />
              </button>
            </span>
            {error.kata_sandi && <small className="error">{error.kata_sandi}</small>}
          </label>

          <button className="tombol tombol-utama tombol-penuh" disabled={proses}>
            {proses ? 'Memproses…' : 'Masuk'}
          </button>
        </form>
      </div>
    </div>
  );
}
