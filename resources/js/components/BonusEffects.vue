<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import PlummoAvatar from '@/components/PlummoAvatar.vue';
import BonusIcon from '@/components/BonusIcon.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { BonusEffect, RoomState } from '@/types/rooms';
const props = defineProps<{
    effects: BonusEffect[];
    room: RoomState;
    canvas?: boolean;
    now?: number;
}>();
const { t } = useTranslations('rooms');
const has = (kind: string) => props.effects.some((e) => e.kind === kind);
const visitor = computed(() =>
    props.effects.find((e) =>
        ['squatter', 'photobomb', 'singer', 'strike'].includes(e.kind),
    ),
);
const actor = computed(() =>
    props.room.players.find((p) => p.id === visitor.value?.actorId),
);
const popped = ref(new Set<number>());
watch(
    () => props.effects.find((e) => e.kind === 'bubbles')?.id,
    () => {
        popped.value = new Set();
    },
);
const labels = computed(() =>
    props.effects
        .filter(
            (e) =>
                ![
                    'squatter',
                    'photobomb',
                    'stamp',
                    'bubbles',
                    'bolt',
                    'scratch',
                    'speed',
                    'singer',
                    'mirror',
                ].includes(e.kind),
        )
        .slice(0, 3),
);
function remaining(e: BonusEffect) {
    return e.expiresAt === null
        ? null
        : Math.max(
              0,
              Math.ceil(e.expiresAt - (props.now ?? Date.now() / 1000)),
          );
}
</script>
<template>
    <div
        v-if="!canvas && has('bolt')"
        class="bonus-blackout"
        aria-hidden="true"
    >
        <BonusIcon kind="bolt" />
    </div>
    <div
        v-if="!canvas && visitor && actor"
        :key="visitor.id"
        :class="[
            'bonus-visitor',
            'bonus-visitor-' + visitor.kind,
            { 'bonus-squatter': visitor.kind === 'squatter' },
        ]"
        aria-hidden="true"
    >
        <PlummoAvatar
            :color="actor.color"
            :accessories="actor.accessories"
            motion="hello"
            :motion-key="visitor.id"
        />
        <span v-if="visitor.kind === 'photobomb'" class="bonus-photo-flash"
            >✦</span
        >
        <span v-if="visitor.kind === 'singer'" class="bonus-music-notes"
            >♪ ♫</span
        >
    </div>
    <div v-if="canvas && has('stamp')" class="bonus-stamp" aria-hidden="true">
        <svg viewBox="0 0 100 100">
            <circle
                cx="50"
                cy="50"
                r="43"
                fill="none"
                stroke="currentColor"
                stroke-width="4"
                stroke-dasharray="5 3"
            />
            <path
                d="M30 58 Q50 78 70 58 M32 40h1 M66 40h1"
                fill="none"
                stroke="currentColor"
                stroke-width="7"
                stroke-linecap="round"
            />
        </svg>
    </div>
    <div v-if="!canvas && has('bubbles')" class="bonus-bubbles">
        <template v-for="n in 6" :key="n"
            ><button
                v-if="!popped.has(n)"
                type="button"
                :style="{ '--bubble-n': n }"
                :aria-label="t('bonus_pop')"
                @click="popped.add(n)"
            >
                <span aria-hidden="true" /></button
            ><span
                v-else
                class="bonus-bubble-pop"
                :style="{ '--bubble-n': n }"
                aria-hidden="true"
                >✦</span
            ></template
        >
    </div>
    <div
        v-if="!canvas && labels.length"
        class="bonus-effect-ribbon"
        role="status"
        aria-live="polite"
    >
        <span
            v-for="effect in labels"
            :key="effect.id"
            :class="['bonus-effect-token', 'bonus-token-' + effect.kind]"
        >
            <BonusIcon :kind="effect.kind" /><small>{{
                t('bonus_' + effect.kind)
            }}</small
            ><b v-if="remaining(effect) !== null">{{ remaining(effect) }}</b>
        </span>
    </div>
</template>
