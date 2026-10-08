<script setup lang="ts">
import TextReader from '@/components/TextReader.vue';
import PlummoAvatar from '@/components/PlummoAvatar.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { Player } from '@/types/rooms';
defineProps<{ winners: Player[] }>();
const { t } = useTranslations('rooms');
</script>
<template>
    <div v-if="winners.length" class="winner-scene text-center" role="status">
        <p class="text-summary text-lg font-black">
            {{
                t('game_winners', {
                    names: winners.map((p) => p.name).join(', '),
                })
            }}
        </p>
        <TextReader
            :text="
                t('game_winners', {
                    names: winners.map((p) => p.name).join(', '),
                })
            "
        />
        <div class="winner-cast mt-3 flex justify-center gap-2">
            <div
                v-for="player in winners"
                :key="player.id"
                data-testid="winner-avatar"
                class="winner min-w-0 flex-1 max-w-24"
            >
                <PlummoAvatar
                    :color="player.color"
                    :accessories="player.accessories"
                    :label="player.name"
                />
                <p class="text-summary mt-1 text-xs font-bold">
                    {{ player.name }}
                </p>
            </div>
        </div>
    </div>
</template>
<style scoped>
@media (max-height: 420px) {
    .winner-scene > p {
        font-size: 14px;
        line-height: 18px;
        -webkit-line-clamp: 1;
    }
    .winner-scene > :deep(.text-reader-trigger) {
        position: absolute;
        right: 0;
        top: 0;
    }
    .winner-scene {
        position: relative;
        padding-right: 24px;
    }
    .winner-cast {
        margin-top: 4px;
    }
    .winner :deep(svg) {
        height: 44px;
        width: 100%;
    }
    .winner p {
        line-height: 14px;
        -webkit-line-clamp: 1;
    }
}
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
