<script setup lang="ts">
import { ref, watch } from 'vue';
import { Info } from '@lucide/vue';
import BonusIcon from '@/components/BonusIcon.vue';
import {
    Drawer,
    DrawerContent,
    DrawerTitle,
    DrawerDescription,
    DrawerClose,
} from '@/components/ui/drawer';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { BonusState } from '@/types/rooms';
const props = defineProps<{
    bonuses: BonusState;
    busy?: boolean;
    connected?: boolean;
}>();
const emit = defineEmits<{
    launch: [id: string];
    replace: [itemId: string, replaceId: string | null];
}>();
const { t } = useTranslations('rooms');
const info = ref<string | null>(null);
const choosing = ref(false);
watch(
    () => props.bonuses.pending?.id,
    () => {
        choosing.value = false;
    },
);
watch(
    () => props.bonuses.inventory,
    () => {
        if (!props.bonuses.inventory.some((item) => item.id === info.value))
            info.value = null;
    },
);
</script>
<template>
    <div
        v-if="bonuses.enabled"
        class="bonus-pocket"
        :aria-label="t('bonus_pocket')"
    >
        <div v-for="slot in 2" :key="slot" class="bonus-slot">
            <template v-if="bonuses.inventory[slot - 1]">
                <button
                    type="button"
                    class="bonus-info"
                    :aria-label="
                        t('bonus_info', {
                            name: t(
                                'bonus_' + bonuses.inventory[slot - 1]!.kind,
                            ),
                        })
                    "
                    :aria-expanded="info === bonuses.inventory[slot - 1]!.id"
                    @click="
                        info =
                            info === bonuses.inventory[slot - 1]!.id
                                ? null
                                : bonuses.inventory[slot - 1]!.id
                    "
                >
                    <Info />
                </button>
                <p
                    v-if="info === bonuses.inventory[slot - 1]!.id"
                    role="status"
                    class="bonus-tooltip"
                >
                    {{ t('bonus_hint_' + bonuses.inventory[slot - 1]!.kind) }}
                </p>
                <button
                    type="button"
                    class="bonus-object"
                    :data-bonus="bonuses.inventory[slot - 1]!.kind"
                    :aria-label="
                        t('bonus_launch', {
                            name: t(
                                'bonus_' + bonuses.inventory[slot - 1]!.kind,
                            ),
                        })
                    "
                    :disabled="busy || connected === false || !bonuses.canUse"
                    @click="emit('launch', bonuses.inventory[slot - 1]!.id)"
                >
                    <BonusIcon
                        :kind="bonuses.inventory[slot - 1]!.kind"
                    /><small>{{
                        t('bonus_' + bonuses.inventory[slot - 1]!.kind)
                    }}</small>
                </button>
            </template>
            <span v-else class="bonus-empty" :aria-label="t('bonus_empty')"
                >+</span
            >
        </div>
        <button
            v-if="bonuses.pending"
            type="button"
            class="bonus-pending"
            :aria-label="t('bonus_new')"
            :disabled="busy || connected === false"
            @click="choosing = true"
        >
            <BonusIcon :kind="bonuses.pending.kind" /><small>{{
                t('bonus_new')
            }}</small>
        </button>
        <Drawer v-model:open="choosing">
            <DrawerContent
                v-if="choosing && bonuses.pending"
                class="game-drawer bonus-replace-drawer"
            >
                <DrawerTitle>{{ t('bonus_replace_title') }}</DrawerTitle>
                <DrawerDescription>{{
                    t('bonus_replace_hint', {
                        name: t('bonus_' + bonuses.pending.kind),
                    })
                }}</DrawerDescription>
                <div class="bonus-replace-options">
                    <Button
                        v-for="item in bonuses.inventory"
                        :key="item.id"
                        type="button"
                        :disabled="busy || connected === false"
                        @click="emit('replace', bonuses.pending!.id, item.id)"
                    >
                        <BonusIcon :kind="item.kind" />{{
                            t('bonus_replace', {
                                name: t('bonus_' + item.kind),
                            })
                        }}
                    </Button>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="busy || connected === false"
                    @click="emit('replace', bonuses.pending.id, null)"
                    >{{ t('bonus_discard') }}</Button
                >
                <DrawerClose as-child
                    ><Button type="button" variant="outline">{{
                        t('bonus_later')
                    }}</Button></DrawerClose
                >
            </DrawerContent>
        </Drawer>
    </div>
</template>
