<script setup lang="ts">
import { computed } from 'vue';
import PlummoAvatar from '@/components/PlummoAvatar.vue';
import BonusIcon from '@/components/BonusIcon.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { BonusEffect, RoomState } from '@/types/rooms';
const props = defineProps<{
    effects: BonusEffect[];
    room: RoomState;
    canvas?: boolean;
}>();
const { t } = useTranslations('rooms');
const squatter = computed(() =>
    props.effects.find((effect) => effect.kind === 'squatter'),
);
const actor = computed(() =>
    props.room.players.find((player) => player.id === squatter.value?.actorId),
);
</script>
<template>
    <div
        v-if="!canvas && effects.some((effect) => effect.kind === 'bolt')"
        class="bonus-blackout"
        aria-hidden="true"
    >
        <BonusIcon kind="bolt" />
    </div>
    <div
        v-if="!canvas && squatter && actor"
        :key="squatter.id"
        class="bonus-squatter"
        aria-hidden="true"
    >
        <PlummoAvatar :color="actor.color" :accessories="actor.accessories" />
    </div>
    <div
        v-if="canvas && effects.some((effect) => effect.kind === 'stamp')"
        class="bonus-stamp"
        aria-hidden="true"
    >
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
    <p
        v-if="
            !canvas &&
            effects.some(
                (effect) =>
                    effect.kind === 'accent' || effect.kind === 'sneeze',
            )
        "
        class="bonus-warning"
        role="status"
    >
        {{ t('bonus_phrase_warning') }}
    </p>
</template>
