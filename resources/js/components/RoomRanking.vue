<script setup lang="ts">
import PlummoAvatar from '@/components/PlummoAvatar.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { RoomState } from '@/types/rooms';
defineProps<{ room: RoomState }>();
const { t } = useTranslations('rooms');
</script>
<template>
    <section
        class="mt-6 rounded-3xl bg-card p-5 lg:p-7"
        :aria-label="t('ranking')"
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-bold">{{ t('ranking') }}</h2>
            <p class="font-semibold text-primary">
                {{
                    room.pointTarget === null
                        ? t('nonstop')
                        : t('point_target', { count: room.pointTarget })
                }}
            </p>
        </div>
        <ol class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <li
                v-for="player in room.ranking"
                :key="player.id"
                class="flex items-center gap-3 rounded-2xl bg-accent/30 px-4 py-3"
            >
                <span class="min-w-7 text-xl font-black">{{
                    player.rank
                }}</span>
                <PlummoAvatar
                    :color="player.color"
                    :accessories="player.accessories"
                    :label="player.name"
                    class="w-12 shrink-0"
                />
                <div class="min-w-0 flex-1">
                    <p class="font-bold break-words">{{ player.name }}</p>
                    <p
                        v-if="player.status === 'left'"
                        class="text-xs text-muted-foreground"
                    >
                        {{ t('ranking_left') }}
                    </p>
                </div>
                <span class="shrink-0 font-bold"
                    >{{ player.score }} {{ t('score') }}</span
                >
            </li>
        </ol>
    </section>
</template>
