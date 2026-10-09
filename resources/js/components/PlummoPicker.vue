<script setup lang="ts">
import { computed, ref } from 'vue';
import { useMediaQuery } from '@vueuse/core';
import { X } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import PlummoAvatar from '@/components/PlummoAvatar.vue';
import PageControls from '@/components/PageControls.vue';
import {
    Drawer,
    DrawerContent,
    DrawerTitle,
    DrawerDescription,
    DrawerTrigger,
    DrawerClose,
} from '@/components/ui/drawer';
import { useTranslations } from '@/composables/useTranslations';
import { selectAccessory, randomAppearance } from '@/lib/plummo';
import type { Catalog } from '@/types/rooms';
const props = defineProps<{ catalog: Catalog }>();
const color = defineModel<string>('color', { required: true });
const accessories = defineModel<string[]>('accessories', { required: true });
const { t } = useTranslations('plummo');
const ui = useTranslations('interface');
const desktop = useMediaQuery('(min-width: 768px)');
const slots = computed(
    () =>
        props.catalog.slots ?? [
            ...new Set(props.catalog.accessories.map((item) => item.slot)),
        ],
);
const page = ref(0);
const paths: Record<string, string> = {
    head: 'M12 40Q15 30 25 30L29 12Q40 4 51 12L55 30Q65 30 68 40Z',
    face: 'M8 28Q20 18 32 28L48 28Q60 18 72 28L70 43Q58 52 48 40L32 40Q22 52 10 43Z',
    neck: 'M15 16L40 30L65 16V53L40 40L15 53Z',
    hand: 'M34 55V30Q22 18 30 10Q35 7 40 17Q48 7 53 12Q60 20 47 30V55Z',
};
function select(id: string) {
    accessories.value = selectAccessory(
        accessories.value,
        id,
        props.catalog.accessories,
    );
}
function randomize() {
    const next = randomAppearance(props.catalog);
    color.value = next.color;
    accessories.value = next.accessories;
}
function worn(slot: string) {
    return props.catalog.accessories.find(
        (item) => item.slot === slot && accessories.value.includes(item.id),
    );
}
</script>
<template>
    <div class="wardrobe">
        <div class="wardrobe-tools">
            <fieldset class="color-palette">
                <legend>{{ t('colors') }}</legend>
                <button
                    v-for="option in catalog.colors"
                    :key="option.id"
                    type="button"
                    :aria-label="t('palette.' + option.id)"
                    :aria-pressed="color === option.id"
                    :style="{ background: option.color }"
                    @click="color = option.id"
                >
                    <span v-if="color === option.id">✓</span>
                </button>
            </fieldset>
            <div class="accessory-slots">
                <Drawer
                    v-for="slot in slots"
                    :key="slot"
                    :swipe-direction="desktop ? 'right' : 'down'"
                    @update:open="page = 0"
                >
                    <DrawerTrigger as-child>
                        <button
                            type="button"
                            class="accessory-slot"
                            :aria-label="t(slot)"
                            :class="{ equipped: worn(slot) }"
                        >
                            <svg viewBox="0 0 80 64" aria-hidden="true">
                                <path :d="paths[slot]" />
                                <text x="40" y="38" text-anchor="middle">
                                    {{ worn(slot) ? '✓' : '+' }}
                                </text>
                            </svg>
                            <span>{{ t(slot) }}</span
                            ><small v-if="worn(slot)">{{
                                t('items.' + worn(slot)!.id)
                            }}</small>
                        </button>
                    </DrawerTrigger>
                    <DrawerContent class="game-drawer wardrobe-drawer">
                        <div class="flex items-center justify-between gap-3">
                            <DrawerTitle>{{ t(slot) }}</DrawerTitle
                            ><DrawerClose as-child
                                ><Button
                                    type="button"
                                    variant="secondary"
                                    size="icon"
                                    class="drawer-close"
                                    :aria-label="ui.t('close')"
                                >
                                    <X
                                        class="size-6"
                                        aria-hidden="true"
                                    /> </Button
                            ></DrawerClose>
                        </div>
                        <DrawerDescription>{{ t('limit') }}</DrawerDescription>
                        <div class="drawer-accessories">
                            <button
                                v-for="item in catalog.accessories
                                    .filter((item) => item.slot === slot)
                                    .slice(page * 4, page * 4 + 4)"
                                :key="item.id"
                                type="button"
                                :aria-label="t('items.' + item.id)"
                                :aria-pressed="accessories.includes(item.id)"
                                @click="select(item.id)"
                            >
                                <PlummoAvatar
                                    :color="color"
                                    :accessories="[item.id]"
                                />
                                <span>{{ t('items.' + item.id) }}</span
                                ><small v-if="accessories.includes(item.id)">{{
                                    ui.t('selected')
                                }}</small>
                            </button>
                        </div>
                        <PageControls
                            v-model="page"
                            :total="
                                Math.ceil(
                                    catalog.accessories.filter(
                                        (item) => item.slot === slot,
                                    ).length / 4,
                                )
                            "
                        />
                        <button
                            type="button"
                            class="game-action"
                            :disabled="!worn(slot)"
                            @click="
                                accessories = accessories.filter(
                                    (id) => id !== worn(slot)?.id,
                                )
                            "
                        >
                            {{ t('remove') }}
                        </button>
                    </DrawerContent>
                </Drawer>
            </div>
            <button
                type="button"
                class="game-action random-action"
                @click="randomize"
            >
                ⚄ {{ t('random') }}
            </button>
        </div>
        <PlummoAvatar
            :color="color"
            :accessories="accessories"
            :label="t('preview')"
            class="wardrobe-plummo"
        />
    </div>
</template>
