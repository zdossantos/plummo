<script setup lang="ts">
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { RoomState } from '@/types/rooms';
const props = defineProps<{ room: RoomState; busy: boolean }>();
const emit = defineEmits<{
    save: [
        data: {
            action: string;
            target?: number | null;
            extra?: number;
            confirm?: boolean;
        },
    ];
}>();
const { t } = useTranslations('rooms');
const target = ref(props.room.pointTarget ?? 1000);
const unlimited = ref(props.room.pointTarget === null);
const extra = ref(500);
watch(
    () => props.room.pointTarget,
    (value) => {
        target.value = value ?? 1000;
        unlimited.value = value === null;
    },
);
function configure(action: 'configure' | 'restart') {
    if (action === 'restart' && !window.confirm(t('confirm_restart'))) return;
    emit('save', {
        action,
        target: unlimited.value ? null : Number(target.value),
        ...(action === 'restart' ? { confirm: true } : {}),
    });
}
</script>
<template>
    <section
        class="mt-6 rounded-2xl bg-card p-5"
        :aria-label="t('session_settings')"
    >
        <h2 class="text-xl font-bold">{{ t('session_settings') }}</h2>
        <p class="mt-2 text-sm text-muted-foreground">
            {{
                room.pointTarget === null
                    ? t('nonstop')
                    : t('point_target', { count: room.pointTarget })
            }}
        </p>
        <form class="mt-4 space-y-4" @submit.prevent="configure('configure')">
            <label class="flex items-center gap-3 font-semibold">
                <input
                    v-model="unlimited"
                    type="checkbox"
                    class="size-5 accent-primary"
                />{{ t('nonstop') }}
            </label>
            <div v-if="!unlimited">
                <label for="session-target" class="mb-2 block font-bold">{{
                    t('target_label')
                }}</label>
                <input
                    id="session-target"
                    v-model="target"
                    type="number"
                    min="1"
                    max="4294967295"
                    step="1"
                    required
                    class="w-full rounded-xl border border-primary/25 bg-background p-3"
                />
            </div>
            <Button type="submit" :disabled="busy" class="w-full">{{
                t('save_target')
            }}</Button>
        </form>
        <form
            class="mt-5 space-y-3 border-t pt-5"
            @submit.prevent="
                emit('save', { action: 'extend', extra: Number(extra) })
            "
        >
            <label for="session-extra" class="block font-bold">{{
                t('extend_label')
            }}</label>
            <p class="text-sm text-muted-foreground">{{ t('extend_hint') }}</p>
            <input
                id="session-extra"
                v-model="extra"
                type="number"
                min="1"
                max="4294967295"
                step="1"
                required
                class="w-full rounded-xl border border-primary/25 bg-background p-3"
            />
            <Button
                type="submit"
                variant="outline"
                :disabled="busy"
                class="w-full"
                >{{ t('extend') }}</Button
            >
        </form>
        <Button
            variant="ghost"
            :disabled="busy"
            class="mt-4 w-full text-destructive"
            @click="configure('restart')"
            >{{ t('restart') }}</Button
        >
    </section>
</template>
