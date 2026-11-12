import { createContext, useCallback, useContext, useEffect, useState } from 'react';
import { api, token } from './api';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [pengguna, setPengguna] = useState(null);
  const [memuat, setMemuat] = useState(Boolean(token.ambil()));

  useEffect(() => {
    if (!token.ambil()) return;
    api.get('/me')
      .then((d) => setPengguna(d.pengguna))
      .catch(() => token.hapus())
      .finally(() => setMemuat(false));
  }, []);

  useEffect(() => {
    const keluarPaksa = () => setPengguna(null);
    window.addEventListener('sesi-berakhir', keluarPaksa);
    return () => window.removeEventListener('sesi-berakhir', keluarPaksa);
  }, []);

  const masuk = useCallback(async (login, kataSandi) => {
    const d = await api.post('/login', { login, kata_sandi: kataSandi });
    token.simpan(d.token);
    setPengguna(d.pengguna);
    return d.pengguna;
  }, []);

  const keluar = useCallback(async () => {
    try { await api.post('/logout'); } catch { /* token mungkin sudah tidak berlaku */ }
    token.hapus();
    setPengguna(null);
  }, []);

  return (
    <AuthContext.Provider value={{ pengguna, memuat, masuk, keluar }}>
      {children}
    </AuthContext.Provider>
  );
}

// eslint-disable-next-line react-refresh/only-export-components
export const useAuth = () => useContext(AuthContext);
