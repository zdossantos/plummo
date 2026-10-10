import '../css/app.css';
import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import { preventZoom } from '@/lib/preventZoom';
preventZoom();
void createInertiaApp({
    title: (title) => `${title} — Plummo`,
    progress: { color: '#7951c8' },
});
initializeTheme();
