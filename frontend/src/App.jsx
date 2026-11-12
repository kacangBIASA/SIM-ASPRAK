import { Navigate, Route, Routes } from 'react-router-dom';
import { useAuth } from './auth';
import Layout from './components/Layout';
import Login from './pages/Login';
import Beranda from './pages/Beranda';
import SegeraHadir from './pages/SegeraHadir';
import Presensi from './pages/Presensi';
import KelasPraktikum from './pages/KelasPraktikum';
import NomorSurat from './pages/NomorSurat';
import Bap from './pages/Bap';
import { MENU } from './menu';


const HALAMAN = {
  asisten: { '/presensi': <Presensi />, '/bap': <Bap /> }, // US02, US03
  dosen: { '/kelas-praktikum': <KelasPraktikum /> },                       // US05, US06
  laboran: { '/sertifikat': <NomorSurat /> },                              // US11
};

export default function App() {
  const { pengguna, memuat } = useAuth();

  if (memuat) {
    return (
      <div className="layar-muat" role="status" aria-label="Memuat">
        <aside>
          <i className="skeleton" style={{ height: 40 }} />
          {[1, 2, 3, 4].map((n) => <i key={n} className="skeleton" style={{ height: 32 }} />)}
        </aside>
        <main>
          <i className="skeleton" style={{ height: 32, width: '40%' }} />
          <i className="skeleton" style={{ height: 140 }} />
          <i className="skeleton" style={{ height: 220 }} />
        </main>
      </div>
    );
  }
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
          <Route key={m.ke} path={m.ke} element={HALAMAN[pengguna.peran][m.ke] ?? <SegeraHadir />} />
        ))}
        <Route path="*" element={<Navigate to="/" replace />} />
      </Route>
    </Routes>
  );
}
