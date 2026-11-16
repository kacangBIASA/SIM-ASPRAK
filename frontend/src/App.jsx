import { Navigate, Route, Routes } from 'react-router-dom';
import { useAuth } from './auth';
import Layout from './components/Layout';
import Login from './pages/Login';
import Beranda from './pages/Beranda';
import Presensi from './pages/Presensi';
import Bap from './pages/Bap';
import KelasPraktikum from './pages/KelasPraktikum';
import NomorSurat from './pages/NomorSurat';
import { MENU } from './menu';

/* Halaman per peran lalu per path menu. Rute hanya dibuat untuk menu milik peran pengguna (KF-02), */
const HALAMAN = {
  mahasiswa: {},
  asisten: { '/presensi': <Presensi />, '/bap': <Bap /> },            // US02, US03
  dosen: { '/kelas-praktikum': <KelasPraktikum /> },                  // US05, US06
  admin: { '/sertifikat': <NomorSurat /> },                           // US11
};

export default function App() {
  const { pengguna, memuat } = useAuth();

  if (memuat) return <div className="layar-muat">Memuat…</div>;
  if (!pengguna) {
    return (
      <Routes>
        <Route path="*" element={<Login />} />
      </Routes>
    );
  }

  return (
    <Routes>
      <Route element={<Layout />}>
        <Route index element={<Beranda />} />
        {MENU[pengguna.peran].filter((m) => m.ke !== '/').map((m) => (
          <Route key={m.ke} path={m.ke} element={HALAMAN[pengguna.peran][m.ke]} />
        ))}
        <Route path="*" element={<Navigate to="/" replace />} />
      </Route>
    </Routes>
  );
}
