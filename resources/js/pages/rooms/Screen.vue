<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PlayerDock from '@/components/PlayerDock.vue';
import GamePlay from '@/components/GamePlay.vue';
import ViewportShell from '@/components/ViewportShell.vue';
import { useRoom } from '@/composables/useRoom';
import { useTranslations } from '@/composables/useTranslations';
import type { GameState, RoomState } from '@/types/rooms';
const props = defineProps<{
    room: RoomState;
    game: GameState | null;
    serverTime: number;
    joinUrl: string;
    manualUrl: string;
}>();
const { room, game, seconds, serverNow, closed, error, connected } = useRoom(
    props.room.code,
    {
        room: props.room,
        me: null,
        game: props.game,
        serverTime: props.serverTime,
    },
);
const { t } = useTranslations('rooms');
</script>
<template>
    <Head :title="t('title')" />
    <ViewportShell class="display-screen" display-only>
        <template #header
            ><span class="font-bold text-primary">{{ room?.code }}</span
            ><small>{{
                room?.pointTarget === null
                    ? t('nonstop')
                    : t('point_target', { count: room?.pointTarget ?? 0 })
            }}</small></template
        >
        <div v-if="closed" class="game-panel justify-center text-center">
            <h1 class="text-4xl">{{ t('closed') }}</h1>
        </div>
        <template v-else>
            <p v-if="error" role="status" class="text-sm">{{ error }}</p>
            <div class="phone-scene max-w-none" data-testid="screen-content">
                <section v-if="!game" class="screen-lobby">
                    <div>
                        <p class="mb-4 font-bold text-primary">
                            {{ t('screen_intro') }}
                        </p>
                        <h1>{{ t('screen_title') }}</h1>
                        <p class="my-4 text-lg text-muted-foreground">
                            {{ t('invite') }}
                        </p>
                        <p class="screen-code">{{ room?.code }}</p>
                        <p class="mt-3 text-sm">
                            {{ t('manual') }}
                            <span>{{ manualUrl }}</span>
                        </p>
                    </div>
                    <div class="flex flex-col items-center gap-3">
                        <img
                            :src="`/rooms/${room?.code}/qr`"
                            :alt="t('qr_alt')"
                        /><span class="text-sm font-bold">{{ t('join') }}</span>
                    </div>
                </section>
                <GamePlay
                    v-if="game && room"
                    :game="game"
                    :room="room"
                    :seconds="seconds"
                    :connected="connected"
                />
            </div>
        </template>
        <template #footer
            ><PlayerDock
                v-if="room && !closed"
                :room="room"
                :game="game"
                :server-now="serverNow"
        /></template>
    </ViewportShell>
</template>
