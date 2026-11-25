import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import AutoImport from 'unplugin-auto-import/vite'
import Components from 'unplugin-vue-components/vite'
import { ElementPlusResolver } from 'unplugin-vue-components/resolvers'
import { writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig(({ command, mode }) => {
  const isAdminBuild = process.argv.includes('admin.js');

  return {
    base: command === 'serve' ? '/vite/' : undefined,
    optimizeDeps: {
      include: [
        '@vuelidate/core',
        '@vuelidate/validators',
        'element-plus',
        'element-plus/es/locale/lang/ru',
      ],
    },
    server: {
      host: '0.0.0.0',
      port: 5004,
      strictPort: true,
      origin: '',
      hmr: {
        clientPort: 5000,
      },
    },
    plugins: [
      laravel({
        input: [
          'resources/js/app.js',
          'resources/js/admin.js',
        ],
        ssr: [
          'resources/js/ssr.js',
          'resources/js/ssr_admin.js',
        ],
        refresh: true,
      }),
      {
        name: 'same-origin-vite-assets',
        enforce: 'post',
        configureServer(server) {
          // Nginx serves Vite under /vite/ on the same host as Laravel.
          // Use a relative hot URL after laravel-vite-plugin writes the file.
          server.httpServer?.once('listening', () => {
            const hotFile = resolve(server.config.root, 'public/hot');
            writeFileSync(hotFile, server.config.base.replace(/\/$/, ''));
          });
        },
      },
      vue({
        template: {
          transformAssetUrls: {
            base: null,
            includeAbsolute: false,
          },
        },
      }),
      ...(isAdminBuild ? [
        AutoImport({
          resolvers: [ElementPlusResolver()],
        }),
        Components({
          resolvers: [ElementPlusResolver()],
        }),
      ] : []),
    ],
    resolve: {
      alias: {
        // vue: 'vue/dist/vue.esm-bundler.js',
        '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
      },
    },
  }
});
