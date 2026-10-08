<script setup lang="ts">
import DrawingPlay from '@/components/DrawingPlay.vue';
import AudioClip from '@/components/AudioClip.vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { GameState, RoomState, DrawingSender } from '@/types/rooms';
const props = defineProps<{
    game: GameState;
    room: RoomState;
    seconds: number;
    phone?: boolean;
    chief?: boolean;
    busy?: boolean;
    connected?: boolean;
    sendDrawing?: DrawingSender;
}>();
const emit = defineEmits<{
    answer: [choice: number];
    control: [action: string];
}>();
const { t } = useTranslations('rooms');
const revealed = computed(
    () => props.game.type !== 'drawing' && props.game.round.correct !== null,
);
const ranking = computed(() => {
    let rank = 0;
    let last: number | null = null;
    return props.room.ranking
        .filter((p) => p.id in props.game.scores)
        .map((p) => ({ ...p, score: props.game.scores[p.id] ?? 0 }))
        .sort((a, b) => b.score - a.score || a.id - b.id)
        .map((p, i) => {
            if (p.score !== last) rank = i + 1;
            last = p.score;
            return { ...p, rank };
        });
});
</script>
<template>
    <section
        class="my-8 rounded-3xl bg-card p-6 lg:p-10"
        data-testid="game-play"
    >
        <div
            class="flex items-center justify-between gap-5 font-bold text-primary"
        >
            <p>
                {{
                    t(
                        game.type === 'drawing'
                            ? 'drawing_round'
                            : game.type === 'quiz'
                              ? 'quiz_round'
                              : 'blind_round',
                        {
                            number:
                                game.type === 'drawing'
                                    ? game.round.tour
                                    : game.round.number,
                            total: game.round.total,
                        },
                    )
                }}
            </p>
            <p
                v-if="
                    ['answer', 'selecting', 'drawing', 'resuming'].includes(
                        game.phase,
                    )
                "
                class="text-3xl tabular-nums"
                role="timer"
            >
                {{ seconds }} {{ t('seconds') }}
            </p>
        </div>
        <AudioClip
            v-if="!phone && game.type !== 'drawing' && game.round.audio"
            :src="game.round.audio"
            :playing="
                game.phase === 'answer' && seconds > 0 && connected !== false
            "
        />
        <h1
            v-if="game.phase === 'paused'"
            class="my-8 text-center text-3xl font-black"
        >
            {{ t('game_paused') }}
        </h1>
        <h1
            v-else-if="game.phase === 'resuming'"
            class="my-8 text-center text-3xl font-black"
        >
            {{ t('game_resuming') }}
        </h1>
        <template v-else-if="game.phase === 'results'">
            <div
                v-if="game.targetReached"
                class="mb-8 rounded-2xl bg-primary/10 p-5"
            >
                <h2 class="text-3xl font-black">{{ t('session_results') }}</h2>
                <p class="mt-3 text-xl font-bold">
                    {{
                        t('game_winners', {
                            names: room.ranking
                                .filter((p) => p.rank === 1)
                                .map((p) => p.name)
                                .join(', '),
                        })
                    }}
                </p>
                <p class="mt-3">{{ t('target_reached') }}</p>
            </div>
            <h1 class="mt-6 text-3xl font-black">{{ t('game_results') }}</h1>
            <p v-if="ranking.length" class="mt-4 text-2xl font-bold">
                {{
                    t('game_winners', {
                        names: ranking
                            .filter((p) => p.rank === 1)
                            .map((p) => p.name)
                            .join(', '),
                    })
                }}
            </p>
            <ol class="mt-6 space-y-3">
                <li
                    v-for="player in ranking"
                    :key="player.id"
                    class="flex justify-between rounded-xl bg-accent/30 p-4 font-bold"
                >
                    <span>{{ player.rank }} · {{ player.name }}</span
                    ><span>{{ player.score }} {{ t('score') }}</span>
                </li>
            </ol>
            <Button
                v-if="chief && phone"
                class="mt-6 w-full"
                :disabled="busy"
                @click="emit('control', 'lobby')"
                >{{ t('back_lobby') }}</Button
            >
        </template>
        <DrawingPlay
            v-else-if="game.type === 'drawing'"
            :game="game"
            :room="room"
            :phone="phone"
            :busy="busy"
            :connected="connected"
            :send="sendDrawing"
        />
        <template v-else>
            <h1 class="my-7 text-2xl font-black leading-tight lg:text-5xl">
                {{ game.round.question }}
            </h1>
            <p
                v-if="phone && !game.me?.eligible && !revealed"
                class="mb-5 font-semibold"
            >
                {{ t('next_round_wait') }}
            </p>
            <div
                class="grid gap-3"
                :class="phone ? 'grid-cols-1' : 'grid-cols-2'"
            >
                <component
                    :is="phone ? 'button' : 'div'"
                    v-for="(choice, index) in game.round.choices"
                    :key="index"
                    :disabled="
                        !phone ||
                        busy ||
                        game.phase !== 'answer' ||
                        !game.me?.eligible ||
                        game.me.answered
                    "
                    class="rounded-2xl border-2 px-5 py-5 text-left text-xl font-bold transition-colors lg:text-2xl"
                    :class="
                        game.round.correct === index
                            ? 'border-primary bg-primary text-primary-foreground'
                            : game.me?.choice === index
                              ? 'border-primary bg-accent'
                              : 'border-primary/15 bg-accent/20'
                    "
                    @click="phone && emit('answer', index)"
                >
                    <span class="mr-3 font-mono">{{
                        String.fromCharCode(65 + index)
                    }}</span
                    >{{ choice }}
                </component>
            </div>
            <p
                v-if="phone && game.me?.answered && !revealed"
                class="mt-5 font-semibold"
                role="status"
            >
                {{ t('answer_saved') }}
            </p>
            <p
                v-if="phone && revealed"
                class="mt-5 text-xl font-black"
                role="status"
            >
                {{ t('round_points', { count: game.me?.points ?? 0 }) }}
            </p>
        </template>
        <div
            v-if="chief && phone && game.phase !== 'results'"
            class="mt-6 flex flex-wrap gap-3"
        >
            <Button
                v-if="game.phase !== 'paused'"
                variant="outline"
                :disabled="busy"
                @click="emit('control', 'pause')"
                >{{ t('pause_game') }}</Button
            >
            <template v-else>
                <Button
                    :disabled="busy || game.exhausted"
                    @click="emit('control', 'resume')"
                    >{{ t('resume_game') }}</Button
                >
                <Button
                    variant="outline"
                    :disabled="busy"
                    @click="emit('control', 'stop')"
                    >{{ t('stop_game') }}</Button
                >
            </template>
        </div>
    </section>
</template>
