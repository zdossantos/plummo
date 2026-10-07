<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import PlummoAvatar from '@/components/PlummoAvatar.vue';
import RoomHeader from '@/components/RoomHeader.vue';
import { useRoom } from '@/composables/useRoom';
import { useTranslations } from '@/composables/useTranslations';
import type { RoomState } from '@/types/rooms';
const props = defineProps<{
    room: RoomState;
    joinUrl: string;
    manualUrl: string;
}>();
const { room, closed, error } = useRoom(props.room.code, {
    room: props.room,
    me: null,
});
const { t } = useTranslations('rooms');
const slots = computed(() =>
    Array.from({ length: 8 }, (_, index) => room.value?.players[index] ?? null),
);
</script>
<template>
    <Head :title="t('title')" />
    <main
        class="mx-auto flex min-h-screen max-w-[1600px] flex-col px-8 py-8 lg:px-16 lg:py-10"
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
            <section
                class="grid flex-1 items-center gap-10 py-10 lg:grid-cols-[1fr_420px]"
            >
                <div>
                    <p class="mb-4 text-lg font-semibold text-primary">
                        {{ t('screen_intro') }}
                    </p>
                    <h1
                        class="max-w-2xl text-5xl leading-tight font-black tracking-tight xl:text-7xl"
                    >
                        {{ t('screen_title') }}
                    </h1>
                    <p
                        class="mt-6 max-w-xl text-xl leading-relaxed text-muted-foreground"
                    >
                        {{ t('invite') }}
                    </p>
                    <p class="mt-10 text-xl font-semibold">
                        {{
                            room?.players.length
                                ? t('waiting_chief')
                                : t('waiting_first')
                        }}
                    </p>
                </div>
                <div class="rounded-[2rem] bg-card p-7 text-center shadow-sm">
                    <img
                        :src="`/rooms/${room?.code}/qr`"
                        :alt="t('qr_alt')"
                        class="mx-auto w-64 rounded-2xl bg-white p-2"
                        width="256"
                        height="256"
                    />
                    <p class="mt-5 text-sm font-semibold text-muted-foreground">
                        {{ t('code') }}
                    </p>
                    <p
                        class="mt-2 font-mono text-5xl font-black tracking-[.15em]"
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
            <section
                class="rounded-3xl bg-accent/35 p-5 lg:p-7"
                :aria-label="t('players')"
            >
                <div class="mb-4 flex items-center justify-between gap-4">
                    <h2 class="text-xl font-bold">{{ t('players') }}</h2>
                    <span class="font-semibold text-muted-foreground">{{
                        t('count', { count: room?.occupied ?? 0 })
                    }}</span>
                </div>
                <div class="grid grid-cols-4 gap-3 lg:grid-cols-8">
                    <article
                        v-for="(player, index) in slots"
                        :key="player?.id ?? 'empty-' + index"
                        class="min-w-0 rounded-2xl p-3 text-center"
                        :class="
                            player
                                ? 'bg-card'
                                : 'border-2 border-dashed border-primary/15'
                        "
                    >
                        <template v-if="player">
                            <PlummoAvatar
                                :color="player.color"
                                :accessories="player.accessories"
                                :label="player.name"
                                class="mx-auto w-full max-w-32"
                            />
                            <p class="mt-1 font-bold break-words">
                                {{ player.name }}
                            </p>
                            <p
                                v-if="player.id === room?.chiefId"
                                class="mt-1 text-xs font-bold text-primary"
                            >
                                {{ t('chief') }}
                            </p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ player.score }} {{ t('score') }}
                            </p>
                            <p
                                v-if="player.status === 'disconnected'"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                {{ t('disconnected') }}
                            </p>
                        </template>
                        <div
                            v-else
                            class="flex h-full min-h-28 items-center justify-center text-xs text-muted-foreground"
                        >
                            {{ t('empty_slot') }}
                        </div>
                    </article>
                </div>
            </section>
        </template>
    </main>
</template>
