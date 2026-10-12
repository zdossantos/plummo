<script setup lang="ts">
import { Pause } from '@lucide/vue';
import BonusEffects from '@/components/BonusEffects.vue';
import { activeBonusEffects, bonusChoices, eatenQuestion } from '@/lib/bonuses';
import PagedList from '@/components/PagedList.vue';
import TextReader from '@/components/TextReader.vue';
import PlummoAvatar from '@/components/PlummoAvatar.vue';
import GameWinners from '@/components/GameWinners.vue';
import PhrasePlay from '@/components/PhrasePlay.vue';
import TribunalGamePlay from '@/components/TribunalGamePlay.vue';
import MiniGamePlay from '@/components/MiniGamePlay.vue';
import DrawingPlay from '@/components/DrawingPlay.vue';
import AnswerTexture from '@/components/AnswerTexture.vue';
import AudioClip from '@/components/AudioClip.vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { GameState, RoomState, DrawingSender } from '@/types/rooms';
const props = defineProps<{
    game: GameState;
    room: RoomState;
    seconds: number;
    serverNow?: number;
    phone?: boolean;
    chief?: boolean;
    busy?: boolean;
    connected?: boolean;
    sendDrawing?: DrawingSender;
    sendPhrase?: DrawingSender;
    sendMini?: DrawingSender;
    sendTribunal?: DrawingSender;
}>();
const emit = defineEmits<{
    answer: [choice: number];
    control: [action: string];
}>();
const { t } = useTranslations('rooms');
const effects = computed(() =>
    activeBonusEffects(
        props.game.bonuses?.effects ?? [],
        props.serverNow ?? Date.now() / 1000,
        props.game.phase,
    ),
);
const hasEffect = (kind: string) => effects.value.some((e) => e.kind === kind);
const question = computed(() =>
    'question' in props.game.round
        ? eatenQuestion(
              props.game.round.question,
              !!props.phone && hasEffect('missing_word'),
          )
        : '',
);
const revealedCurtains = ref(new Set<number>());
watch(
    () =>
        [
            props.game.id,
            props.game.round.number,
            effects.value.find((e) => e.kind === 'curtain')?.id,
        ].join(':'),
    () => {
        revealedCurtains.value = new Set();
    },
);
function concealed(index: number) {
    return (
        !!props.phone &&
        hasEffect('curtain') &&
        !revealedCurtains.value.has(index)
    );
}
function choose(index: number) {
    if (concealed(index)) {
        revealedCurtains.value.add(index);
        return;
    }
    emit('answer', index);
}
const timerValue = computed(() =>
    props.phone && hasEffect('watch')
        ? '—'
        : (props.game.bonuses?.clock?.frozenValue ?? props.seconds),
);
const choices = computed(() =>
    'choices' in props.game.round
        ? bonusChoices(
              props.game.round.choices,
              props.phone ? effects.value : [],
              props.serverNow ?? Date.now() / 1000,
          )
        : [],
);
function choiceText(choice: string) {
    return effects.value.some((effect) => effect.kind === 'artist')
        ? choice.split(' — ')[0]!
        : choice;
}
const revealed = computed(
    () =>
        ['quiz', 'blind_test'].includes(props.game.type) &&
        'correct' in props.game.round &&
        props.game.round.correct !== null,
);
function responders(index: number) {
    if (!revealed.value || !('answers' in props.game.round)) return [];
    const answers = props.game.round.answers;
    return props.room.players.filter(
        (player) => answers?.[player.id] === index,
    );
}
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
            'game-jelly': phone && hasEffect('jelly'),
        }"
        data-testid="game-play"
    >
        <BonusEffects
            v-if="phone || game.type === 'blind_test'"
            :effects="effects"
            :room="room"
            :now="serverNow"
        />
        <div
            class="game-phase-bar flex items-center justify-between gap-5 font-bold text-primary"
        >
            <p>
                {{
                    t(
                        game.type === 'tribunal'
                            ? 'tribunal_round'
                            : game.type === 'phrase'
                              ? 'phrase_round'
                              : game.type === 'drawing'
                                ? 'drawing_round'
                                : game.type === 'mini_games'
                                  ? 'mini_round'
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
            <button
                v-if="
                    chief &&
                    phone &&
                    !['paused', 'results'].includes(game.phase)
                "
                class="game-pause"
                type="button"
                :disabled="busy"
                :aria-label="t('pause_game')"
                @click="emit('control', 'pause')"
            >
                <Pause aria-hidden="true" />
            </button>
            <p
                v-if="
                    [
                        'preparing',
                        'speaking_first',
                        'speaking_second',
                        'observing',
                        'answer',
                        'selecting',
                        'drawing',
                        'writing',
                        'presenting',
                        'voting',
                    ].includes(game.phase) &&
                    (phone || game.type !== 'tribunal')
                "
                class="game-timer tabular-nums"
                role="timer"
            >
                {{ timerValue }}
            </p>
        </div>
        <AudioClip
            v-if="!phone && game.type === 'blind_test' && game.round.audio"
            :src="game.round.audio"
            :effects="effects"
            :now="serverNow"
            :revealing="game.phase === 'reveal'"
            :playing="
                ['answer', 'reveal'].includes(game.phase) && connected !== false
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
            <GameWinners
                :winners="ranking.slice(0, 3)"
                :motion-key="`${game.id}:results`"
            />
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
            :server-now="serverNow"
            :send="sendDrawing"
        />
        <TribunalGamePlay
            v-else-if="game.type === 'tribunal'"
            :game="game"
            :room="room"
            :phone="phone"
            :busy="busy"
            :connected="connected"
            :server-now="serverNow"
            :send="sendTribunal"
            :seconds="timerValue"
        />
        <MiniGamePlay
            v-else-if="game.type === 'mini_games'"
            :game="game"
            :room="room"
            :phone="phone"
            :busy="busy"
            :connected="connected"
            :server-now="serverNow"
            :send="sendMini"
            @answer="emit('answer', $event)"
        />
        <template v-else-if="game.type !== 'phrase'">
            <div class="game-question">
                <h1 class="text-summary text-xl lg:text-3xl">
                    {{ question }}
                </h1>
                <TextReader v-if="phone" :text="question" />
            </div>
            <p v-if="phone && !game.me?.eligible && !revealed" class="text-sm">
                {{ t('next_round_wait') }}
            </p>
            <TransitionGroup
                tag="div"
                class="game-choices"
                :class="{ 'controller-answers': phone }"
                name="answer"
            >
                <div
                    v-for="{ choice, index } in choices"
                    :key="index"
                    class="game-choice"
                    :data-answer="index"
                    :data-color="index % 4"
                    :class="{
                        correct: revealed && game.round.correct === index,
                        wrong:
                            phone &&
                            revealed &&
                            game.me?.choice === index &&
                            game.round.correct !== index,
                        muted:
                            revealed &&
                            game.round.correct !== index &&
                            (!phone || game.me?.choice !== index),
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
                        @click="choose(index)"
                    >
                        <span
                            v-if="concealed(index)"
                            class="answer-curtain"
                            :aria-label="t('bonus_reveal')"
                            ><span>✦</span
                            ><small>{{ t('bonus_reveal') }}</small></span
                        >
                        <AnswerTexture :variant="index % 4" />
                        <span
                            class="choice-letter"
                            :class="{
                                'answer-upside': phone && hasEffect('alphabet'),
                            }"
                            >{{ String.fromCharCode(65 + index) }}</span
                        ><span
                            class="text-summary"
                            :class="{
                                'answer-upside': phone && hasEffect('alphabet'),
                                'answer-concealed': concealed(index),
                            }"
                            >{{
                                concealed(index) ? '' : choiceText(choice)
                            }}</span
                        >
                    </button>
                    <p v-else class="choice-face font-bold">
                        <AnswerTexture :variant="index % 4" />
                        <span
                            class="choice-letter"
                            :class="{
                                'answer-upside': phone && hasEffect('alphabet'),
                            }"
                            >{{ String.fromCharCode(65 + index) }}</span
                        ><span class="text-summary">{{
                            choiceText(choice)
                        }}</span>
                    </p>
                    <div
                        v-if="revealed && responders(index).length"
                        class="choice-plummos"
                    >
                        <PlummoAvatar
                            v-for="player in responders(index)"
                            :key="player.id"
                            :color="player.color"
                            :accessories="player.accessories"
                            :label="player.name"
                        />
                    </div>
                    <TextReader
                        v-if="phone && !concealed(index)"
                        :text="choiceText(choice)"
                        :force="phone"
                        :upside="phone && hasEffect('alphabet')"
                        :label="String.fromCharCode(65 + index)"
                    />
                </div>
            </TransitionGroup>
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
            v-if="chief && phone && game.phase === 'paused'"
            class="flex flex-wrap gap-2"
        >
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
        </div>
    </section>
</template>
