import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { VitePWA } from 'vite-plugin-pwa'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    react(),
    VitePWA({
      registerType: 'autoUpdate',
      includeAssets: ['favicon.png'],
      manifest: {
        name: 'SIMAS',
        short_name: 'SIMAS',
        lang: 'id',
        start_url: '/',
        display: 'standalone',
        theme_color: '#ed1e28',
        background_color: '#ffffff',
        icons: [
          { src: 'pwa-192.png', sizes: '192x192', type: 'image/png' },
          { src: 'pwa-512.png', sizes: '512x512', type: 'image/png' },
          { src: 'pwa-maskable-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
        ],
      },
      // Hanya cangkang aplikasi yang di-cache; panggilan API tidak, agar data presensi/nilai tidak basi.
      workbox: { navigateFallbackDenylist: [/^\/api/] },
    }),
  ],
})
