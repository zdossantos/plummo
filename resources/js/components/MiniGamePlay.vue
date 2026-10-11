<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';
import { Eye } from '@lucide/vue';
import MiniGameScene from '@/components/MiniGameScene.vue';
import PlummoAvatar from '@/components/PlummoAvatar.vue';
import AnswerTexture from '@/components/AnswerTexture.vue';
import PagedTextField from '@/components/PagedTextField.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { activeBonusEffects, bonusChoices } from '@/lib/bonuses';
import type { DrawingSender, MiniGameState, RoomState } from '@/types/rooms';
const props = defineProps<{
    game: MiniGameState;
    room: RoomState;
    phone?: boolean;
    busy?: boolean;
    connected?: boolean;
    serverNow?: number;
    send?: DrawingSender;
}>();
const emit = defineEmits<{ answer: [choice: number] }>();
const { t } = useTranslations('rooms');
const now = computed(() => props.serverNow ?? Date.now() / 1000);
const revealed = computed(() => props.game.phase === 'reveal');
const effects = computed(() =>
    props.phone
        ? activeBonusEffects(
              props.game.bonuses?.effects ?? [],
              now.value,
              props.game.phase,
          )
        : [],
);
const hasEffect = (kind: string) => effects.value.some((e) => e.kind === kind);
const choices = computed(() =>
    bonusChoices(props.game.round.choices, effects.value, now.value),
);
const curtain = ref(new Set<number>());
watch(
    () =>
        [
            props.game.id,
            props.game.round.number,
            effects.value.find((e) => e.kind === 'curtain')?.id,
        ].join(':'),
    () => {
        curtain.value = new Set();
    },
);
const concealed = (index: number) =>
    hasEffect('curtain') && !curtain.value.has(index);
function choose(index: number) {
    if (concealed(index)) {
        curtain.value.add(index);
        return;
    }
    emit('answer', index);
}
const authors = computed(() =>
    props.game.round.participants.map((id, index) => ({
        player: props.room.players.find((p) => p.id === id),
        number: index + 1,
    })),
);
const title = computed(() =>
    props.game.round.trial === 'author' &&
    props.game.phase !== 'writing' &&
    props.game.round.text
        ? t('mini_author_guess')
        : props.game.round.question,
);
const draft = ref(props.game.me?.draft ?? '');
const dirty = ref(false);
const pending = ref(false);
const failed = ref(false);
const submitting = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;
const canWrite = computed(
    () =>
        props.phone &&
        props.game.phase === 'writing' &&
        props.game.me?.eligible &&
        !props.game.me.submitted,
);
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
        if (props.busy || props.connected === false) {
            schedule();
            return;
        }
        void save();
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
    pending.value = true;
    submitting.value = submit;
    failed.value = false;
    const ok =
        (await props.send?.('write', {
            game_id: props.game.id,
            round: props.game.round.number,
            text,
            submit,
        })) ?? false;
    pending.value = false;
    submitting.value = false;
    if (ok && draft.value === text) dirty.value = false;
    failed.value = !ok;
    schedule();
}
function input() {
    dirty.value = true;
    failed.value = false;
    schedule();
}
watch(
    () => [props.game.id, props.game.round.number].join(':'),
    () => {
        clearTimeout(timer);
        timer = undefined;
        draft.value = props.game.me?.draft ?? '';
        dirty.value = false;
        failed.value = false;
    },
);
watch(
    () => props.game.me?.draft,
    (value) => {
        if (!dirty.value && !pending.value) draft.value = value ?? '';
    },
);
watch(canWrite, schedule);
onUnmounted(() => clearTimeout(timer));
const responders = (index: number) =>
    props.room.players.filter(
        (player) => props.game.round.answers?.[player.id] === index,
    );
const showChoices = computed(
    () =>
        props.game.round.choices.length &&
        (props.phone || !props.game.round.scene),
);
</script>
<template>
    <div
        class="mini-play"
        :class="{ 'mini-phone': phone }"
        data-testid="mini-play"
    >
        <h1 class="mini-question">{{ title }}</h1>
        <p
            v-if="
                phone && game.round.trial === 'author' && game.me?.authorNumber
            "
            class="mini-author-number"
        >
            {{ t('mini_your_number', { number: game.me.authorNumber }) }}
        </p>
        <div
            v-if="!phone && game.phase === 'writing'"
            class="mini-author-roster"
        >
            <div v-for="author in authors" :key="author.number">
                <PlummoAvatar
                    v-if="author.player"
                    :color="author.player.color"
                    :accessories="author.player.accessories"
                />
                <strong>{{ author.number }} · {{ author.player?.name }}</strong>
            </div>
        </div>
        <template v-if="game.phase === 'waiting'">
            <p class="mini-watching" role="status">{{ t('mini_waiting') }}</p>
        </template>
        <template v-else-if="game.phase === 'writing'">
            <form
                v-if="canWrite"
                class="mini-writing"
                @submit.prevent="save(true)"
            >
                <label for="mini-text">{{ t('mini_author_text') }}</label>
                <PagedTextField
                    id="mini-text"
                    v-model="draft"
                    :maxlength="80"
                    :disabled="connected === false || submitting"
                    @input="input"
                />
                <p class="text-sm" role="status">
                    {{
                        t(
                            pending
                                ? 'phrase_saving'
                                : dirty
                                  ? 'phrase_unsaved'
                                  : 'phrase_saved',
                        )
                    }}
                </p>
                <Button
                    v-if="failed"
                    type="button"
                    variant="outline"
                    :disabled="busy || pending"
                    @click="save()"
                    >{{ t('phrase_retry') }}</Button
                >
                <Button
                    type="submit"
                    :disabled="busy || pending || connected === false"
                    >{{ t('phrase_submit') }}</Button
                >
            </form>
            <div v-else class="mini-watching">
                <PlummoAvatar
                    color="honey"
                    :accessories="['wizard-hat']"
                    motion="idle"
                />
                <p role="status">
                    {{
                        t(
                            phone
                                ? game.me?.submitted
                                    ? 'phrase_submitted'
                                    : 'next_round_wait'
                                : 'mini_author_hint',
                        )
                    }}
                </p>
            </div>
        </template>
        <template v-else>
            <blockquote v-if="game.round.text" class="mini-secret">
                {{ game.round.text }}
            </blockquote>
            <MiniGameScene
                v-if="!phone && game.round.scene"
                :game="game"
                :now="now"
            />
            <div
                v-if="phone && game.phase === 'observing'"
                class="mini-watching"
            >
                <Eye aria-hidden="true" />
                <p>{{ t('mini_watch_screen') }}</p>
                <PlummoAvatar color="honey" :accessories="['cap']" />
            </div>
            <p
                v-if="phone && !game.me?.eligible && game.phase === 'answer'"
                role="status"
            >
                {{
                    t(
                        game.round.trial === 'author' && game.me?.submitted
                            ? 'mini_author_own'
                            : 'next_round_wait',
                    )
                }}
            </p>
            <div
                v-if="showChoices"
                class="game-choices mini-choices"
                :class="{
                    'mini-numbers': ['hide', 'count', 'cups'].includes(
                        game.round.trial,
                    ),
                    'mini-many': choices.length > 4,
                }"
            >
                <div
                    v-for="{ choice, index } in choices"
                    :key="index"
                    class="game-choice"
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
                    <component
                        :is="phone ? 'button' : 'div'"
                        :type="phone ? 'button' : undefined"
                        class="choice-face"
                        :data-mini-choice="index"
                        :disabled="
                            phone
                                ? busy ||
                                  connected === false ||
                                  game.phase !== 'answer' ||
                                  !game.me?.eligible ||
                                  game.me.answered
                                : undefined
                        "
                        @click="phone && choose(index)"
                    >
                        <AnswerTexture :variant="index % 4" />
                        <span
                            v-if="
                                !['hide', 'count', 'cups'].includes(
                                    game.round.trial,
                                )
                            "
                            class="choice-letter"
                            >{{ String.fromCharCode(65 + index) }}</span
                        >
                        <span v-if="concealed(index)" class="answer-curtain"
                            ><small>{{ t('bonus_reveal') }}</small></span
                        >
                        <span
                            class="text-summary"
                            :class="{
                                'answer-upside': hasEffect('alphabet'),
                                'answer-concealed': concealed(index),
                            }"
                            >{{ concealed(index) ? '' : choice }}</span
                        >
                    </component>
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
                </div>
            </div>
            <p v-if="revealed" class="mini-result" role="status">
                {{
                    game.round.correct !== null
                        ? t('mini_correct', {
                              answer:
                                  game.round.choices[game.round.correct] ?? '',
                          })
                        : t('mini_empty')
                }}
            </p>
            <p v-if="phone && game.me?.answered && !revealed" role="status">
                {{ t('answer_saved') }}
            </p>
            <p v-if="phone && revealed" class="font-black" role="status">
                {{ t('round_points', { count: game.me?.points ?? 0 }) }}
            </p>
        </template>
    </div>
</template>
