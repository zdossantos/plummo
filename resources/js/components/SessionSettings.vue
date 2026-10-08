<script setup lang="ts">
import {
    Drawer,
    DrawerContent,
    DrawerTitle,
    DrawerDescription,
    DrawerClose,
} from '@/components/ui/drawer';
import { useMediaQuery } from '@vueuse/core';
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
const extending = ref(false);
const desktop = useMediaQuery('(min-width: 768px)');
const ui = useTranslations('interface');
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
        class="game-panel session-board"
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
        <div class="session-command-board">
            <form class="grid gap-2" @submit.prevent="configure('configure')">
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
            <div class="session-secondary-actions">
                <Button
                    variant="outline"
                    :disabled="busy"
                    data-session-extend
                    @click="extending = true"
                    >{{ t('extend') }}</Button
                >
                <Button
                    variant="ghost"
                    :disabled="busy"
                    class="w-full"
                    @click="configure('restart')"
                    >{{ t('restart') }}</Button
                >
            </div>
        </div>
        <Drawer
            v-model:open="extending"
            :swipe-direction="desktop ? 'right' : 'down'"
        >
            <DrawerContent v-if="extending" class="game-drawer session-drawer">
                <DrawerTitle>{{ t('extend') }}</DrawerTitle>
                <DrawerDescription>{{ t('extend_hint') }}</DrawerDescription>
                <form
                    class="grid gap-2"
                    @submit.prevent="
                        emit('save', {
                            action: 'extend',
                            extra: Number(extra),
                        });
                        extending = false;
                    "
                >
                    <label for="session-extra" class="block font-bold">{{
                        t('extend_label')
                    }}</label>

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

                <DrawerClose as-child
                    ><Button variant="secondary">{{
                        ui.t('close')
                    }}</Button></DrawerClose
                >
            </DrawerContent>
        </Drawer>
    </section>
</template>
