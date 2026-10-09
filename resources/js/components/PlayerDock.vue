<script setup lang="ts">
import { onUnmounted, ref, watch } from 'vue';
import GamePlummoAvatar from '@/components/GamePlummoAvatar.vue';
import { PlummoSnapshotBaseline } from '@/lib/plummo-motion';
import { roundCelebration } from '@/lib/celebration';
import { useTranslations } from '@/composables/useTranslations';
import type { GameState, RoomState } from '@/types/rooms';
const props = defineProps<{
    room: RoomState;
    game: GameState | null;
    serverNow: number;
    connected: boolean;
}>();
const { t } = useTranslations('rooms');
const gains = ref<Record<number, number>>({});
let lastCelebrated = '';
const baseline = new PlummoSnapshotBaseline();
let timer: ReturnType<typeof setTimeout> | undefined;
watch(
    [() => props.game, () => props.connected],
    ([game, connected]) => {
        const state = baseline.observe(game, connected);
        if (state === 'waiting' || !game) {
            gains.value = {};
            clearTimeout(timer);
            return;
        }
        if (state === 'baseline') {
            lastCelebrated =
                game.phase === 'reveal'
                    ? `${game.id}:${game.round.number}`
                    : '';
            return;
        }
        const celebration = roundCelebration(game, lastCelebrated);
        if (!celebration) return;
        lastCelebrated = celebration.key;
        gains.value = celebration.gains;
        clearTimeout(timer);
        timer = setTimeout(() => {
            gains.value = {};
        }, 2400);
    },
    { immediate: true },
);
onUnmounted(() => clearTimeout(timer));
</script>
<template>
    <section
        class="player-cast"
        :aria-label="t('players')"
        data-testid="player-dock"
    >
        <article
            v-for="player in room.players"
            :key="player.id"
            class="player-character"
        >
            <p
                v-if="player.chat && player.chat.expiresAt > serverNow"
                data-testid="chat-bubble"
                class="chat-bubble rounded-2xl bg-white px-2 py-2 leading-tight break-words"
                :aria-label="t('chat_from', { name: player.name })"
            >
                {{ player.chat.message }}
            </p>
            <p class="player-name">
                {{ player.name
                }}<span v-if="player.id === room.chiefId" class="text-primary">
                    ♛</span
                >
            </p>
            <p class="player-score">{{ player.score }} {{ t('score') }}</p>
            <p v-if="player.status === 'disconnected'" class="text-[10px]">
                {{ t('disconnected') }}
            </p>
            <div class="relative">
                <GamePlummoAvatar
                    :game="game"
                    :player="player"
                    :connected="connected"
                />
                <p
                    v-if="gains[player.id]"
                    class="points-rise absolute -top-2 left-1/2 z-10 -translate-x-1/2 rounded-full bg-primary px-2 py-1 text-sm font-black whitespace-nowrap text-primary-foreground"
                    role="status"
                >
                    +{{ gains[player.id] }} {{ t('score') }}
                </p>
            </div>
        </article>
    </section>
</template>
<style scoped>
.chat-bubble {
    animation: bubble-in 0.18s ease-out;
}
.points-rise {
    animation: points-rise 2.4s ease-out;
}
@keyframes bubble-in {
    from {
        opacity: 0;
        transform: translateY(4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
@keyframes points-rise {
    0% {
        opacity: 0;
        margin-top: 8px;
    }
    20%,
    80% {
        opacity: 1;
    }
    100% {
        opacity: 0;
        margin-top: -14px;
    }
}
@media (prefers-reduced-motion: reduce) {
    .chat-bubble,
    .points-rise {
        animation: none;
    }
}
</style>
