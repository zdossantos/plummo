<script setup lang="ts">
import PlummoAvatar from '@/components/PlummoAvatar.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { Player } from '@/types/rooms';
defineProps<{ winners: Player[] }>();
const { t } = useTranslations('rooms');
</script>
<template>
    <div
        v-if="winners.length"
        class="my-6 rounded-2xl bg-primary/10 p-5 text-center"
        role="status"
    >
        <p class="text-2xl font-black">
            {{
                t('game_winners', {
                    names: winners.map((p) => p.name).join(', '),
                })
            }}
        </p>
        <div class="mt-4 flex flex-wrap justify-center gap-6">
            <div
                v-for="player in winners"
                :key="player.id"
                data-testid="winner-avatar"
                class="winner w-24"
            >
                <PlummoAvatar
                    :color="player.color"
                    :accessories="player.accessories"
                    :label="player.name"
                />
                <p class="mt-2 font-bold break-words">{{ player.name }}</p>
            </div>
        </div>
    </div>
</template>
<style scoped>
.winner {
    animation: winner-cheer 0.7s ease-in-out 3;
}
@keyframes winner-cheer {
    0%,
    100% {
        transform: translateY(0) rotate(0);
    }
    35% {
        transform: translateY(-10px) rotate(-4deg);
    }
    70% {
        transform: translateY(-4px) rotate(4deg);
    }
}
@media (prefers-reduced-motion: reduce) {
    .winner {
        animation: none;
    }
}
</style>
