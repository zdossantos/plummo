import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath } from 'node:url';
const resolve = (path) => fileURLToPath(new URL(path, import.meta.url));
export default defineConfig({
    plugins: [vue()],
    define: { 'process.env.NODE_ENV': JSON.stringify('production') },
    resolve: { alias: { '@': resolve('../../resources/js') } },
    build: {
        outDir: resolve('../../node_modules/.cache/plummo-bonus-presentation'),
        emptyOutDir: true,
        lib: {
            entry: resolve('./entry.mjs'),
            formats: ['iife'],
            name: 'Bonuses',
            fileName: () => 'bonuses.js',
        },
    },
});
