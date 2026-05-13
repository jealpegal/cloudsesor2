import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// Con ngrok, el WebSocket de HMR suele fallar → "connection lost" en consola.
// Arranca con: NGROK=1 npm run dev  (o export NGROK=1) para desactivar HMR mientras pruebas el túnel.
const tunelNgrok = process.env.NGROK === '1' || process.env.NGROK === 'true'

export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    host: true, // escucha en 0.0.0.0; útil si accedes por IP o túnel
    // ngrok cambia el Host en cada URL; sin esto Vite responde "Blocked request... allowedHosts"
    // En producción sirves el build estático con nginx/Apache, no expongas Vite así.
    allowedHosts: true,
    // Sin esto, el cliente intenta WS a localhost y se corta al usar la URL pública de ngrok
    hmr: tunelNgrok ? false : undefined,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
})
