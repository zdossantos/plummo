<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';
import AnswerTexture from '@/components/AnswerTexture.vue';
import TextReader from '@/components/TextReader.vue';
import PagedTextField from '@/components/PagedTextField.vue';
import PlummoAvatar from '@/components/PlummoAvatar.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { activeBonusEffects, bonusChoices, eatenQuestion } from '@/lib/bonuses';
import type { BluffGameState, DrawingSender, RoomState } from '@/types/rooms';
const props = defineProps<{
    game: BluffGameState;
    room: RoomState;
    phone?: boolean;
    busy?: boolean;
    connected?: boolean;
    serverNow?: number;
    send?: DrawingSender;
}>();
const { t } = useTranslations('rooms');
const draft = ref(props.game.me?.draft ?? '');
const pending = ref(false);
const submitting = ref(false);
const dirty = ref(false);
const failed = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;
const key = computed(() => `${props.game.id}:${props.game.round.number}`);
const canWrite = computed(
    () =>
        props.phone &&
        props.game.phase === 'writing' &&
        props.game.me?.eligible &&
        !props.game.me.submitted,
);
async function action(action: string, values: Record<string, unknown>) {
    return (
        (await props.send?.(action, {
            game_id: props.game.id,
            round: props.game.round.number,
            ...values,
        })) ?? false
    );
}
function schedule() {
    if (
        timer ||
        pending.value ||
        failed.value ||
        !dirty.value ||
        !canWrite.value
    )
        return;
    timer = setTimeout(() => {
        timer = undefined;
        if (props.busy || props.connected === false) schedule();
        else void save();
    }, 300);
}
async function save(submit = false) {
    if (
        !canWrite.value ||
        props.busy ||
        pending.value ||
        props.connected === false
    )
        return;
    clearTimeout(timer);
    timer = undefined;
    const text = draft.value;
    const current = key.value;
    pending.value = true;
    submitting.value = submit;
    try {
        const ok = await action('write', { text, submit });
        if (key.value !== current) return;
        if (ok && draft.value === text) dirty.value = false;
        failed.value = !ok;
    } finally {
        pending.value = false;
        submitting.value = false;
        schedule();
    }
}
function input() {
    dirty.value = true;
    failed.value = false;
    schedule();
}
watch(key, () => {
    clearTimeout(timer);
    timer = undefined;
    draft.value = props.game.me?.draft ?? '';
    dirty.value = false;
    failed.value = false;
});
watch(
    () => props.game.me?.draft,
    (value) => {
        if (!dirty.value && !pending.value) draft.value = value ?? '';
    },
);
watch(canWrite, schedule);
onUnmounted(() => clearTimeout(timer));
const now = computed(() => props.serverNow ?? Date.now() / 1000);
const effects = computed(() =>
    activeBonusEffects(
        props.game.bonuses?.effects ?? [],
        now.value,
        props.game.phase,
    ),
);
const has = (kind: string) => effects.value.some((e) => e.kind === kind);
const question = computed(() =>
    eatenQuestion(props.game.round.question, has('missing_word')),
);
const revealed = computed(() =>
    ['reveal', 'waiting'].includes(props.game.phase),
);
const presenting = computed(() => props.game.phase === 'presenting');
const order = computed(() =>
    bonusChoices(
        props.game.round.entries.map((e) => e.text),
        effects.value,
        now.value,
    ).filter((e) => !presenting.value || e.index === props.game.round.index),
);
const curtains = ref(new Set<number>());
watch(
    () => `${key.value}:${effects.value.find((e) => e.kind === 'curtain')?.id}`,
    () => {
        curtains.value = new Set();
    },
);
const concealed = (index: number) =>
    has('curtain') && (props.phone ? !curtains.value.has(index) : true);
async function vote(index: number) {
    if (pending.value || props.busy || props.connected === false) return;
    if (concealed(index)) {
        curtains.value.add(index);
        return;
    }
    const entry = props.game.round.entries[index];
    if (
        !entry ||
        entry.own ||
        !props.game.me?.canVote ||
        props.game.me.answered
    )
        return;
    pending.value = true;
    try {
        await action('vote', { choice: entry.id });
    } finally {
        pending.value = false;
    }
}
const player = (id: number) => props.room.ranking.find((p) => p.id === id);
const status = computed(() =>
    props.game.phase === 'waiting'
        ? 'bluff_waiting'
        : props.game.me?.foundTruth && !revealed.value
          ? 'bluff_found_truth'
          : presenting.value
            ? 'bluff_presenting'
            : revealed.value
              ? 'bluff_truth'
              : props.phone && props.game.me?.answered
                ? 'answer_saved'
                : 'bluff_vote',
);
</script>
<template>
    <div
        class="bluff-play"
        :class="{ 'bluff-phone': phone }"
        data-testid="bluff-play"
    >
        <div class="bluff-question">
            <h1 :class="{ 'text-summary': phone }">{{ question }}</h1>
            <TextReader v-if="phone" :text="question" />
        </div>
        <template v-if="game.phase === 'writing'">
            <form
                v-if="canWrite"
                class="bluff-writing"
                @submit.prevent="save(true)"
            >
                <label for="bluff-draft">{{ t('bluff_write') }}</label>
                <PagedTextField
                    id="bluff-draft"
                    v-model="draft"
                    :maxlength="120"
                    :disabled="connected === false || submitting"
                    @input="input"
                />
                <p role="status">
                    {{
                        t(
                            failed
                                ? 'phrase_save_error'
                                : dirty
                                  ? 'phrase_unsaved'
                                  : 'phrase_saved',
                        )
                    }}
                </p>
                <Button
                    type="submit"
                    :disabled="
                        busy || pending || connected === false || !draft.trim()
                    "
                    >{{ t('bluff_submit') }}</Button
                >
                <Button
                    v-if="failed"
                    type="button"
                    variant="outline"
                    @click="
                        failed = false;
                        save();
                    "
                    >{{ t('phrase_retry') }}</Button
                >
            </form>
            <div v-else class="bluff-wait">
                <h2>
                    {{
                        t(
                            phone && game.me?.submitted
                                ? 'bluff_submitted'
                                : phone && !game.me?.eligible
                                  ? 'next_round_wait'
                                  : 'bluff_writing',
                        )
                    }}
                </h2>
                <div v-if="!phone" class="bluff-cast">
                    <PlummoAvatar
                        v-for="p in room.players.filter(
                            (p) => p.status === 'connected',
                        )"
                        :key="p.id"
                        :color="p.color"
                        :accessories="p.accessories"
                        :label="p.name"
                    />
                </div>
            </div>
        </template>
        <template v-else>
            <p class="bluff-directive" role="status">{{ t(status) }}</p>
            <TransitionGroup
                tag="div"
                name="answer"
                class="game-choices bluff-choices"
                :class="{
                    'controller-answers': phone,
                    'bluff-presentation': presenting,
                }"
            >
                <div
                    v-for="{ choice, index } in order"
                    :key="game.round.entries[index]!.id"
                    class="game-choice"
                    :data-answer="index"
                    :data-color="index"
                    :class="{
                        correct: revealed && game.round.entries[index]!.correct,
                        wrong:
                            revealed &&
                            phone &&
                            game.me?.choice === game.round.entries[index]!.id &&
                            !game.round.entries[index]!.correct,
                        muted:
                            revealed &&
                            !game.round.entries[index]!.correct &&
                            (!phone ||
                                game.me?.choice !==
                                    game.round.entries[index]!.id),
                        chosen:
                            game.me?.choice === game.round.entries[index]!.id,
                    }"
                >
                    <component
                        :is="phone ? 'button' : 'div'"
                        class="choice-face"
                        :type="phone ? 'button' : undefined"
                        :disabled="
                            phone
                                ? pending ||
                                  busy ||
                                  connected === false ||
                                  !game.me?.canVote ||
                                  game.me.answered ||
                                  game.round.entries[index]!.own
                                : undefined
                        "
                        :aria-label="`${String.fromCharCode(65 + index)} : ${choice}`"
                        @click="phone && vote(index)"
                    >
                        <AnswerTexture :variant="index" />
                        <span
                            class="choice-letter"
                            :class="{ 'answer-upside': has('alphabet') }"
                            >{{ String.fromCharCode(65 + index) }}</span
                        >
                        <strong
                            v-if="!phone || presenting"
                            class="bluff-choice-text"
                            :class="{
                                invisible: concealed(index),
                                'answer-upside': has('alphabet'),
                            }"
                            >{{ choice }}</strong
                        >
                        <span v-if="concealed(index)" class="answer-curtain"
                            ><span>✦</span
                            ><small>{{ t('bonus_reveal') }}</small></span
                        >
                    </component>
                    <span
                        v-if="
                            phone && game.round.entries[index]!.own && !revealed
                        "
                        class="bluff-own"
                        >{{ t('bluff_own') }}</span
                    >
                    <TextReader
                        v-if="phone && !concealed(index)"
                        :text="choice"
                        :force="true"
                        :upside="has('alphabet')"
                        :label="String.fromCharCode(65 + index)"
                    />
                    <div v-if="revealed" class="bluff-identities">
                        <div
                            v-if="game.round.entries[index]!.authors.length"
                            :aria-label="t('bluff_authors')"
                            class="bluff-authors"
                        >
                            <template
                                v-for="id in game.round.entries[index]!.authors"
                                :key="id"
                                ><PlummoAvatar
                                    v-if="player(id)"
                                    :color="player(id)!.color"
                                    :accessories="player(id)!.accessories"
                                    :label="player(id)!.name"
                                /><small v-if="!phone"
                                    >{{ player(id)?.name }} +{{
                                        game.round.awards[id] ?? 0
                                    }}</small
                                ></template
                            >
                        </div>
                        <div
                            :aria-label="t('bluff_voters')"
                            class="bluff-voters"
                        >
                            <template
                                v-for="id in game.round.entries[index]!.voters"
                                :key="id"
                                ><PlummoAvatar
                                    v-if="player(id)"
                                    :color="player(id)!.color"
                                    :accessories="player(id)!.accessories"
                                    :label="player(id)!.name"
                            /></template>
                        </div>
                    </div>
                </div>
            </TransitionGroup>
            <p v-if="phone && revealed" class="bluff-points">
                {{ t('round_points', { count: game.me?.points ?? 0 }) }}
            </p>
        </template>
    </div>
</template>
<style scoped>
.bluff-play {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    gap: 10px;
}
.bluff-question {
    flex: none;
    text-align: center;
}
.bluff-question h1 {
    font-size: clamp(24px, 3vw, 52px);
    line-height: 1.1;
    overflow-wrap: anywhere;
    text-wrap: balance;
}
.bluff-question .text-reader-trigger {
    width: fit-content;
}
.bluff-writing {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    justify-content: center;
    gap: 8px;
}
.bluff-writing label {
    font-weight: 700;
}
.bluff-writing p {
    font-size: 12px;
}
.bluff-wait {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 24px;
    text-align: center;
}
.bluff-wait h2 {
    font-size: clamp(24px, 3vw, 48px);
    color: var(--lime);
}
.bluff-cast {
    display: flex;
    justify-content: center;
    width: 100%;
}
.bluff-cast > * {
    width: 10%;
}
.bluff-directive {
    font-weight: 700;
    color: var(--lime);
    text-align: center;
    font-size: clamp(14px, 1.6vw, 26px);
}
.bluff-choices {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    grid-auto-rows: minmax(0, 1fr);
}
.bluff-presentation {
    grid-template-columns: minmax(0, 1fr);
}
.bluff-choice-text {
    position: relative;
    z-index: 1;
    font-size: clamp(16px, 1.8vw, 30px);
    line-height: 1.1;
    overflow-wrap: anywhere;
    text-align: center;
}
.bluff-presentation .bluff-choice-text {
    font-size: clamp(24px, 4vw, 60px);
}
.bluff-identities {
    position: absolute;
    bottom: 6px;
    left: 8px;
    right: 8px;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: space-between;
    pointer-events: none;
}
.bluff-authors,
.bluff-voters {
    display: flex;
    align-items: center;
    min-width: 0;
}
.bluff-identities svg {
    width: clamp(18px, 2.5vw, 36px);
    height: clamp(18px, 2.5vw, 36px);
    flex: none;
}
.bluff-authors small {
    max-width: 100px;
    font-weight: 700;
    font-size: 12px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.bluff-choices .choice-face {
    padding-bottom: 44px;
}
.bluff-own {
    position: absolute;
    top: 5px;
    right: 5px;
    font-size: 10px;
    color: var(--ink);
    background: var(--paper);
    border-radius: 8px;
    padding: 3px;
}
.bluff-points {
    font-weight: 700;
    flex: none;
}
.bluff-phone {
    gap: 6px;
}
.bluff-phone .bluff-question {
    display: flex;
    text-align: left;
    align-items: center;
    gap: 6px;
}
.bluff-phone .bluff-question h1 {
    font-size: 20px;
    flex: 1;
}
.bluff-phone .bluff-directive {
    font-size: 14px;
}
.bluff-phone .bluff-choices {
    gap: 8px;
}
.bluff-phone .choice-face {
    padding: 8px;
}
.bluff-phone .bluff-identities {
    bottom: 28px;
    left: 3px;
    right: 3px;
}
.bluff-phone .bluff-identities svg {
    width: 18px;
    height: 18px;
}
.bluff-phone .bluff-presentation .choice-letter {
    position: absolute;
    top: 8px;
    left: 8px;
    font-size: 24px;
    width: 36px;
    height: 36px;
}
.bluff-phone .bluff-presentation .bluff-choice-text {
    font-size: clamp(20px, 5vw, 30px);
    padding: 40px 8px;
}
@media (max-height: 450px) {
    .bluff-phone .bluff-question h1 {
        font-size: 16px;
    }
    .bluff-writing {
        gap: 4px;
    }
    .bluff-phone .bluff-directive {
        font-size: 12px;
    }
}
</style>
