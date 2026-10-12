import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import tailwind from '@tailwindcss/vite';
import { fileURLToPath } from 'node:url';
const resolve = (path) => fileURLToPath(new URL(path, import.meta.url));
export default defineConfig({
    plugins: [vue(), tailwind()],
    resolve: {
        alias: {
            '@': resolve('../../resources/js'),
            '@inertiajs/vue3': resolve('./inertia.mjs'),
        },
    },
    build: {
        outDir: resolve('../../node_modules/.cache/plummo-viewport'),
        emptyOutDir: true,
        lib: {
            entry: resolve('./entry.mjs'),
            formats: ['iife'],
            name: 'Layout',
            fileName: () => 'layout.js',
        },
    },
});
