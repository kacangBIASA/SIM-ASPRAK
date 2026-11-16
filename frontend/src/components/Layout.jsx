import { useState } from 'react';
import { NavLink, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../auth';
import { NAMA_PERAN } from '../format';
import { MENU } from '../menu';
import { SubJudulContext } from '../subjudul';
import Ikon from './Ikon';
import logo from './logo/logo telu polos.png';

const inisial = (nama) => nama.trim().split(/\s+/).slice(0, 2).map((k) => k[0]).join('').toUpperCase();
const Avatar = ({ nama, kelas = '' }) => <span className={`avatar ${kelas}`} aria-hidden="true">{inisial(nama)}</span>;

export default function Layout() {
  const { pengguna, keluar } = useAuth();
  const { pathname } = useLocation();
  const [terbuka, setTerbuka] = useState(false);
  const [sub, setSub] = useState('');
  const menu = MENU[pengguna.peran];
  const aktif = menu.find((m) => m.ke === pathname);
  const judul = aktif?.judul ?? aktif?.label ?? 'Beranda';
  const remah = ['Beranda', ...(pathname === '/' ? [] : [aktif?.label ?? judul]), ...(sub ? [sub] : [])];
  const subjudul = pathname === '/' ? 'Ringkasan kegiatan pada periode aktif' : remah.join(' / ');

  return (
    <div className="kerangka">
      <aside className={`sidebar ${terbuka ? 'terbuka' : ''}`}>
        <div className="merek">
          <img className="logo" src={logo} alt="Logo" />
          <div>
            <strong>SIM Asisten Praktikum</strong>
            <small>Laboratorium Tel-U Surabaya</small>
          </div>
        </div>
        <small className="label-menu">MENU</small>
        <nav>
          {menu.map((m) => (
            <NavLink key={m.ke} to={m.ke} end={m.ke === '/'} onClick={() => setTerbuka(false)}>
              <Ikon nama={m.ikon} />
              {m.label}
            </NavLink>
          ))}
        </nav>
        <div className="sidebar-profil">
          <Avatar nama={pengguna.nama} />
          <div>
            <strong>{pengguna.nama}</strong>
            <small>{NAMA_PERAN[pengguna.peran]}</small>
          </div>
        </div>
      </aside>
      {terbuka && <div className="tirai" onClick={() => setTerbuka(false)} />}

      <div className="utama">
        <header className="topbar">
          <button className="tombol-ikon menu-hp" onClick={() => setTerbuka(true)} aria-label="Buka menu">
            <Ikon nama="menu" />
          </button>
          <div className="topbar-judul">
            <h1>{judul}</h1>
            <small className="redup">{subjudul}</small>
          </div>
          <div className="topbar-pengguna">
            {pengguna.periode && <span className="pil-periode"><Ikon nama="kalender" ukuran={15} />{pengguna.periode}</span>}
            <Avatar nama={pengguna.nama} kelas="avatar-merah" />
            <span className="profil-teks">
              <strong>{pengguna.nama}</strong>
              <small>{NAMA_PERAN[pengguna.peran]}{pengguna.nomor_induk && ` · ${pengguna.nomor_induk}`}</small>
            </span>
            <button className="tombol tombol-kecil" onClick={keluar}>
              <Ikon nama="keluar" ukuran={16} />
              <span className="teks-keluar">Keluar</span>
            </button>
          </div>
        </header>
        <main className="konten">
          <SubJudulContext.Provider value={setSub}>
            <Outlet />
          </SubJudulContext.Provider>
        </main>
      </div>
    </div>
  );
}
