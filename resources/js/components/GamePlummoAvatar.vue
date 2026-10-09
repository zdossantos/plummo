<script setup lang="ts">
import PlummoAvatar from '@/components/PlummoAvatar.vue';
import { useGamePlummoMotion } from '@/composables/usePlummoMotion';
import type { GameState, Player } from '@/types/rooms';
const props = defineProps<{ game: GameState | null; player: Player }>();
const event = useGamePlummoMotion(
    () => props.game,
    () => props.player.id,
);
</script>
<template>
    <PlummoAvatar
        :color="player.color"
        :accessories="player.accessories"
        :label="player.name"
        :motion="event.motion"
        :motion-key="event.key"
    />
</template>
