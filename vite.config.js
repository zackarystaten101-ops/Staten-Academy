import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
  plugins: [react()],
  build: {
    // Use a build subdirectory to avoid conflicts with existing assets structure
    outDir: 'public/assets/js/build',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        classroom: path.resolve(__dirname, 'src/classroom.tsx')
      },
      output: {
        // Output bundle directly in build directory, not in nested assets/
        entryFileNames: '[name].bundle.js',
        chunkFileNames: '[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          // Put all assets directly in build directory, no nested assets/ folder
          const ext = assetInfo.name?.split('.').pop() || 'bin';
          if (ext === 'css') {
            return 'classroom.[hash].css';
          }
          // Images and other assets go directly in build dir
          return '[name]-[hash].[ext]';
        },
        format: 'iife',
        name: 'ClassroomApp'
      }
    },
    sourcemap: true
  },
  server: {
    port: 3000,
    proxy: {
      '/api': {
        target: 'http://localhost',
        changeOrigin: true
      },
      '/websocket': {
        target: 'ws://localhost:8080',
        ws: true
      }
    }
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'src')
    }
  }
});











