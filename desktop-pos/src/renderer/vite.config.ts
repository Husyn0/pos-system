import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'path'

export default defineConfig({
  root: __dirname,
  base: './',
  plugins: [react()],
  build: { outDir: path.resolve(__dirname, '../../dist') },
  server: { port: 5174 },
})
