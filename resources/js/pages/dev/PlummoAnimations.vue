<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import { Shuffle } from '@lucide/vue';
import ViewportShell from '@/components/ViewportShell.vue';
import PlummoAvatar from '@/components/PlummoAvatar.vue';
import { useTranslations } from '@/composables/useTranslations';
import { randomAppearance } from '@/lib/plummo';
import type { PlummoMotion } from '@/lib/plummo-motion';
import type { Catalog } from '@/types/rooms';

const props = defineProps<{ catalog: Catalog }>();
const { t } = useTranslations('plummo');
const motions: PlummoMotion[] = [
    'idle',
    'answer',
    'points',
    'resume',
    'podium',
    'wiggle',
    'stretch',
    'hello',
    'boing',
];
const motion = ref<PlummoMotion>('idle');
const sequence = ref(0);
const reduced = useMediaQuery('(prefers-reduced-motion: reduce)');
const cast = ref(
    Array.from({ length: 8 }, () => randomAppearance(props.catalog)),
);
function play(next: PlummoMotion) {
    motion.value = next;
    sequence.value++;
}
function shuffle() {
    cast.value = cast.value.map(() => randomAppearance(props.catalog));
    sequence.value++;
}
</script>

<template>
    <Head :title="t('motion_lab.title')" />
    <ViewportShell display-only>
        <div class="motion-lab">
            <header class="motion-lab-heading">
                <h1>{{ t('motion_lab.title') }}</h1>
                <p>{{ t('motion_lab.note') }}</p>
            </header>
            <div class="motion-lab-cast">
                <button
                    v-for="(plummo, index) in cast"
                    :key="index"
                    type="button"
                    :aria-label="t('motion_lab.shuffle') + ' · ' + (index + 1)"
                    @click="cast[index] = randomAppearance(catalog)"
                >
                    <PlummoAvatar
                        :color="plummo.color"
                        :accessories="plummo.accessories"
                        :motion="motion"
                        :motion-key="String(sequence)"
                    />
                </button>
            </div>
            <p v-if="reduced" class="motion-lab-notice" role="status">
                {{ t('motion_lab.reduced') }}
            </p>
            <div class="motion-lab-controls">
                <button
                    v-for="option in motions"
                    :key="option"
                    type="button"
                    class="game-action"
                    :aria-pressed="motion === option"
                    @click="play(option)"
                >
                    {{ t('motion_lab.' + option) }}
                </button>
                <button type="button" class="game-action" @click="shuffle">
                    <Shuffle aria-hidden="true" />{{ t('motion_lab.shuffle') }}
                </button>
            </div>
        </div>
    </ViewportShell>
</template>

<style scoped>
.motion-lab {
    height: 100%;
    min-height: 0;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.motion-lab-heading h1 {
    font:
        600 clamp(20px, 3vw, 36px) / 1.1 Fredoka,
        sans-serif;
    color: var(--primary);
}
.motion-lab-heading p,
.motion-lab-notice {
    font-size: 12px;
    line-height: 1.3;
    margin-top: 4px;
}
.motion-lab-cast {
    flex: 1;
    min-height: 0;
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    grid-template-rows: repeat(2, minmax(0, 1fr));
    gap: 12px;
}
.motion-lab-cast button {
    border: 0;
    padding: 0;
    min-height: 0;
    min-width: 0;
    background: transparent;
    box-shadow: none;
    display: flex;
    align-items: center;
    justify-content: center;
}
.motion-lab-cast button:focus-visible {
    outline: 3px solid var(--primary);
    outline-offset: 2px;
    border-radius: 20px;
}
.motion-lab-cast svg {
    width: 100%;
    height: 100%;
    max-height: 250px;
    overflow: visible;
}
.motion-lab-controls {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 10px;
    padding-bottom: 8px;
}
.motion-lab-controls button {
    min-width: 0;
    min-height: 44px;
    font-size: 13px;
    padding: 6px;
}
.motion-lab-controls button[aria-pressed='true'] {
    background: var(--primary);
}
.motion-lab-controls svg {
    width: 16px;
    height: 16px;
}
@media (max-width: 600px) and (min-height: 421px) {
    .motion-lab-cast {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        grid-template-rows: repeat(4, minmax(0, 1fr));
        gap: 4px;
    }
    .motion-lab-controls {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .motion-lab-controls button:last-child {
        grid-column: 1 / -1;
    }
}
@media (max-height: 420px) {
    .motion-lab-heading p {
        display: none;
    }
    .motion-lab {
        gap: 6px;
    }
    .motion-lab-controls {
        gap: 6px;
    }
}
@media (max-width: 600px) and (max-height: 650px) {
    .motion-lab-cast {
        grid-template-columns: repeat(4, minmax(0, 1fr));
        grid-template-rows: repeat(2, minmax(0, 1fr));
    }
}
</style>
