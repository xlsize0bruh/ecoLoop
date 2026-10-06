import { defineConfig } from 'vite';

export default defineConfig({
  server: {
    proxy: {
      '^/.*': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      }
    }
  }
});
