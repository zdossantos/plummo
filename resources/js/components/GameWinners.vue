<script setup lang="ts">
import PlummoAvatar from '@/components/PlummoAvatar.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { Player } from '@/types/rooms';
defineProps<{ winners: (Player & { rank?: number })[]; motionKey?: string }>();
const { t } = useTranslations('rooms');
</script>
<template>
    <div v-if="winners.length" class="podium" role="status">
        <div
            v-for="(player, index) in [winners[0], winners[1], winners[2]]"
            :key="player?.id ?? index"
            class="podium-place"
            :class="'podium-slot-' + (index + 1)"
            :style="{ order: [1, 0, 2][index] }"
            :data-testid="player ? 'winner-avatar' : undefined"
        >
            <PlummoAvatar
                v-if="player"
                :color="player.color"
                :accessories="player.accessories"
                :label="player.name"
                :motion="motionKey ? 'podium' : undefined"
                :motion-key="motionKey"
            />
            <span v-else class="podium-empty" aria-hidden="true" />
            <div class="podium-step">
                <p v-if="player" class="text-summary podium-name">
                    {{ player.name }}
                </p>
                <strong>{{ player?.rank ?? index + 1 }}</strong>
                <span v-if="player">{{ player.score }} {{ t('score') }}</span>
            </div>
        </div>
    </div>
</template>
