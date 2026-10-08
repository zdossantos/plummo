<script setup lang="ts">
import PagedList from '@/components/PagedList.vue';
import TextReader from '@/components/TextReader.vue';
import PlummoAvatar from '@/components/PlummoAvatar.vue';
import GameWinners from '@/components/GameWinners.vue';
import PhrasePlay from '@/components/PhrasePlay.vue';
import DrawingPlay from '@/components/DrawingPlay.vue';
import AnswerTexture from '@/components/AnswerTexture.vue';
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
    sendPhrase?: DrawingSender;
}>();
const emit = defineEmits<{
    answer: [choice: number];
    control: [action: string];
}>();
const { t } = useTranslations('rooms');
const revealed = computed(
    () =>
        ['quiz', 'blind_test'].includes(props.game.type) &&
        'correct' in props.game.round &&
        props.game.round.correct !== null,
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
        class="game-panel game-play"
        :class="{
            'game-results-scene': game.phase === 'results',
            'has-ranking-list': phone && ranking.length > 3,
        }"
        data-testid="game-play"
    >
        <div
            class="flex items-center justify-between gap-5 font-bold text-primary"
        >
            <p>
                {{
                    t(
                        game.type === 'phrase'
                            ? 'phrase_round'
                            : game.type === 'drawing'
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
                    [
                        'answer',
                        'selecting',
                        'drawing',
                        'writing',
                        'presenting',
                        'voting',
                    ].includes(game.phase)
                "
                class="game-timer tabular-nums"
                role="timer"
            >
                {{ seconds }}
            </p>
        </div>
        <AudioClip
            v-if="!phone && game.type === 'blind_test' && game.round.audio"
            :src="game.round.audio"
            :playing="
                game.phase === 'answer' && seconds > 0 && connected !== false
            "
        />
        <PhrasePlay
            v-if="
                game.type === 'phrase' &&
                !['results', 'paused', 'resuming'].includes(game.phase)
            "
            :game="game"
            :room="room"
            :phone="phone"
            :busy="busy"
            :connected="connected"
            :send="sendPhrase"
        />
        <h1
            v-if="game.phase === 'paused'"
            class="my-auto text-center text-3xl font-black"
        >
            {{ t('game_paused') }}
        </h1>
        <div
            v-else-if="game.phase === 'resuming'"
            class="resume-scene"
            role="status"
        >
            <h1>{{ t('game_resuming') }}</h1>
            <strong :key="seconds" class="resume-count" role="timer">{{
                seconds
            }}</strong>
        </div>
        <template v-else-if="game.phase === 'results'">
            <h1 class="text-2xl">{{ t('game_results') }}</h1>
            <p
                v-if="ranking.filter((p) => p.rank === 1).length === 1"
                class="results-congratulations text-summary"
            >
                {{
                    t('game_winners', {
                        names: ranking
                            .filter((p) => p.rank === 1)
                            .map((p) => p.name)
                            .join(', '),
                    })
                }}
            </p>
            <GameWinners :winners="ranking.slice(0, 3)" />
            <p v-if="game.targetReached" class="text-sm">
                {{ t('session_results') }}
            </p>
            <PagedList
                v-if="phone && ranking.length > 3"
                :items="ranking.slice(3)"
                :row-height="40"
            >
                <template #default="{ item: player }">
                    <div class="results-row flex items-center gap-2 font-bold">
                        <PlummoAvatar
                            :color="player.color"
                            :accessories="player.accessories"
                            :label="player.name"
                            class="w-7 shrink-0"
                        />
                        <span class="text-summary"
                            >{{ player.rank }} · {{ player.name }}</span
                        >
                        <span>{{ player.score }} {{ t('score') }}</span>
                    </div>
                </template>
            </PagedList>
            <Button
                v-if="chief && phone"
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
        <template v-else-if="game.type !== 'phrase'">
            <div class="game-question">
                <h1 class="text-summary text-xl lg:text-3xl">
                    {{ game.round.question }}
                </h1>
                <TextReader v-if="phone" :text="game.round.question" />
            </div>
            <p v-if="phone && !game.me?.eligible && !revealed" class="text-sm">
                {{ t('next_round_wait') }}
            </p>
            <div class="game-choices">
                <div
                    v-for="(choice, index) in game.round.choices"
                    :key="index"
                    class="game-choice"
                    :class="{
                        correct: game.round.correct === index,
                        wrong:
                            game.round.correct !== null &&
                            game.me?.choice === index &&
                            game.round.correct !== index,
                        chosen: game.me?.choice === index,
                    }"
                >
                    <button
                        v-if="phone"
                        class="choice-face"
                        type="button"
                        :disabled="
                            busy ||
                            game.phase !== 'answer' ||
                            !game.me?.eligible ||
                            game.me.answered
                        "
                        @click="emit('answer', index)"
                    >
                        <AnswerTexture :variant="index % 4" />
                        <span class="choice-letter">{{
                            String.fromCharCode(65 + index)
                        }}</span
                        ><span class="text-summary">{{ choice }}</span>
                    </button>
                    <p v-else class="choice-face font-bold">
                        <AnswerTexture :variant="index % 4" />
                        <span class="choice-letter">{{
                            String.fromCharCode(65 + index)
                        }}</span
                        ><span class="text-summary">{{ choice }}</span>
                    </p>
                    <TextReader
                        v-if="phone"
                        :text="choice"
                        :label="String.fromCharCode(65 + index)"
                    />
                </div>
            </div>
            <p
                v-if="phone && game.me?.answered && !revealed"
                class="text-sm font-semibold"
                role="status"
            >
                {{ t('answer_saved') }}
            </p>
            <p
                v-if="phone && revealed"
                class="text-sm font-black"
                role="status"
            >
                {{ t('round_points', { count: game.me?.points ?? 0 }) }}
            </p>
        </template>
        <div
            v-if="chief && phone && game.phase !== 'results'"
            class="flex flex-wrap gap-2"
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
