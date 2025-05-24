import { defineConfig } from 'vite';
import { resolve } from 'path';
import legacy from '@vitejs/plugin-legacy';

export default defineConfig({
  plugins: [
    legacy({
      targets: ['defaults', 'not IE 11']
    })
  ],
  
  // Entry points
  build: {
    outDir: 'assets/dist',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        main: resolve(__dirname, 'assets/src/main.js')
      },
      output: {
        // Generate clean filenames without hash
        entryFileNames: 'js/[name].js',
        chunkFileNames: 'js/[name].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'css/[name].css';
          }
          return 'assets/[name][extname]';
        }
      }
    },
    manifest: true,
    sourcemap: false // Disable source maps
  },
  
  // Development server
  server: {
    port: 5173,
    host: 'localhost',
    cors: true,
    origin: 'http://localhost:5173'
  },
  
  // CSS preprocessing
  css: {
    preprocessorOptions: {
      scss: {
        // Приховуємо deprecation warnings для зворотної сумісності
        silenceDeprecations: ['legacy-js-api', 'import'],
        // Global SCSS variables available in all components  
        additionalData: `
          @import "assets/src/styles/variables/_colors.scss";
          @import "assets/src/styles/variables/_spacing.scss";
          @import "assets/src/styles/variables/_breakpoints.scss";
        `
      }
    }
  },
  
  // Asset handling
  assetsInclude: ['**/*.woff', '**/*.woff2', '**/*.ttf'],
  
  // Path resolution
  resolve: {
    alias: {
      '@': resolve(__dirname, 'assets/src'),
      '@js': resolve(__dirname, 'assets/src/js'),
      '@styles': resolve(__dirname, 'assets/src/styles'),
      '@components': resolve(__dirname, 'assets/src/js/components'),
      '@utils': resolve(__dirname, 'assets/src/js/utils')
    }
  }
}); 