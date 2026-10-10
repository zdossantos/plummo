<script setup lang="ts">
import { provide, ref } from 'vue';
import ScreenSound from '@/components/ScreenSound.vue';
import { soundSettings } from '@/lib/soundscape';
import { Head } from '@inertiajs/vue3';
import PlayerDock from '@/components/PlayerDock.vue';
import RoomNotice from '@/components/RoomNotice.vue';
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
const soundEnabled = ref(false);
const soundVolume = ref(0.6);
const media = ref<HTMLAudioElement>();
const audioBlocked = ref(false);
provide(soundSettings, {
    enabled: soundEnabled,
    volume: soundVolume,
    media,
    blocked: audioBlocked,
});
const { room, game, seconds, serverNow, closed, error, connected } = useRoom(
    props.room.code,
    {
        room: props.room,
        me: null,
        game: props.game,
        serverTime: props.serverTime,
    },
    false,
    () => soundEnabled.value && !audioBlocked.value && soundVolume.value > 0,
);
const { t } = useTranslations('rooms');
</script>
<template>
    <Head :title="t('title')" />
    <ViewportShell
        class="display-screen"
        :data-lobby="!game || undefined"
        display-only
    >
        <RoomNotice :message="error" />
        <template #header
            ><span class="screen-session"
                ><span class="font-bold text-primary">{{ room?.code }}</span
                ><img
                    v-if="room && !closed"
                    data-testid="screen-header-qr"
                    :src="`/rooms/${room.code}/qr`"
                    :alt="t('qr_alt')" /></span
            ><small>{{
                room?.pointTarget === null
                    ? t('nonstop')
                    : t('point_target', { count: room?.pointTarget ?? 0 })
            }}</small
            ><ScreenSound
                v-model:enabled="soundEnabled"
                v-model:volume="soundVolume"
                :room="room"
                :game="game"
                :closed="closed"
                :server-now="serverNow"
                :connected="connected"
            />
            <aside
                v-if="room && !closed && !game"
                class="screen-join"
                data-testid="screen-join"
            >
                <img :src="`/rooms/${room.code}/qr`" :alt="t('qr_alt')" />
                <div v-if="!game" class="screen-join-address">
                    <p class="screen-code">{{ room.code }}</p>
                    <p class="screen-manual-label">{{ t('manual') }}</p>
                    <p class="screen-manual-url">{{ manualUrl }}</p>
                </div>
            </aside>
        </template>
        <div v-if="closed" class="game-panel justify-center text-center">
            <h1 class="text-4xl">{{ t('closed') }}</h1>
        </div>
        <template v-else>
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
                    </div>
                </section>
                <GamePlay
                    v-if="game && room"
                    :game="game"
                    :room="room"
                    :seconds="seconds"
                    :server-now="serverNow"
                    :connected="connected"
                />
            </div>
        </template>
        <template #footer
            ><PlayerDock
                v-if="room && !closed"
                :connected="connected"
                :room="room"
                :game="game"
                :server-now="serverNow"
        /></template>
    </ViewportShell>
</template>
