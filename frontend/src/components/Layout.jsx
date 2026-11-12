import { useState } from 'react';
import { NavLink, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../auth';
import { NAMA_PERAN } from '../format';
import { MENU } from '../menu';
import Ikon from './Ikon';
import logo from './logo/logo telu polos.png';

export default function Layout() {
  const { pengguna, keluar } = useAuth();
  const { pathname } = useLocation();
  const [terbuka, setTerbuka] = useState(false);
  const menu = MENU[pengguna.peran];
  const aktif = menu.find((m) => m.ke === pathname);
  const judul = aktif?.judul ?? aktif?.label ?? 'Beranda';

  return (
    <div className="kerangka">
      <aside className={`sidebar ${terbuka ? 'terbuka' : ''}`}>
        <div className="merek">
          <img src={logo} alt="Logo" className="logo" />
          <div>
            <strong>SIM Asisten Praktikum</strong>
            <small>Laboratorium Tel-U Surabaya</small>
          </div>
        </div>
        <nav>
          {menu.map((m) => (
            <NavLink key={m.ke} to={m.ke} end={m.ke === '/'} onClick={() => setTerbuka(false)}>
              <Ikon nama={m.ikon} />
              {m.label}
            </NavLink>
          ))}
        </nav>
      </aside>
      {terbuka && <div className="tirai" onClick={() => setTerbuka(false)} />}

      <div className="utama">
        <header className="topbar">
          <button className="tombol-ikon menu-hp" onClick={() => setTerbuka(true)} aria-label="Buka menu">
            <Ikon nama="menu" />
          </button>
          <h1 className="topbar-judul">{judul}</h1>
          <div className="topbar-pengguna">
            <div className="profil-teks">
              <strong>{pengguna.nama}</strong>
              <small>{NAMA_PERAN[pengguna.peran]}</small>
            </div>
            <button className="tombol" onClick={keluar}>
              <Ikon nama="keluar" ukuran={16} />
              <span className="teks-keluar">Keluar</span>
            </button>
          </div>
        </header>
        <main className="konten">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
