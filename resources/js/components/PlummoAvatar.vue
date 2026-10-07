<script setup lang="ts">
import { computed, useId } from 'vue';
import catalog from '../../../public/plummo/catalog.json';
import { composePlummo } from '@/lib/plummo';
const props = withDefaults(
    defineProps<{ color?: string; accessories?: string[]; label?: string }>(),
    { color: 'violet', accessories: () => [], label: 'Plummo' },
);
const id = useId().replaceAll(':', '-');
const assets = import.meta.glob('../../../public/plummo/**/*.svg', {
    query: '?raw',
    import: 'default',
    eager: true,
});
const parts = Object.fromEntries(
    Object.entries(assets).map(([path, content]) => [
        path.replace('../../../public/plummo/', ''),
        content as string,
    ]),
);
const svg = computed(() =>
    composePlummo(props.color, props.accessories, catalog, parts, id),
);
</script>
<template>
    <!-- Only repository-owned SVG layers are composed; user input never becomes markup. -->
    <!-- eslint-disable-next-line vue/no-v-html -- Trusted SVG files, catalog-only colors and accessories. -->
    <svg viewBox="0 0 512 512" role="img" :aria-label="label" v-html="svg" />
</template>
