<script setup lang="ts">
import { computed, onMounted, ref, watch, onUnmounted } from 'vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
const props = defineProps<{
    code: string;
    busy: boolean;
    recover?: boolean;
    initialPacks?: number[];
    initialType?: 'quiz' | 'blind_test' | 'drawing' | 'phrase';
}>();
const emit = defineEmits<{ start: [settings: object] }>();
const { t } = useTranslations('rooms');
const packs = ref<{ id: number; name: string }[]>([]);
const selected = ref<number[]>(props.initialPacks ?? []);
const availability = ref<{ total: number; unseen: number } | null>(null);
const gameType = ref<'quiz' | 'blind_test' | 'drawing' | 'phrase'>(
    props.initialType ?? 'quiz',
);
const settings = ref({
    quiz: { rounds: 10, duration: 30 },
    blind_test: { rounds: 10, duration: 30 },
    drawing: { rounds: 1, duration: 90 },
    phrase: { rounds: 1, duration: 60 },
});
const rounds = computed({
    get: () => settings.value[gameType.value].rounds,
    set: (value) => {
        settings.value[gameType.value].rounds = value;
    },
});
const duration = computed({
    get: () => settings.value[gameType.value].duration,
    set: (value) => {
        settings.value[gameType.value].duration = value;
    },
});
const connectedPlayers = ref(0);
const labels = computed(() =>
    gameType.value === 'phrase'
        ? {
              launch: 'launch_phrase',
              available: 'available_phrases',
              count: 'phrase_tours',
              duration: 'phrase_duration',
              start: 'start_phrase',
          }
        : gameType.value === 'drawing'
          ? {
                launch: 'launch_drawing',
                available: 'available_words',
                count: 'drawing_tours',
                duration: 'drawing_duration',
                start: 'start_drawing',
            }
          : gameType.value === 'quiz'
            ? {
                  launch: 'launch_quiz',
                  available: 'available_questions',
                  count: 'question_count',
                  duration: 'answer_duration',
                  start: 'start_game',
              }
            : {
                  launch: 'launch_blind',
                  available: 'available_clips',
                  count: 'clip_count',
                  duration: 'clip_duration',
                  start: 'start_blind',
              },
);
const required = computed(() =>
    gameType.value === 'drawing'
        ? props.recover || repeats.value
            ? 3
            : connectedPlayers.value * rounds.value * 3
        : props.recover || repeats.value
          ? 1
          : rounds.value,
);
const distinctSongs = ref<number | null>(null);
const repeats = ref(false);
const loading = ref(true);
const error = ref('');
let controller: AbortController | undefined;
const count = computed(
    () =>
        (repeats.value
            ? availability.value?.total
            : availability.value?.unseen) ?? 0,
);
const valid = computed(
    () =>
        !!availability.value &&
        (gameType.value !== 'blind_test' || (distinctSongs.value ?? 0) >= 8) &&
        (gameType.value !== 'drawing' ||
            props.recover ||
            connectedPlayers.value >= 2) &&
        (gameType.value !== 'phrase' ||
            props.recover ||
            connectedPlayers.value >= 3) &&
        count.value >= required.value,
);
async function load() {
    controller?.abort();
    const current = new AbortController();
    controller = current;
    loading.value = true;
    availability.value = null;
    error.value = '';
    try {
        const response = await fetch(`/rooms/${props.code}/game-options`, {
            method: 'POST',
            signal: current.signal,
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN':
                    document.querySelector<HTMLMetaElement>(
                        'meta[name="csrf-token"]',
                    )?.content ?? '',
            },
            body: JSON.stringify({
                type: gameType.value,
                ...(selected.value.length ? { packs: selected.value } : {}),
            }),
        });
        if (!response.ok) throw new Error();
        const data = await response.json();
        if (controller !== current) return;
        packs.value = data.packs;
        connectedPlayers.value = data.connectedPlayers;
        distinctSongs.value = data.distinctSongs;
        availability.value = data.availability;
    } catch {
        if (!current.signal.aborted) error.value = t('room_error');
    } finally {
        if (controller === current) loading.value = false;
    }
}
watch([selected, gameType], () => void load());
onMounted(() => void load());
onUnmounted(() => controller?.abort());
</script>
<template>
    <form
        class="mt-6 rounded-3xl bg-card p-6"
        @submit.prevent="
            emit('start', {
                type: gameType,
                packs: selected,
                rounds,
                duration,
                allow_repeats: repeats,
            })
        "
    >
        <h2 class="text-2xl font-black">
            {{ t(recover ? 'recover_packs' : labels.launch) }}
        </h2>
        <label v-if="!recover" class="mt-5 block font-bold">
            {{ t('game_type') }}
            <select
                id="game-type"
                v-model="gameType"
                class="mt-2 w-full rounded-xl border bg-background p-3"
            >
                <option value="quiz">{{ t('quiz_name') }}</option>
                <option value="blind_test">{{ t('blind_name') }}</option>
                <option value="drawing">{{ t('drawing_name') }}</option>
                <option value="phrase">{{ t('phrase_name') }}</option>
            </select>
        </label>
        <p class="mt-2 text-sm text-muted-foreground">{{ t('packs_hint') }}</p>
        <fieldset class="mt-5 space-y-3">
            <legend class="mb-2 font-bold">{{ t('packs_label') }}</legend>
            <label
                v-for="pack in packs"
                :key="pack.id"
                class="flex items-center gap-3 rounded-xl bg-accent/30 px-4 py-3"
            >
                <input
                    v-model="selected"
                    type="checkbox"
                    :value="pack.id"
                    :disabled="
                        !selected.includes(pack.id) && selected.length >= 3
                    "
                />{{ pack.name }}
            </label>
        </fieldset>
        <p v-if="!loading && !packs.length" class="mt-4">{{ t('no_packs') }}</p>
        <p v-if="availability" class="mt-4 text-sm">
            {{
                t(labels.available, {
                    unseen: availability.unseen,
                    total: availability.total,
                })
            }}
        </p>
        <div v-if="!recover" class="mt-5 grid grid-cols-2 gap-4">
            <label class="font-semibold"
                >{{ t(labels.count)
                }}<input
                    id="game-rounds"
                    v-model.number="rounds"
                    type="number"
                    :min="['drawing', 'phrase'].includes(gameType) ? 1 : 5"
                    :max="['drawing', 'phrase'].includes(gameType) ? 5 : 30"
                    required
                    class="mt-2 w-full rounded-xl border bg-background p-3"
            /></label>
            <label class="font-semibold"
                >{{ t(labels.duration)
                }}<input
                    id="game-duration"
                    v-model.number="duration"
                    type="number"
                    :min="['drawing', 'phrase'].includes(gameType) ? 30 : 10"
                    max="150"
                    required
                    class="mt-2 w-full rounded-xl border bg-background p-3"
            /></label>
        </div>
        <p v-if="!recover" class="mt-3 text-sm text-muted-foreground">
            {{
                t('estimated_minutes', {
                    count: Math.ceil(
                        (rounds *
                            (gameType === 'drawing' ? connectedPlayers : 1) *
                            (duration +
                                (gameType === 'phrase'
                                    ? connectedPlayers * 12 + 33
                                    : gameType === 'drawing'
                                      ? 18
                                      : 3))) /
                            60,
                    ),
                })
            }}
        </p>
        <p v-if="!recover && gameType === 'drawing'" class="mt-3 text-sm">
            {{
                t('drawing_count', {
                    count: connectedPlayers * rounds,
                    players: connectedPlayers,
                    tours: rounds,
                })
            }}
        </p>
        <label class="mt-5 flex gap-3"
            ><input v-model="repeats" type="checkbox" />{{
                t('allow_repeats')
            }}</label
        >
        <p v-if="error" role="alert" class="mt-3">{{ error }}</p>
        <p v-else-if="availability && !valid" class="mt-3 text-sm">
            {{
                t(
                    gameType === 'phrase' && !recover && connectedPlayers < 3
                        ? 'phrase_minimum'
                        : gameType === 'drawing' &&
                            !recover &&
                            connectedPlayers < 2
                          ? 'drawing_minimum'
                          : gameType === 'blind_test' &&
                              (distinctSongs ?? 0) < 8
                            ? 'blind_catalogue_small'
                            : 'contents_exhausted',
                )
            }}
        </p>
        <Button
            class="mt-5 w-full"
            type="submit"
            :disabled="busy || loading || !valid"
            >{{ t(recover ? 'recover_game' : labels.start) }}</Button
        >
    </form>
</template>
