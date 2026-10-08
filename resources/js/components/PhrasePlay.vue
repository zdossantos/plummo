<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';
import TextReader from '@/components/TextReader.vue';
import PagedTextField from '@/components/PagedTextField.vue';
import PlummoAvatar from '@/components/PlummoAvatar.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { PhraseGameState, DrawingSender, RoomState } from '@/types/rooms';
const props = defineProps<{
    game: PhraseGameState;
    room: RoomState;
    phone?: boolean;
    busy?: boolean;
    connected?: boolean;
    send?: DrawingSender;
}>();
const { t } = useTranslations('rooms');
const draft = ref(props.game.me?.draft ?? '');
const dirty = ref(false);
const pending = ref(false);
const submitting = ref(false);
const failed = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;
const canWrite = computed(
    () =>
        props.phone &&
        props.game.phase === 'writing' &&
        props.game.me?.eligible &&
        !props.game.me.submitted,
);
const length = computed(() => Array.from(draft.value).length);
async function sendAction(action: string, values: Record<string, unknown>) {
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
        if (props.busy || props.connected === false) {
            schedule();
            return;
        }
        void save();
    }, 300);
}
async function save(submit = false) {
    if (!canWrite.value || props.busy || pending.value) return;
    clearTimeout(timer);
    timer = undefined;
    const text = draft.value;
    pending.value = true;
    submitting.value = submit;
    failed.value = false;
    const ok = await sendAction(submit ? 'submit' : 'draft', { suffix: text });
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
watch([() => props.game.id, () => props.game.round.number], () => {
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
watch(canWrite, () => schedule());
onUnmounted(() => clearTimeout(timer));
const author = (id?: number) =>
    props.room.ranking.find((player) => player.id === id);
</script>
<template>
    <div
        v-show="!['paused', 'resuming'].includes(game.phase)"
        class="phrase-scene"
    >
        <h1 class="text-summary text-xl leading-tight lg:text-3xl">
            {{ game.round.prompt }}
        </h1>
        <TextReader v-if="phone" :text="game.round.prompt" />
        <template v-if="game.phase === 'writing'">
            <form v-if="canWrite" @submit.prevent="save(true)">
                <label for="phrase-suffix" class="font-bold">{{
                    t('phrase_suffix')
                }}</label>
                <PagedTextField
                    id="phrase-suffix"
                    v-model="draft"
                    :maxlength="150"
                    :disabled="connected === false || submitting"
                    @input="input"
                />
                <p class="mt-2 text-sm" aria-live="polite">
                    {{ t('phrase_characters', { count: length }) }} ·
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
                <p v-if="failed" class="mt-3" role="alert">
                    {{ t('phrase_save_error') }}
                </p>
                <Button
                    v-if="failed"
                    type="button"
                    class="mt-3"
                    variant="outline"
                    :disabled="busy || pending"
                    @click="
                        failed = false;
                        save();
                    "
                    >{{ t('phrase_retry') }}</Button
                >
                <Button
                    class="mt-2 w-full"
                    type="submit"
                    :disabled="busy || pending || connected === false"
                    >{{ t('phrase_submit') }}</Button
                >
            </form>
            <p v-else class="text-xl font-semibold" role="status">
                {{
                    t(
                        phone && !game.me?.eligible
                            ? 'next_round_wait'
                            : phone && game.me?.submitted
                              ? 'phrase_submitted'
                              : 'phrase_writing',
                    )
                }}
            </p>
        </template>
        <template v-else-if="game.phase === 'presenting'">
            <p class="text-sm font-semibold">{{ t('phrase_presenting') }}</p>
            <blockquote
                v-for="entry in game.round.entries"
                :key="entry.id"
                class="rounded-2xl bg-accent/30 p-3 text-xl font-bold"
            >
                <span class="text-summary">{{ entry.text }}</span
                ><TextReader v-if="phone" :text="entry.text" />
            </blockquote>
        </template>
        <template v-else>
            <h2 class="text-lg font-bold">
                {{
                    t(game.phase === 'voting' ? 'phrase_vote' : 'phrase_reveal')
                }}
            </h2>
            <div class="game-choices">
                <article
                    v-for="entry in game.round.entries"
                    :key="entry.id"
                    class="game-choice"
                    :class="{ chosen: game.me?.choice === entry.id }"
                >
                    <button
                        v-if="phone && game.phase === 'voting'"
                        type="button"
                        :data-testid="'phrase-entry-' + entry.id"
                        :disabled="
                            busy ||
                            connected === false ||
                            !game.me?.eligible ||
                            game.me?.voted ||
                            entry.id === game.me?.ownEntry
                        "
                        @click="sendAction('vote', { choice: entry.id })"
                    >
                        <span class="text-summary">{{ entry.text }}</span
                        ><small v-if="entry.id === game.me?.ownEntry">{{
                            t('phrase_own')
                        }}</small>
                    </button>
                    <p
                        v-else
                        :data-testid="'phrase-entry-' + entry.id"
                        class="text-summary"
                    >
                        {{ entry.text }}
                    </p>
                    <TextReader v-if="phone" :text="entry.text" />
                    <div
                        v-if="entry.author !== undefined"
                        class="flex items-center gap-1"
                    >
                        <PlummoAvatar
                            v-if="author(entry.author)"
                            :color="author(entry.author)!.color"
                            :accessories="author(entry.author)!.accessories"
                            class="w-6 shrink-0"
                        />
                        <div class="min-w-0 text-[10px]">
                            <p class="text-summary">
                                {{ author(entry.author)?.name }}
                            </p>
                            <p>
                                {{
                                    t('phrase_votes', {
                                        count: entry.votes ?? 0,
                                        points: entry.points ?? 0,
                                    })
                                }}
                            </p>
                        </div>
                    </div>
                </article>
            </div>
            <p
                v-if="phone && game.phase === 'voting' && game.me?.voted"
                class="text-sm font-bold"
                role="status"
            >
                {{ t('phrase_vote_saved') }}
            </p>
        </template>
    </div>
</template>
