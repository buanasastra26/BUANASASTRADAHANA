import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import { dirname, resolve } from 'path';
import { fileURLToPath } from 'url';

const rootDir = dirname(fileURLToPath(import.meta.url));

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': resolve(rootDir, 'vue-frontend/src'),
      '@vue-frontend': resolve(rootDir, 'vue-frontend/src'),
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./tests/vue-unit/setup.ts'],
    include: ['tests/vue-unit/**/*.test.ts'],
    server: {
      deps: {
        inline: ['@hostinger/hcomponents'],
      },
    },
    coverage: {
      provider: 'v8',
      include: ['vue-frontend/src/**/*.{js,ts,vue}'],
      exclude: [
        'vue-frontend/src/**/*.test.ts',
        'vue-frontend/src/**/*.spec.ts',
        'vue-frontend/src/vue-shim.d.ts',
        'vue-frontend/src/**/shims-*.d.ts',
      ],
      reporter: ['text', 'json', 'json-summary', 'html', 'lcov'],
      reportsDirectory: './coverage',
      thresholds: {
        global: {
          branches: 30,
          functions: 30,
          lines: 30,
          statements: 30,
        },
      },
    },
  },
});
