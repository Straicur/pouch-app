import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    host: true, // listen on 0.0.0.0 so the dev server is reachable from the Docker host
    port: 5173,
    strictPort: true,
    proxy: {
      // forwards /api/* to the backend so the frontend can call same-origin paths
      // and JWT auth cookies work without extra CORS config in dev.
      '/api': {
        target: process.env.VITE_API_PROXY_TARGET ?? 'http://localhost:8111',
        changeOrigin: true,
      },
    },
  },
})
