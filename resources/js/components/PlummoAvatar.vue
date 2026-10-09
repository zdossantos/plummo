<script setup lang="ts">
import { computed, useId } from 'vue';
import catalog from '../../../public/plummo/catalog.json';
import rig from '../../../public/plummo/rig.json';
import { usePlummoMotion } from '@/composables/usePlummoMotion';
import type { PlummoMotion } from '@/lib/plummo-motion';
import { composeAnimatedPlummo } from '@/lib/plummo';
const props = withDefaults(
    defineProps<{
        color?: string;
        accessories?: string[];
        label?: string;
        motion?: PlummoMotion;
        motionKey?: string;
    }>(),
    { color: 'violet', accessories: () => [], label: 'Plummo' },
);
const id = useId().replaceAll(':', '-');
const idleDelay =
    -(Array.from(id).reduce((sum, char) => sum + char.charCodeAt(0), 0) % 47) /
    10;
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
const { active } = usePlummoMotion(
    () => props.motion,
    () => props.motionKey,
);
const svg = computed(() =>
    composeAnimatedPlummo(
        props.color,
        props.accessories,
        catalog,
        parts,
        rig,
        id,
        true,
    ),
);
</script>
<template>
    <!-- Only repository-owned SVG layers are composed; user input never becomes markup. -->
    <!-- eslint-disable vue/no-v-html -- Only trusted repository SVG fragments are composed. -->
    <svg
        class="pointer-events-none overflow-visible"
        :data-plummo-motion="active"
        :style="{ '--plummo-idle-delay': `${idleDelay}s` }"
        :data-plummo-key="motionKey"
        viewBox="0 0 512 512"
        role="img"
        :aria-label="label"
        v-html="svg"
    />
    <!-- eslint-enable vue/no-v-html -->
</template>
