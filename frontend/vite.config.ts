import path from 'node:path'

import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: [
      {
        find: /^@inmediam\/ui$/,
        replacement: path.resolve(__dirname, './src/lib/inmediam-ui.ts'),
      },
      {
        find: /^use-sync-external-store(\/.*)?$/,
        replacement: 'react',
      },
      {
        find: '@',
        replacement: path.resolve(__dirname, './src'),
      },
    ],
  },
  server: {
    port: 3000,
    host: true,
  },
})
