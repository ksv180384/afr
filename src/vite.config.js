import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import AutoImport from 'unplugin-auto-import/vite'
import Components from 'unplugin-vue-components/vite'
import { ElementPlusResolver } from 'unplugin-vue-components/resolvers'
import { fileURLToPath, URL } from "node:url";

export default defineConfig(({ command, mode }) => {
  const isAdminBuild = process.argv.includes('admin.js');

  return {
    base: command === 'serve' ? '/vite/' : undefined,
    optimizeDeps: {
      include: ['element-plus', 'element-plus/es/locale/lang/ru'],
    },
    server: {
      host: '0.0.0.0',
      port: 5004,
      strictPort: true,
      origin: 'http://localhost:5000',
      hmr: {
        host: 'localhost',
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
