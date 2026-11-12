const BASE_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api';
const KUNCI_TOKEN = 'sim-asprak-token';

export class ApiError extends Error {
  constructor(status, message, errors = {}) {
    super(message);
    this.status = status;
    this.errors = errors;
  }
}

export const token = {
  ambil: () => {
    try { return localStorage.getItem(KUNCI_TOKEN); } catch { return null; }
  },
  simpan: (t) => {
    try { localStorage.setItem(KUNCI_TOKEN, t); } catch { /* abaikan */ }
  },
  hapus: () => {
    try { localStorage.removeItem(KUNCI_TOKEN); } catch { /* abaikan */ }
  },
};

async function kirim(path, { method = 'GET', body } = {}) {
  const headers = { Accept: 'application/json' };
  const t = token.ambil();
  if (t) headers.Authorization = `Bearer ${t}`;

  let payload = body;
  if (body && !(body instanceof FormData)) {
    headers['Content-Type'] = 'application/json';
    payload = JSON.stringify(body);
  }

  let res;
  try {
    res = await fetch(`${BASE_URL}${path}`, { method, headers, body: payload });
  } catch {
    throw new ApiError(0, 'Tidak dapat terhubung ke server. Periksa koneksi Anda.');
  }

  if (res.status === 401 && path !== '/login') {
    token.hapus();
    window.dispatchEvent(new Event('sesi-berakhir'));
  }

  return res;
}

async function json(res) {
  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    throw new ApiError(res.status, data.message ?? 'Terjadi kesalahan pada server.', data.errors ?? {});
  }
  return data;
}

export const api = {
  get: async (path) => json(await kirim(path)),
  post: async (path, body) => json(await kirim(path, { method: 'POST', body })),

  /**
   * Mengunduh berkas lewat tautan bertanda tangan: `path` mengembalikan { url, nama_berkas }
   * setelah hak akses diperiksa, lalu browser (atau download manager) mengunduh dari tautan itu.
   */
  async unduh(path) {
    const { url, nama_berkas: nama } = await json(await kirim(path));
    const a = Object.assign(document.createElement('a'), { href: url, download: nama, rel: 'noopener' });
    document.body.appendChild(a);
    a.click();
    a.remove();
  },
};
