<script setup lang="ts">
import { computed, onMounted, ref, watch, onUnmounted } from 'vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
const props = defineProps<{
    code: string;
    busy: boolean;
    recover?: boolean;
    initialPacks?: number[];
}>();
const emit = defineEmits<{ start: [settings: object] }>();
const { t } = useTranslations('rooms');
const packs = ref<{ id: number; name: string }[]>([]);
const selected = ref<number[]>(props.initialPacks ?? []);
const availability = ref<{ total: number; unseen: number } | null>(null);
const rounds = ref(10);
const duration = ref(30);
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
        (repeats.value
            ? count.value > 0
            : count.value >= (props.recover ? 1 : rounds.value)),
);
async function load() {
    controller?.abort();
    const current = new AbortController();
    controller = current;
    loading.value = true;
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
            body: JSON.stringify(
                selected.value.length ? { packs: selected.value } : {},
            ),
        });
        if (!response.ok) throw new Error();
        const data = await response.json();
        packs.value = data.packs;
        availability.value = data.availability;
    } catch {
        if (!current.signal.aborted) error.value = t('room_error');
    } finally {
        if (controller === current) loading.value = false;
    }
}
watch(selected, () => void load());
onMounted(() => void load());
onUnmounted(() => controller?.abort());
</script>
<template>
    <form
        class="mt-6 rounded-3xl bg-card p-6"
        @submit.prevent="
            emit('start', {
                type: 'quiz',
                packs: selected,
                rounds,
                duration,
                allow_repeats: repeats,
            })
        "
    >
        <h2 class="text-2xl font-black">
            {{ t(recover ? 'recover_packs' : 'launch_quiz') }}
        </h2>
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
                t('available_questions', {
                    unseen: availability.unseen,
                    total: availability.total,
                })
            }}
        </p>
        <div v-if="!recover" class="mt-5 grid grid-cols-2 gap-4">
            <label class="font-semibold"
                >{{ t('question_count')
                }}<input
                    id="game-rounds"
                    v-model.number="rounds"
                    type="number"
                    min="5"
                    max="30"
                    required
                    class="mt-2 w-full rounded-xl border bg-background p-3"
            /></label>
            <label class="font-semibold"
                >{{ t('answer_duration')
                }}<input
                    id="game-duration"
                    v-model.number="duration"
                    type="number"
                    min="10"
                    max="150"
                    required
                    class="mt-2 w-full rounded-xl border bg-background p-3"
            /></label>
        </div>
        <p v-if="!recover" class="mt-3 text-sm text-muted-foreground">
            {{
                t('estimated_minutes', {
                    count: Math.ceil((rounds * (duration + 3)) / 60),
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
            {{ t('contents_exhausted') }}
        </p>
        <Button
            class="mt-5 w-full"
            type="submit"
            :disabled="busy || loading || !valid"
            >{{ t(recover ? 'recover_game' : 'start_game') }}</Button
        >
    </form>
</template>
