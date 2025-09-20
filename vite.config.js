import { defineConfig } from 'vite';
import path from 'path';
import sassDts from 'vite-plugin-sass-dts';

// Define your plugin's public path
const PLUGIN_PATH = '/wp-content/plugins/wp2-update'; // <-- Change 'wp2-update' to your plugin's folder name

export default defineConfig(({ command }) => ({
  plugins: [sassDts()],

  // The root directory for your source files
  root: '.',

  // The base path for your assets
  base: command === 'serve' ? '' : `${PLUGIN_PATH}/dist/`,

  build: {
    // The output directory for your built assets
    outDir: 'dist',
    emptyOutDir: true,

    // Generate a manifest file to help PHP locate the assets
    manifest: true,

    // Define your entry points
    rollupOptions: {
      output: {
        format: 'es', // Use ES module format to support multiple inputs
        entryFileNames: '[name].js',
        chunkFileNames: '[name]-[hash].js',
        assetFileNames: '[name]-[hash][extname]',
      },
      input: {
        'admin-main': path.resolve(__dirname, 'assets/scripts/admin-main.js'),
        'admin-style': path.resolve(__dirname, 'assets/styles/admin-main.scss'),
      },
    },

    // Ensure compatibility with older browsers by transpiling to ES2015
    esbuild: {
      target: 'es2015',
    },
  },

  css: {
    preprocessorOptions: {
      scss: {
        additionalData: `@use "sass:math";` // Ensure SCSS is processed correctly
      }
    }
  },

  server: {
    // Your local development server settings
    host: "0.0.0.0",
    // Make sure the port matches your local environment setup (e.g., DDEV, Herd)
    port: 5173,
    strictPort: true,
  },
}));
