import { Navigate, Route, Routes } from 'react-router-dom';
import { useAuth } from './auth';
import Layout from './components/Layout';
import { KerangkaSkel } from './components/Skeleton';
import Login from './pages/Login';
import Beranda from './pages/Beranda';
import Presensi from './pages/Presensi';
import Bap from './pages/Bap';
import KelasPraktikum from './pages/KelasPraktikum';
import Sertifikat from './pages/Sertifikat';
import Rekapitulasi from './pages/Rekapitulasi';
import Penilaian from './pages/Penilaian';
import DokumenChatbot from './pages/DokumenChatbot';
import Bantuan from './pages/Bantuan';
import SegeraHadir from './pages/SegeraHadir';
import SertifikatSaya from './pages/SertifikatSaya';
import { MENU } from './menu';

/* Halaman per peran lalu per path menu. Rute hanya dibuat untuk menu milik peran pengguna (KF-02), */
const HALAMAN = {
  mahasiswa: { '/sertifikat': <SegeraHadir />, '/bantuan': <Bantuan /> },
  asisten: { '/presensi': <Presensi />, '/bap': <Bap />, '/sertifikat': <SertifikatSaya />, '/bantuan': <Bantuan /> },            // US02, US03, US13
  dosen: { '/kelas-praktikum': <KelasPraktikum />, '/penilaian': <Penilaian />, '/bantuan': <Bantuan /> },                  // US05, US06, US07
  admin: { '/rekapitulasi': <Rekapitulasi />, '/sertifikat': <Sertifikat />, '/dokumen-chatbot': <DokumenChatbot />, '/bantuan': <Bantuan /> },                           // US04, US10, US11
};

export default function App() {
  const { pengguna, memuat } = useAuth();

  if (memuat) return <KerangkaSkel />;
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
