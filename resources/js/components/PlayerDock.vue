<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';
import PlummoAvatar from '@/components/PlummoAvatar.vue';
import { roundCelebration } from '@/lib/celebration';
import { useTranslations } from '@/composables/useTranslations';
import type { GameState, RoomState } from '@/types/rooms';
const props = defineProps<{
    room: RoomState;
    game: GameState | null;
    serverNow: number;
}>();
const { t } = useTranslations('rooms');
const slots = computed(() =>
    Array.from({ length: 8 }, (_, index) => props.room.players[index] ?? null),
);
const gains = ref<Record<number, number>>({});
let lastCelebrated = '';
let timer: ReturnType<typeof setTimeout> | undefined;
watch(
    () => props.game,
    (game) => {
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
        class="mt-auto w-full max-w-3xl self-end rounded-3xl bg-accent/35 p-4"
        :aria-label="t('players')"
        data-testid="player-dock"
    >
        <div class="mb-2 flex items-center justify-between gap-4">
            <h2 class="font-bold">{{ t('players') }}</h2>
            <span class="text-sm text-muted-foreground">{{
                t('count', { count: room.occupied })
            }}</span>
        </div>
        <div class="grid grid-cols-4 gap-x-3 gap-y-2">
            <article
                v-for="(player, index) in slots"
                :key="player?.id ?? 'empty-' + index"
                class="flex min-w-0 flex-col text-center"
            >
                <template v-if="player">
                    <div
                        class="flex min-h-24 flex-1 items-end justify-center pb-2"
                    >
                        <p
                            v-if="
                                player.chat && player.chat.expiresAt > serverNow
                            "
                            data-testid="chat-bubble"
                            class="chat-bubble w-full rounded-2xl bg-card px-2 py-2 text-xs leading-[1.15] break-words shadow-sm"
                            :aria-label="t('chat_from', { name: player.name })"
                        >
                            {{ player.chat.message }}
                        </p>
                    </div>
                    <div
                        class="relative mx-auto w-14"
                        :class="{ 'plummo-hop': gains[player.id] }"
                    >
                        <PlummoAvatar
                            :color="player.color"
                            :accessories="player.accessories"
                            :label="player.name"
                        />
                        <p
                            v-if="gains[player.id]"
                            class="points-rise absolute -top-2 left-1/2 z-10 -translate-x-1/2 rounded-full bg-primary px-2 py-1 text-sm font-black whitespace-nowrap text-primary-foreground"
                            role="status"
                        >
                            +{{ gains[player.id] }} {{ t('score') }}
                        </p>
                    </div>
                    <p class="truncate text-sm font-bold" :title="player.name">
                        {{ player.name }}
                        <span
                            v-if="player.id === room.chiefId"
                            class="text-primary"
                            >· {{ t('chief') }}</span
                        >
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ player.score }} {{ t('score') }}
                    </p>
                    <p
                        v-if="player.status === 'disconnected'"
                        class="text-xs text-muted-foreground"
                    >
                        {{ t('disconnected') }}
                    </p>
                </template>
                <div
                    v-else
                    class="flex h-full min-h-32 items-end justify-center rounded-2xl border border-dashed border-primary/15 p-3 text-xs text-muted-foreground"
                >
                    {{ t('empty_slot') }}
                </div>
            </article>
        </div>
    </section>
</template>
<style scoped>
.chat-bubble {
    animation: bubble-in 0.18s ease-out;
}
.plummo-hop {
    animation: plummo-hop 0.6s ease-out 2;
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
@keyframes plummo-hop {
    0%,
    100% {
        transform: translateY(0);
    }
    45% {
        transform: translateY(-12px);
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
    .plummo-hop,
    .points-rise {
        animation: none;
    }
}
</style>
