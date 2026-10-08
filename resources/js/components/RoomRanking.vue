<script setup lang="ts">
import GameWinners from '@/components/GameWinners.vue';
import PagedList from '@/components/PagedList.vue';
import PlummoAvatar from '@/components/PlummoAvatar.vue';
import { useTranslations } from '@/composables/useTranslations';
import type { RoomState } from '@/types/rooms';
defineProps<{ room: RoomState }>();
const { t } = useTranslations('rooms');
</script>
<template>
    <section
        class="game-panel"
        :class="{ 'has-ranking-list': room.ranking.length > 3 }"
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
        <GameWinners :winners="room.ranking.slice(0, 3)" />
        <PagedList
            v-if="room.ranking.length > 3"
            :items="room.ranking.slice(3)"
            :row-height="80"
        >
            <template #default="{ item: player }">
                <li
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
                        <p class="font-bold text-summary">{{ player.name }}</p>
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
            </template></PagedList
        >
    </section>
</template>
