<script setup lang="ts">
import { computed, onMounted, ref, watch, onUnmounted } from 'vue';
import { Brain, Music, Pencil, MessageSquare, Check } from '@lucide/vue';
import PagedList from '@/components/PagedList.vue';
import TextReader from '@/components/TextReader.vue';
import { useMediaQuery } from '@vueuse/core';
import {
    Drawer,
    DrawerContent,
    DrawerTitle,
    DrawerDescription,
    DrawerClose,
} from '@/components/ui/drawer';
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
const choosingPacks = ref(false);
const choosingGame = ref(false);
const games = [
    { type: 'quiz', label: 'quiz_name', icon: Brain },
    { type: 'blind_test', label: 'blind_name', icon: Music },
    { type: 'drawing', label: 'drawing_short', icon: Pencil },
    { type: 'phrase', label: 'phrase_short', icon: MessageSquare },
] as const;
const gameLabel = computed(() =>
    t(games.find((game) => game.type === gameType.value)!.label),
);
const desktop = useMediaQuery('(min-width: 768px)');
const ui = useTranslations('interface');
const packSummary = computed(() =>
    selected.value.length
        ? selected.value.length === 1
            ? t('pack_selected')
            : t('packs_selected', { count: selected.value.length })
        : t('packs_all'),
);
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
        class="game-panel setup-board"
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
        <h2 class="text-2xl">
            {{ t(recover ? 'recover_packs' : labels.launch) }}
        </h2>
        <div class="setup-main">
            <div class="setup-fields">
                <label class="setup-type" for="game-type">
                    <span class="font-bold">{{ t('game_type') }}</span>
                    <Button
                        id="game-type"
                        type="button"
                        variant="outline"
                        data-choose-game
                        aria-haspopup="dialog"
                        :aria-expanded="choosingGame"
                        :disabled="recover"
                        @click="choosingGame = true"
                    >
                        {{ gameLabel }}
                    </Button>
                </label>
                <div class="setup-pack-choice">
                    <span class="font-bold">{{ t('packs_label') }}</span>
                    <Button
                        type="button"
                        variant="outline"
                        data-choose-packs
                        @click="choosingPacks = true"
                        >{{ packSummary }}</Button
                    >
                </div>
                <template v-if="!recover">
                    <label class="setup-field font-bold"
                        ><span>{{ t(labels.count) }}</span
                        ><input
                            id="game-rounds"
                            v-model.number="rounds"
                            type="number"
                            :min="
                                ['drawing', 'phrase'].includes(gameType) ? 1 : 5
                            "
                            :max="
                                ['drawing', 'phrase'].includes(gameType)
                                    ? 5
                                    : 30
                            "
                            required
                            class="w-full" /></label
                    ><label class="setup-field font-bold"
                        ><span>{{ t(labels.duration) }}</span
                        ><input
                            id="game-duration"
                            v-model.number="duration"
                            type="number"
                            :min="
                                ['drawing', 'phrase'].includes(gameType)
                                    ? 30
                                    : 10
                            "
                            max="150"
                            required
                            class="w-full"
                    /></label>
                </template>
            </div>
            <div class="setup-details">
                <p v-if="!recover && valid" class="text-sm setup-estimate">
                    {{
                        t('estimated_minutes', {
                            count: Math.ceil(
                                (rounds *
                                    (gameType === 'drawing'
                                        ? connectedPlayers
                                        : 1) *
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
                <p
                    v-if="!recover && valid && gameType === 'drawing'"
                    class="text-sm"
                >
                    {{
                        t('drawing_setup_count', {
                            count: connectedPlayers * rounds,
                            players: connectedPlayers,
                            tours: rounds,
                        })
                    }}
                </p>
                <label class="flex gap-3 text-sm"
                    ><input v-model="repeats" type="checkbox" />{{
                        t('allow_repeats')
                    }}</label
                >
                <p v-if="availability" class="text-sm">
                    {{
                        t(labels.available, {
                            unseen: availability.unseen,
                            total: availability.total,
                        })
                    }}
                </p>
                <p v-if="availability && !valid" role="status" class="text-sm">
                    {{
                        t(
                            gameType === 'phrase' &&
                                !recover &&
                                connectedPlayers < 3
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
            </div>
        </div>
        <p v-if="error" role="alert">{{ error }}</p>
        <Button
            type="submit"
            class="setup-launch"
            :disabled="busy || loading || !valid"
            >{{ t(recover ? 'recover_game' : labels.start) }}</Button
        >
        <Drawer
            v-model:open="choosingGame"
            :swipe-direction="desktop ? 'right' : 'down'"
        >
            <DrawerContent
                v-if="choosingGame"
                class="game-drawer setup-game-drawer"
            >
                <DrawerTitle>{{ t('game_type') }}</DrawerTitle>
                <DrawerDescription>{{
                    t('game_choice_hint')
                }}</DrawerDescription>
                <div class="setup-game-grid">
                    <Button
                        v-for="game in games"
                        :key="game.type"
                        type="button"
                        variant="outline"
                        :data-game-option="game.type"
                        :aria-pressed="gameType === game.type"
                        @click="
                            gameType = game.type;
                            choosingGame = false;
                        "
                    >
                        <component :is="game.icon" aria-hidden="true" />
                        {{ t(game.label) }}
                        <Check
                            v-if="gameType === game.type"
                            class="setup-game-check"
                            aria-hidden="true"
                        />
                    </Button>
                </div>
                <DrawerClose as-child
                    ><Button type="button">{{
                        ui.t('close')
                    }}</Button></DrawerClose
                >
            </DrawerContent>
        </Drawer>
        <Drawer
            v-model:open="choosingPacks"
            :swipe-direction="desktop ? 'right' : 'down'"
        >
            <DrawerContent
                v-if="choosingPacks"
                class="game-drawer setup-pack-drawer"
            >
                <DrawerTitle>{{ t('packs_label') }}</DrawerTitle>
                <DrawerDescription>{{ t('packs_hint') }}</DrawerDescription>
                <PagedList :items="packs" :row-height="60">
                    <template #default="{ item: pack }">
                        <label
                            class="flex items-center gap-3 rounded-xl bg-accent/30 px-4 py-3"
                        >
                            <input
                                v-model="selected"
                                type="checkbox"
                                :value="pack.id"
                                :disabled="
                                    !selected.includes(pack.id) &&
                                    selected.length >= 3
                                "
                            />
                            <span class="text-summary">{{ pack.name }}</span>
                            <TextReader :text="pack.name" />
                        </label>
                    </template>
                </PagedList>
                <p v-if="!loading && !packs.length">{{ t('no_packs') }}</p>
                <DrawerClose as-child
                    ><Button>{{ ui.t('close') }}</Button></DrawerClose
                >
            </DrawerContent>
        </Drawer>
    </form>
</template>
