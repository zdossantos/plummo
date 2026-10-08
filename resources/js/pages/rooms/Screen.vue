<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import PlayerDock from '@/components/PlayerDock.vue';
import RoomRanking from '@/components/RoomRanking.vue';
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
const showRanking = ref(false);
</script>
<template>
    <Head :title="t('title')" />
    <ViewportShell>
        <template #header
            ><span class="font-bold text-primary">{{ room?.code }}</span
            ><small>{{
                room?.pointTarget === null
                    ? t('nonstop')
                    : t('point_target', { count: room?.pointTarget ?? 0 })
            }}</small
            ><button
                v-if="room?.ranking.length"
                type="button"
                class="game-action"
                :aria-expanded="showRanking"
                @click="showRanking = !showRanking"
            >
                {{ showRanking ? t('back_lobby') : t('ranking') }}
            </button></template
        >
        <div v-if="closed" class="game-panel justify-center text-center">
            <h1 class="text-4xl">{{ t('closed') }}</h1>
            <Link href="/">{{ t('create') }}</Link>
        </div>
        <template v-else>
            <p v-if="error" role="status" class="text-sm">{{ error }}</p>
            <div class="phone-scene max-w-none" data-testid="screen-content">
                <section v-if="!showRanking && !game" class="screen-lobby">
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
                            <a :href="manualUrl">{{ manualUrl }}</a>
                        </p>
                    </div>
                    <div class="flex flex-col items-center gap-3">
                        <img
                            :src="`/rooms/${room?.code}/qr`"
                            :alt="t('qr_alt')"
                        /><a :href="joinUrl" class="text-sm font-bold">{{
                            t('join')
                        }}</a>
                    </div>
                </section>
                <GamePlay
                    v-if="game && room"
                    v-show="!showRanking"
                    :game="game"
                    :room="room"
                    :seconds="seconds"
                    :connected="connected"
                />
                <RoomRanking v-if="showRanking && room" :room="room" />
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
