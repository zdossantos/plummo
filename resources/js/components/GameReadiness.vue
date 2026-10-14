<script setup lang="ts">
import { computed, ref } from 'vue';
import { useMediaQuery } from '@vueuse/core';
import { Button } from '@/components/ui/button';
import PagedList from '@/components/PagedList.vue';
import {
    Drawer,
    DrawerContent,
    DrawerTitle,
    DrawerDescription,
    DrawerClose,
} from '@/components/ui/drawer';
import { useTranslations } from '@/composables/useTranslations';
import type { Player } from '@/types/rooms';
const props = defineProps<{
    players: Player[];
    me: Player;
    disabled: boolean;
    revision?: number;
    chiefId: number | null;
}>();
const emit = defineEmits<{ ready: [value: boolean] }>();
const { t } = useTranslations('rooms');
const ui = useTranslations('interface');
const open = ref(false);
const desktop = useMediaQuery('(min-width: 768px)');
const expected = computed(() =>
    props.players.filter(
        (player) =>
            player.status === 'connected' && player.id !== props.chiefId,
    ),
);
const count = computed(
    () => expected.value.filter((player) => player.ready).length,
);
</script>
<template>
    <div class="readiness-actions" :data-setup-revision="revision">
        <Button
            v-if="me.id !== chiefId"
            type="button"
            data-ready
            :variant="me.ready ? 'outline' : 'secondary'"
            :disabled="disabled || me.status !== 'connected'"
            @click="emit('ready', !me.ready)"
            >{{ t(me.ready ? 'ready_withdraw' : 'ready_confirm') }}</Button
        >
        <Button type="button" variant="outline" @click="open = true">{{
            t('ready_count', { count, total: expected.length })
        }}</Button>
    </div>
    <Drawer v-model:open="open" :swipe-direction="desktop ? 'right' : 'down'">
        <DrawerContent v-if="open" class="game-drawer">
            <DrawerTitle>{{ t('ready_players') }}</DrawerTitle>
            <DrawerDescription>{{ t('ready_hint') }}</DrawerDescription>
            <PagedList :items="expected" :row-height="48">
                <template #default="{ item }"
                    ><div class="flex items-center justify-between gap-3">
                        <span class="text-summary">{{ item.name }}</span
                        ><strong>{{
                            t(item.ready ? 'ready_yes' : 'ready_waiting')
                        }}</strong>
                    </div></template
                >
            </PagedList>
            <DrawerClose as-child
                ><Button variant="secondary">{{
                    ui.t('close')
                }}</Button></DrawerClose
            >
        </DrawerContent>
    </Drawer>
</template>
