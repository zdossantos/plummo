<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import PlayerDock from '@/components/PlayerDock.vue';
import RoomRanking from '@/components/RoomRanking.vue';
import GamePlay from '@/components/GamePlay.vue';
import RoomHeader from '@/components/RoomHeader.vue';
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
    <main
        class="mx-auto flex min-h-screen max-w-[1600px] flex-col px-8 py-8 lg:px-10 lg:py-6"
    >
        <RoomHeader />
        <div v-if="closed" class="my-auto py-20 text-center">
            <h1 class="text-4xl font-black">{{ t('closed') }}</h1>
            <Link
                href="/"
                class="mt-8 inline-block rounded-xl bg-primary px-6 py-4 font-bold text-primary-foreground"
                >{{ t('create') }}</Link
            >
        </div>
        <template v-else>
            <p v-if="error" role="status" class="mt-4 rounded-xl bg-accent p-3">
                {{ error }}
            </p>
            <div
                v-if="room?.ranking.length"
                class="mt-5 flex items-center justify-between gap-4"
            >
                <p class="font-semibold text-primary">
                    {{
                        room.pointTarget === null
                            ? t('nonstop')
                            : t('point_target', { count: room.pointTarget })
                    }}
                </p>
                <button
                    class="rounded-xl bg-accent px-4 py-3 font-bold"
                    :aria-expanded="showRanking"
                    @click="showRanking = !showRanking"
                >
                    {{ showRanking ? t('back_lobby') : t('ranking') }}
                </button>
            </div>
            <div
                class="mt-6 grid flex-1 items-start gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(0,640px)]"
            >
                <div data-testid="screen-content">
                    <section
                        v-if="!showRanking && !game"
                        class="grid items-center gap-8 py-6 md:grid-cols-[1fr_220px]"
                    >
                        <div>
                            <p
                                class="mb-4 text-base font-semibold text-primary"
                            >
                                {{ t('screen_intro') }}
                            </p>
                            <h1
                                class="max-w-2xl text-4xl leading-tight font-black tracking-tight 2xl:text-5xl"
                            >
                                {{ t('screen_title') }}
                            </h1>
                            <p
                                class="mt-6 max-w-xl text-lg leading-relaxed text-muted-foreground"
                            >
                                {{ t('invite') }}
                            </p>
                            <p class="mt-8 text-lg font-semibold">
                                {{
                                    room?.players.length
                                        ? t('waiting_chief')
                                        : t('waiting_first')
                                }}
                            </p>
                        </div>
                        <div
                            class="rounded-[2rem] bg-card p-5 text-center shadow-sm"
                        >
                            <img
                                :src="`/rooms/${room?.code}/qr`"
                                :alt="t('qr_alt')"
                                class="mx-auto w-48 rounded-2xl bg-white p-2"
                                width="256"
                                height="256"
                            />
                            <p
                                class="mt-5 text-sm font-semibold text-muted-foreground"
                            >
                                {{ t('code') }}
                            </p>
                            <p
                                class="mt-2 font-mono text-3xl font-black tracking-[.15em]"
                                data-testid="room-code"
                            >
                                {{ room?.code }}
                            </p>
                            <p class="mt-5 text-sm text-muted-foreground">
                                {{ t('manual') }}
                            </p>
                            <p class="mt-2 text-sm font-bold break-all">
                                {{ manualUrl }}
                            </p>
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
                    <RoomRanking v-if="room && showRanking" :room="room" />
                </div>
                <PlayerDock
                    class="screen-players"
                    v-if="room"
                    :room="room"
                    :game="game"
                    :server-now="serverNow"
                />
            </div>
        </template>
    </main>
</template>

<style scoped>
@media (min-width: 80rem) {
    .screen-players {
        position: fixed;
        right: max(2.5rem, calc((100vw - 1600px) / 2 + 2.5rem));
        bottom: 1.5rem;
        width: 640px;
    }
}
</style>
