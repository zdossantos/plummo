<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import DrawingBoard from '@/components/DrawingBoard.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { DrawingGameState, DrawingSender, RoomState } from '@/types/rooms';
const props = defineProps<{
    game: DrawingGameState;
    room: RoomState;
    phone?: boolean;
    busy?: boolean;
    connected?: boolean;
    send?: DrawingSender;
}>();
const { t } = useTranslations('rooms');
const guess = ref('');
const color = ref('#35236b');
const width = ref(4);
const pending = ref(false);
const palette = [
    '#35236b',
    '#f05a78',
    '#4a83e8',
    '#30a080',
    '#f5b83d',
    '#ffffff',
];
const artist = computed(
    () =>
        props.room.players.find((p) => p.id === props.game.round.artistId)
            ?.name ?? '',
);
const editable = computed(
    () => !!props.phone && props.game.me?.canDraw && props.connected !== false,
);
const canGuess = computed(
    () =>
        !!props.phone &&
        props.game.me?.eligible &&
        !props.game.me.found &&
        ['drawing', 'artist_missing'].includes(props.game.phase),
);
async function send(action: string, values: Record<string, unknown>) {
    return (
        (await props.send?.(action, {
            game_id: props.game.id,
            round: props.game.round.number,
            revision: props.game.round.revision,
            ...values,
        })) ?? false
    );
}
async function submit() {
    if (await send('guess', { guess: guess.value })) guess.value = '';
}
watch(
    () => props.game.round.number,
    () => {
        guess.value = '';
        pending.value = false;
    },
);
</script>
<template>
    <div class="mt-6">
        <h1
            v-if="game.phase === 'waiting'"
            class="my-8 text-center text-3xl font-black"
        >
            {{ t('drawing_waiting') }}
        </h1>
        <template v-else-if="game.phase === 'selecting'">
            <h1 class="mb-6 text-2xl font-black lg:text-4xl">
                {{
                    t(
                        phone && game.me?.words.length
                            ? 'drawing_choose'
                            : 'drawing_choosing',
                        { name: artist },
                    )
                }}
            </h1>
            <div v-if="phone" class="grid gap-4">
                <Button
                    v-for="(word, index) in game.me?.words"
                    :key="index"
                    data-testid="drawing-word"
                    class="h-auto whitespace-normal py-5 text-xl"
                    :disabled="busy"
                    @click="send('choose', { choice: index })"
                    >{{ word }}</Button
                >
            </div>
        </template>
        <template v-else>
            <h1 class="mb-5 text-2xl font-black lg:text-4xl">
                {{
                    t(game.round.word ? 'drawing_revealed' : 'drawing_artist', {
                        word: game.round.word ?? '',
                        name: artist,
                    })
                }}
            </h1>
            <p
                v-if="game.phase === 'artist_missing'"
                class="mb-5 font-semibold"
            >
                {{ t('drawing_absent') }}
            </p>
            <p
                v-if="phone && game.me?.word && !game.round.word"
                class="mb-4 text-xl font-bold"
            >
                {{ t('drawing_secret', { word: game.me.word }) }}
            </p>
            <DrawingBoard
                :key="`${game.id}-${game.round.number}-${game.round.revision}`"
                :strokes="game.round.canvas"
                :editable="editable"
                :busy="busy"
                :color="color"
                :width="width"
                :send="(values) => send('stroke', values)"
                @pending="pending = $event"
            />
            <div v-if="editable" class="mt-4 flex flex-wrap items-center gap-3">
                <button
                    v-for="(value, index) in palette"
                    :key="value"
                    type="button"
                    :aria-label="t('drawing_color', { number: index + 1 })"
                    :aria-pressed="color === value"
                    :style="{ backgroundColor: value }"
                    class="size-10 rounded-full border-2"
                    :class="
                        color === value
                            ? 'ring-2 ring-primary ring-offset-2'
                            : ''
                    "
                    @click="color = value"
                />
                <label class="flex items-center gap-2"
                    >{{ t('drawing_width')
                    }}<input
                        v-model.number="width"
                        type="range"
                        min="2"
                        max="12"
                /></label>
                <Button
                    variant="outline"
                    :disabled="busy || pending || !game.round.canvas.length"
                    @click="send('undo', {})"
                    >{{ t('drawing_undo') }}</Button
                >
                <Button
                    variant="outline"
                    :disabled="busy || pending || !game.round.canvas.length"
                    @click="send('clear', {})"
                    >{{ t('drawing_clear') }}</Button
                >
            </div>
            <form
                v-if="canGuess"
                class="mt-5 flex gap-3"
                @submit.prevent="submit"
            >
                <label class="min-w-0 flex-1"
                    ><span class="sr-only">{{ t('drawing_guess') }}</span
                    ><input
                        id="drawing-guess"
                        v-model="guess"
                        maxlength="150"
                        required
                        autocomplete="off"
                        class="w-full rounded-xl border bg-background p-3"
                        :placeholder="t('drawing_guess')"
                /></label>
                <Button type="submit" :disabled="busy || !guess.length">{{
                    t('drawing_submit')
                }}</Button>
            </form>
            <p
                v-if="canGuess && game.me?.near"
                role="status"
                class="mt-4 font-bold"
            >
                {{ t('drawing_near') }}
            </p>
            <p
                v-if="phone && game.me?.found"
                role="status"
                class="mt-4 font-bold"
            >
                {{ t('drawing_found', { count: game.me.points }) }}
            </p>
            <p
                v-if="
                    phone &&
                    !game.me?.eligible &&
                    !game.me?.word &&
                    !game.round.word
                "
                class="mt-4"
            >
                {{ t('drawing_next') }}
            </p>
            <Button
                v-if="phone && game.me?.canSkip"
                class="mt-5"
                :disabled="busy || game.me.votedSkip"
                @click="send('skip', {})"
                >{{
                    t(game.me.votedSkip ? 'drawing_skip_saved' : 'drawing_skip')
                }}</Button
            >
            <p v-if="phone && game.round.word" class="mt-4 text-xl font-bold">
                {{ t('drawing_points', { count: game.me?.points ?? 0 }) }}
            </p>
        </template>
    </div>
</template>
