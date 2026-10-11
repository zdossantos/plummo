<script setup lang="ts">
import { Sparkles } from '@lucide/vue';
import { onUnmounted, ref, watch } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import { PhonePointsTracker } from '@/lib/phone-points';
import type { GameState } from '@/types/rooms';
const props = defineProps<{ game: GameState | null; connected: boolean }>();
const { t } = useTranslations('rooms');
const tracker = new PhonePointsTracker();
const gain = ref<number | null>(null);
const sequence = ref(0);
let context = '';
let timer: ReturnType<typeof setTimeout> | undefined;
watch(
    [() => props.game, () => props.connected],
    () => {
        const nextContext = props.game
            ? `${props.game.id}:${props.game.round.number}`
            : '';
        if (nextContext !== context || !props.connected) {
            clearTimeout(timer);
            gain.value = null;
        }
        context = nextContext;
        const points = tracker.observe(props.game, props.connected);
        if (points === null) return;
        gain.value = points;
        sequence.value++;
        clearTimeout(timer);
        timer = setTimeout(() => {
            gain.value = null;
        }, 2400);
    },
    { immediate: true },
);
onUnmounted(() => clearTimeout(timer));
</script>
<template>
    <div
        v-if="gain !== null"
        :key="sequence"
        class="phone-points-feedback"
        role="status"
        data-testid="phone-points-gain"
    >
        <Sparkles aria-hidden="true" />
        <span
            ><small>{{ t('points_earned') }}</small
            ><strong>{{ t('points_gain', { count: gain }) }}</strong></span
        >
    </div>
</template>
