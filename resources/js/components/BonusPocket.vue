<script setup lang="ts">
import { computed, ref, watch } from 'vue';
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
const hidden = computed(() =>
    props.bonuses.effects.some((e) => e.kind === 'mystery'),
);
const strike = computed(() =>
    props.bonuses.effects.some((e) => e.kind === 'strike'),
);
const defensive = (kind: string) =>
    ['shield', 'soap', 'boomerang'].includes(kind);
const helpful = (kind: string) =>
    [
        'shield',
        'soap',
        'boomerang',
        'pinata',
        'freeze',
        'extension',
        'overtime',
        'last_breath',
        'borrow',
    ].includes(kind);
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
        <button
            v-if="bonuses.pending"
            type="button"
            class="bonus-pending"
            :aria-label="t('bonus_new')"
            :disabled="busy || connected === false"
            @click="choosing = true"
        >
            <BonusIcon
                :kind="hidden ? 'mystery' : bonuses.pending.kind"
            /><small>{{ t('bonus_new') }}</small>
        </button>
        <div v-for="slot in 2" :key="slot" class="bonus-slot">
            <template v-if="bonuses.inventory[slot - 1]">
                <button
                    v-if="!hidden"
                    type="button"
                    class="bonus-info"
                    :aria-label="
                        t('bonus_info', {
                            name: hidden
                                ? t('bonus_mystery_name')
                                : t(
                                      'bonus_' +
                                          bonuses.inventory[slot - 1]!.kind,
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
                    v-if="!hidden && info === bonuses.inventory[slot - 1]!.id"
                    role="status"
                    class="bonus-tooltip"
                >
                    {{ t('bonus_hint_' + bonuses.inventory[slot - 1]!.kind) }}
                </p>
                <button
                    type="button"
                    class="bonus-object"
                    :class="{
                        'bonus-carried-away':
                            strike &&
                            !defensive(bonuses.inventory[slot - 1]!.kind),
                        'bonus-mystery': hidden,
                        'bonus-helpful':
                            !hidden &&
                            helpful(bonuses.inventory[slot - 1]!.kind),
                        'bonus-prank':
                            !hidden &&
                            !helpful(bonuses.inventory[slot - 1]!.kind),
                    }"
                    :data-bonus="bonuses.inventory[slot - 1]!.kind"
                    :aria-label="
                        t('bonus_launch', {
                            name: hidden
                                ? t('bonus_mystery_name')
                                : t(
                                      'bonus_' +
                                          bonuses.inventory[slot - 1]!.kind,
                                  ),
                        })
                    "
                    :disabled="
                        busy ||
                        connected === false ||
                        !bonuses.canUse ||
                        bonuses.inventory[slot - 1]!.canUse === false
                    "
                    @click="emit('launch', bonuses.inventory[slot - 1]!.id)"
                >
                    <BonusIcon
                        :kind="
                            hidden
                                ? 'mystery'
                                : bonuses.inventory[slot - 1]!.kind
                        "
                    /><small>{{
                        hidden
                            ? t('bonus_mystery_name')
                            : t('bonus_' + bonuses.inventory[slot - 1]!.kind)
                    }}</small>
                    <span v-if="!hidden" class="bonus-category">{{
                        t(
                            helpful(bonuses.inventory[slot - 1]!.kind)
                                ? 'bonus_category_helpful'
                                : 'bonus_category_prank',
                        )
                    }}</span>
                </button>
            </template>
            <span v-else class="bonus-empty" :aria-label="t('bonus_empty')"
                >+</span
            >
        </div>
        <div v-if="strike" class="bonus-strike-sign" aria-hidden="true">
            <BonusIcon kind="strike" />
        </div>
        <Drawer v-model:open="choosing">
            <DrawerContent
                v-if="choosing && bonuses.pending"
                class="game-drawer bonus-replace-drawer"
            >
                <DrawerTitle>{{ t('bonus_replace_title') }}</DrawerTitle>
                <DrawerDescription>{{
                    t('bonus_replace_hint', {
                        name: hidden
                            ? t('bonus_mystery_name')
                            : t('bonus_' + bonuses.pending.kind),
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
                        <BonusIcon :kind="hidden ? 'mystery' : item.kind" />{{
                            t('bonus_replace', {
                                name: hidden
                                    ? t('bonus_mystery_name')
                                    : t('bonus_' + item.kind),
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
