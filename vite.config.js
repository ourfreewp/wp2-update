import { defineConfig, createLogger } from 'vite';
import path from 'path';
import sassDts from 'vite-plugin-sass-dts';
import babel from 'vite-plugin-babel';

// Dynamically determine the plugin path
const PLUGIN_PATH = process.env.PLUGIN_PATH || '/wp-content/plugins/wp2-update';

const logger = createLogger();

export default defineConfig(({ command }) => {
  logger.info(`Vite build started with command: ${command}`);

  return {
    plugins: [
      sassDts(),
      babel({
        babelConfig: {
          plugins: [
            ['@babel/plugin-proposal-decorators', { legacy: true }],
            ['@babel/plugin-proposal-class-properties', { loose: true }],
          ],
        },
      }),
      {
        name: 'log-build',
        buildStart() {
          logger.info('Build process started.');
        },
        buildEnd() {
          logger.info('Build process completed.');
        },
      },
    ],

    // The root directory for your source files
    root: '.',

    // The base path for your assets
    base: command === 'serve' ? '' : `${PLUGIN_PATH}/dist/`,

    build: {
      // The output directory for your built assets
      outDir: 'dist',
      emptyOutDir: true,

      // Ensure the manifest file is generated directly in the dist directory
      manifest: {
          fileName: 'manifest.json',
      },

      // Define your entry points
      rollupOptions: {
        external: ['wp2UpdateData'],
        output: {
          globals: {
            '@wordpress/api-fetch': 'wp.apiFetch',
            '@wordpress/i18n': 'wp.i18n',
          },
          format: 'es', // Use ES module format to support multiple inputs
          entryFileNames: 'wp2-[name].js',
          chunkFileNames: 'wp2-[name]-[hash].js',
          assetFileNames: 'wp2-[name]-[hash][extname]',
        },
        input: {
          'admin-main': path.resolve(__dirname, 'assets/scripts/admin-main.js'),
          'admin-style': path.resolve(__dirname, 'assets/styles/admin-main.scss'),
        },
      },

      // Ensure compatibility with older browsers by transpiling to ES2015
      esbuild: {
        target: 'es2020', // Add this line to ensure compatibility with modern JavaScript features
      },
    },

    css: {
      preprocessorOptions: {
        scss: {
          additionalData: `@use "sass:math";`, // Ensure SCSS is processed correctly
          includePaths: ["node_modules"],
          silenceDeprecations: [
            'import',
            'mixed-decls',
            'color-functions',
            'global-builtin',
          ],
        },
      },
    },

    server: {
      // Your local development server settings
      host: "0.0.0.0",
      // Make sure the port matches your local environment setup (e.g., DDEV, Herd)
      port: 5173,
      strictPort: true,
    },

    // Suppress deprecation warnings from Sass dependencies globally
    quietDeps: true,

    resolve: {
      alias: {
        '@components': path.resolve(__dirname, 'assets/scripts/src/components'),
        '@modals': path.resolve(__dirname, 'assets/scripts/src/components/modals'),
      },
    },
  };
});
