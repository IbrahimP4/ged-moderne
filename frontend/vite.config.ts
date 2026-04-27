import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import path from 'path'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  server: {
    port: 5173,
    host: '0.0.0.0',   // écoute sur toutes les interfaces → accessible sur le réseau local
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8000',  // 127.0.0.1 évite le conflit IPv6/IPv4
        changeOrigin: true,
      },
    },
  },
})
