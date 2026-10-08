<script setup lang="ts">
import PlummoAvatar from '@/components/PlummoAvatar.vue';
import { useTranslations } from '@/composables/useTranslations';
import { selectAccessory } from '@/lib/plummo';
import type { Catalog } from '@/types/rooms';
const props = defineProps<{ catalog: Catalog }>();
const color = defineModel<string>('color', { required: true });
const accessories = defineModel<string[]>('accessories', { required: true });
const { t } = useTranslations('plummo');
function select(id: string) {
    accessories.value = selectAccessory(
        accessories.value,
        id,
        props.catalog.accessories,
    );
}
</script>
<template>
    <div class="grid items-start gap-6 md:grid-cols-[220px_1fr]">
        <div class="rounded-3xl bg-accent/40 p-3 md:sticky md:top-6">
            <PlummoAvatar
                :color="color"
                :accessories="accessories"
                class="mx-auto w-44 md:w-full"
            />
        </div>
        <div class="space-y-6">
            <fieldset>
                <legend class="mb-3 font-bold">{{ t('colors') }}</legend>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="option in catalog.colors"
                        :key="option.id"
                        type="button"
                        :aria-pressed="color === option.id"
                        :aria-label="t('palette.' + option.id)"
                        class="flex min-h-11 items-center gap-2 rounded-full border-2 px-3 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                        :class="
                            color === option.id
                                ? 'border-primary bg-accent'
                                : 'border-transparent bg-background'
                        "
                        @click="color = option.id"
                    >
                        <span
                            class="size-5 rounded-full"
                            :style="{ background: option.color }"
                        />{{ t('palette.' + option.id) }}
                    </button>
                </div>
            </fieldset>
            <p class="text-sm text-muted-foreground">{{ t('limit') }}</p>
            <fieldset v-for="slot in catalog.slots" :key="slot">
                <legend class="mb-3 font-bold">{{ t(slot) }}</legend>
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                    <button
                        v-for="item in catalog.accessories.filter(
                            (accessory) => accessory.slot === slot,
                        )"
                        :key="item.id"
                        type="button"
                        :aria-pressed="accessories.includes(item.id)"
                        :aria-label="t('items.' + item.id)"
                        class="rounded-2xl border-2 p-2 text-xs font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                        :class="
                            accessories.includes(item.id)
                                ? 'border-primary bg-accent'
                                : 'border-transparent bg-background hover:bg-accent/40'
                        "
                        @click="select(item.id)"
                    >
                        <PlummoAvatar
                            :color="color"
                            :accessories="[item.id]"
                            class="mx-auto w-20"
                        />
                        {{ t('items.' + item.id) }}
                    </button>
                </div>
            </fieldset>
        </div>
    </div>
</template>
