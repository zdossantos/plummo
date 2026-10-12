<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Gavel, Mic, Check } from '@lucide/vue';
import PlummoAvatar from '@/components/PlummoAvatar.vue';
import AnswerTexture from '@/components/AnswerTexture.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { activeBonusEffects, bonusChoices } from '@/lib/bonuses';
import type {
    DrawingSender,
    RoomState,
    TribunalGameState,
} from '@/types/rooms';
const props = defineProps<{
    game: TribunalGameState;
    room: RoomState;
    phone?: boolean;
    busy?: boolean;
    connected?: boolean;
    serverNow?: number;
    send?: DrawingSender;
    seconds?: number | string;
}>();
const { t } = useTranslations('rooms');
const now = computed(() => props.serverNow ?? Date.now() / 1000);
const revealed = computed(() => props.game.phase === 'reveal');
const advocates = computed(() =>
    props.game.round.advocates.map((id) =>
        props.room.players.find((p) => p.id === id),
    ),
);
const effects = computed(() =>
    props.phone
        ? activeBonusEffects(
              props.game.bonuses?.effects ?? [],
              now.value,
              props.game.phase,
          )
        : [],
);
const order = computed(() =>
    bonusChoices(
        [t('tribunal_for'), t('tribunal_against')],
        effects.value,
        now.value,
    ),
);
const curtains = ref(new Set<number>());
watch(
    () =>
        [
            props.game.id,
            props.game.round.number,
            effects.value.find((e) => e.kind === 'curtain')?.id,
        ].join(':'),
    () => {
        curtains.value = new Set();
    },
);
const concealed = (index: number) =>
    effects.value.some((e) => e.kind === 'curtain') &&
    !curtains.value.has(index);
const pending = ref(false);
async function action(name: string, choice?: number) {
    if (pending.value || props.busy || props.connected === false) return;
    if (choice !== undefined && concealed(choice)) {
        curtains.value.add(choice);
        return;
    }
    pending.value = true;
    try {
        await props.send?.(name, {
            game_id: props.game.id,
            round: props.game.round.number,
            ...(choice !== undefined ? { choice } : {}),
        });
    } finally {
        pending.value = false;
    }
}
const verdict = computed(() => {
    if (props.game.round.canceled) return t('tribunal_canceled');
    const [a = 0, b = 0] = props.game.round.counts;
    if (a + b === 0) return t('tribunal_abstain');
    if (a === b) return t('tribunal_tie');
    return t('tribunal_winner', {
        name: advocates.value[a > b ? 0 : 1]?.name ?? '',
    });
});
const status = computed(() => {
    if (revealed.value) return verdict.value;
    if (props.game.phase === 'waiting') return t('tribunal_waiting');
    if (props.game.phase === 'preparing') return t('tribunal_prepare');
    if (props.game.phase === 'voting')
        return props.phone && props.game.me?.answered
            ? t('tribunal_voted')
            : props.phone && !props.game.me?.canVote
              ? t('tribunal_wait_vote')
              : t('tribunal_vote');
    return props.phone && props.game.me?.canSpeak
        ? t('tribunal_your_speech')
        : t('tribunal_speaker', {
              name:
                  props.room.players.find(
                      (p) => p.id === props.game.round.speakerId,
                  )?.name ?? '',
          });
});
function winning(index: number) {
    const counts = props.game.round.counts;
    return (
        revealed.value &&
        !props.game.round.canceled &&
        (counts[index] ?? 0) > 0 &&
        counts[index] === Math.max(...counts)
    );
}
const voters = (index: number) =>
    props.room.players.filter((p) => props.game.round.votes[p.id] === index);
</script>
<template>
    <div
        class="tribunal-play"
        :class="{ 'tribunal-phone': phone }"
        data-testid="tribunal-play"
    >
        <h1 class="tribunal-question">{{ game.round.question }}</h1>
        <div
            class="tribunal-stage"
            :class="{ 'tribunal-stage-voting': game.phase === 'voting' }"
        >
            <svg
                v-if="!phone"
                class="tribunal-scenery"
                viewBox="0 0 1000 430"
                preserveAspectRatio="none"
                aria-hidden="true"
            >
                <path
                    d="M70 430V70Q250 -35 430 70V430M570 430V70Q750 -35 930 70V430"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="8"
                />
                <path
                    d="M100 420H900M110 400H890"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="4"
                />
                <path
                    d="m470 56 30-18 30 18-30 18zM500 82v45"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="5"
                />
            </svg>
            <div class="tribunal-cast">
                <template v-for="index in [0, 1]" :key="index">
                    <div
                        v-if="advocates[index]"
                        class="tribunal-advocate"
                        :class="{
                            'tribunal-speaking':
                                game.round.speakerId === advocates[index]?.id,
                            'tribunal-winning': winning(index),
                        }"
                        :data-advocate="advocates[index]?.id"
                    >
                        <div class="tribunal-character">
                            <Mic
                                v-if="
                                    game.round.speakerId ===
                                    advocates[index]?.id
                                "
                                class="tribunal-mic"
                                aria-hidden="true"
                            />
                            <PlummoAvatar
                                :color="advocates[index]?.color"
                                :accessories="advocates[index]?.accessories"
                                :label="advocates[index]?.name"
                                :motion="
                                    winning(index)
                                        ? 'podium'
                                        : game.round.speakerId ===
                                            advocates[index]?.id
                                          ? 'hello'
                                          : 'idle'
                                "
                                :motion-key="`${game.id}:${game.round.number}:${game.phase}:${index}`"
                            />
                        </div>
                        <div
                            class="tribunal-lectern"
                            :class="{ 'tribunal-lectern-against': index === 1 }"
                        >
                            <span class="tribunal-camp">{{
                                t(
                                    index === 0
                                        ? 'tribunal_for'
                                        : 'tribunal_against',
                                )
                            }}</span>
                            <strong>{{ advocates[index]?.name }}</strong>
                            <template v-if="revealed && !game.round.canceled">
                                <span>{{
                                    t('tribunal_votes', {
                                        count: game.round.counts[index] ?? 0,
                                    })
                                }}</span>
                                <b
                                    >+{{
                                        game.round.awards[
                                            advocates[index]!.id
                                        ] ?? 0
                                    }}</b
                                >
                            </template>
                        </div>
                        <div
                            v-if="revealed && !game.round.canceled"
                            class="tribunal-voters"
                        >
                            <PlummoAvatar
                                v-for="voter in voters(index)"
                                :key="voter.id"
                                :color="voter.color"
                                :accessories="voter.accessories"
                                :label="voter.name"
                            />
                        </div>
                    </div>
                </template>
            </div>
            <div v-if="!phone" class="tribunal-center">
                <p
                    v-if="
                        [
                            'preparing',
                            'speaking_first',
                            'speaking_second',
                            'voting',
                        ].includes(game.phase)
                    "
                    class="game-timer"
                    role="timer"
                >
                    {{ seconds }}
                </p>
                <Gavel class="tribunal-gavel" aria-hidden="true" />
            </div>
        </div>
        <div class="tribunal-directive" role="status">
            <p>{{ status }}</p>
            <span
                v-if="phone && !revealed && game.phase !== 'waiting'"
                class="tribunal-role"
                >{{
                    !game.me?.eligible
                        ? t('tribunal_next_tour')
                        : game.me.camp === null
                          ? t('tribunal_jury')
                          : t('tribunal_camp', {
                                camp: t(
                                    game.me.camp === 0
                                        ? 'tribunal_for'
                                        : 'tribunal_against',
                                ),
                            })
                }}</span
            >
        </div>
        <div v-if="phone && game.me?.canVote" class="tribunal-ballots">
            <Button
                v-for="{ choice, index } in order"
                :key="index"
                variant="outline"
                class="tribunal-ballot"
                :class="{
                    'tribunal-ballot-against': index === 1,
                    'tribunal-ballot-chosen': game.me.choice === index,
                }"
                :data-tribunal-choice="index"
                :disabled="
                    pending || busy || connected === false || game.me.answered
                "
                @click="action('vote', index)"
            >
                <AnswerTexture :variant="index" />
                <span
                    class="tribunal-ballot-text"
                    :class="{ invisible: concealed(index) }"
                    ><strong>{{ choice }}</strong
                    ><span>{{ advocates[index]?.name }}</span></span
                >
                <span
                    v-if="concealed(index)"
                    class="game-curtain"
                    aria-hidden="true"
                    >?</span
                >
                <Check v-if="game.me.choice === index" aria-hidden="true" />
            </Button>
        </div>
        <Button
            v-if="phone && game.me?.canSpeak"
            class="tribunal-finish"
            data-tribunal-finish
            :disabled="pending || busy || connected === false"
            @click="action('finish')"
            ><Check aria-hidden="true" />{{ t('tribunal_finish') }}</Button
        >
    </div>
</template>
<style scoped>
.tribunal-play {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    gap: 12px;
}
.tribunal-question {
    font-size: clamp(28px, 3.4vw, 62px);
    text-align: center;
    text-wrap: balance;
    line-height: 1.1;
    flex: none;
}
.tribunal-stage {
    position: relative;
    flex: 1;
    min-height: 0;
    container-type: size;
    isolation: isolate;
}
.tribunal-scenery {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    color: var(--purple);
    opacity: 0.15;
    z-index: -1;
}
.tribunal-cast {
    display: flex;
    align-items: end;
    justify-content: space-around;
    height: 100%;
    gap: 8%;
    padding: 4cqh 6% 3cqh;
}
.tribunal-advocate {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 36%;
    min-width: 0;
    height: 100%;
    justify-content: end;
}
.tribunal-character {
    position: relative;
    width: min(78%, 65cqh);
    min-height: 0;
    flex: 1;
    display: flex;
    align-items: end;
    justify-content: center;
    padding-top: 5cqh;
}
.tribunal-character > svg:not(.tribunal-mic) {
    width: 100%;
    max-height: 100%;
}
.tribunal-speaking::before {
    content: '';
    position: absolute;
    inset: 0 -12% 0;
    background: linear-gradient(180deg, transparent, #efe6811c);
    clip-path: polygon(43% 0, 57% 0, 100% 100%, 0 100%);
    z-index: -1;
}
.tribunal-mic {
    position: absolute;
    right: -8%;
    top: 15%;
    color: var(--lime);
    width: 8cqh;
    height: 8cqh;
}
.tribunal-lectern {
    width: 100%;
    background: var(--mint);
    color: var(--ink);
    border: 3px solid var(--edge);
    border-radius: 20px 20px 10px 10px;
    box-shadow: 0 8px 0 var(--edge);
    padding: 12px 18px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    text-align: center;
    font-size: clamp(16px, 1.8vw, 28px);
    flex: none;
}
.tribunal-lectern-against {
    background: var(--pink);
}
.tribunal-lectern strong {
    max-width: 100%;
    overflow-wrap: anywhere;
    font-size: clamp(20px, 2.3vw, 34px);
    line-height: 1.05;
}
.tribunal-camp {
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-size: 0.65em;
    font-weight: 800;
}
.tribunal-center {
    position: absolute;
    inset: 16% 44% auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 24px;
}
.tribunal-center .game-timer {
    font-size: clamp(32px, 4vw, 52px);
    width: 96px;
    height: 96px;
}
.tribunal-gavel {
    width: 72px;
    height: auto;
    color: var(--lime);
    rotate: -15deg;
}
.tribunal-speaking .tribunal-camp,
.tribunal-winning .tribunal-camp {
    background: var(--paper);
    padding: 2px 10px;
    border-radius: 20px;
}
.tribunal-voters {
    height: 36px;
    display: flex;
    justify-content: center;
    margin-top: 14px;
    flex: none;
}
.tribunal-voters svg {
    width: 40px;
    height: 40px;
}
.tribunal-directive {
    text-align: center;
    flex: none;
}
.tribunal-directive p {
    font-family: Fredoka, sans-serif;
    font-size: clamp(22px, 2.5vw, 36px);
    font-weight: 600;
    line-height: 1.15;
    color: var(--lime);
}
.tribunal-role {
    display: block;
    margin-top: 6px;
    font-size: 14px;
}
.tribunal-ballots {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    flex: none;
}
.tribunal-ballot[data-slot='button'][data-variant='outline'] {
    position: relative;
    overflow: hidden;
    min-height: 76px;
    height: auto;
    background: var(--mint);
    padding: 10px;
    white-space: normal;
}
.tribunal-ballot-against[data-slot='button'][data-variant='outline'] {
    background: var(--pink);
}
.tribunal-ballot-text {
    display: flex;
    flex-direction: column;
    position: relative;
    z-index: 1;
    gap: 4px;
    line-height: 1.1;
    overflow-wrap: anywhere;
}
.tribunal-ballot-text > span {
    font-size: 14px;
}
.tribunal-ballot-chosen {
    outline: 3px solid var(--lime);
    outline-offset: 3px;
}
.tribunal-finish {
    flex: none;
}
.tribunal-phone .tribunal-question {
    font-size: clamp(22px, 5.5vw, 30px);
}
.tribunal-phone .tribunal-stage {
    min-height: 150px;
}
.tribunal-phone .tribunal-cast {
    padding: 0 0 10px;
    gap: 12px;
}
.tribunal-phone .tribunal-advocate {
    width: 46%;
}
.tribunal-phone .tribunal-character {
    width: min(90%, 65cqh);
}
.tribunal-phone .tribunal-lectern {
    border-width: 2px;
    border-radius: 14px;
    padding: 8px;
    box-shadow: 0 5px 0 var(--edge);
    font-size: 13px;
}
.tribunal-phone .tribunal-lectern strong {
    font-size: 18px;
}
.tribunal-phone .tribunal-directive p {
    font-size: 22px;
}
@media (max-height: 620px) {
    .tribunal-phone {
        gap: 6px;
    }
    .tribunal-phone .tribunal-question {
        font-size: 20px;
    }
    .tribunal-phone .tribunal-role {
        font-size: 12px;
    }
    .tribunal-phone .tribunal-directive p {
        font-size: 18px;
    }
    .tribunal-phone
        .tribunal-ballot[data-slot='button'][data-variant='outline'] {
        min-height: 60px;
    }
}
</style>

<style scoped>
.tribunal-phone .tribunal-voters svg {
    width: 24px;
    height: 24px;
}
</style>
