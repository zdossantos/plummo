<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useElementSize, useMediaQuery } from '@vueuse/core';
import {
    Drawer,
    DrawerContent,
    DrawerTitle,
    DrawerDescription,
    DrawerTrigger,
    DrawerClose,
} from '@/components/ui/drawer';
import PageControls from '@/components/PageControls.vue';
import { useTranslations } from '@/composables/useTranslations';
const props = defineProps<{ text: string; label?: string }>();
const { t } = useTranslations('interface');
const desktop = useMediaQuery('(min-width: 768px)');
const page = ref(0);
const reader = ref<HTMLElement>();
const { width, height } = useElementSize(reader);
const parts = computed(() => {
    const element = reader.value;
    const style = element ? getComputedStyle(element) : null;
    const context = document.createElement('canvas').getContext('2d');
    if (context) context.font = style?.font || '18px sans-serif';
    const lineHeight = parseFloat(style?.lineHeight || '') || 28;
    const lines = Math.max(
        1,
        Math.floor((height.value || 100) / lineHeight) - 1,
    );
    const available = Math.max(40, width.value || 200);
    const pages: string[] = [];
    let part = '',
        line = 0,
        used = 0;
    for (const char of Array.from(props.text)) {
        const size = context?.measureText(char).width || 18;
        const wraps = char === '\n' || used + size > available;
        if (wraps && line + 1 >= lines && part) {
            pages.push(part);
            part = '';
            line = 0;
            used = 0;
        } else if (wraps) {
            line++;
            used = 0;
        }
        part += char;
        if (char !== '\n') used += size;
    }
    if (part || !pages.length) pages.push(part);
    return pages;
});
watch(parts, () => {
    page.value = Math.min(page.value, parts.value.length - 1);
});
</script>
<template>
    <Drawer
        :swipe-direction="desktop ? 'right' : 'down'"
        @update:open="page = 0"
    >
        <DrawerTrigger as-child
            ><button
                type="button"
                class="text-reader-trigger"
                :aria-label="t('read') + (label ? ' · ' + label : '')"
            >
                ↗ <span>{{ t('read') }}</span>
            </button></DrawerTrigger
        >
        <DrawerContent class="game-drawer">
            <DrawerTitle>{{ label || t('read') }}</DrawerTitle>
            <DrawerDescription class="sr-only">{{
                t('read')
            }}</DrawerDescription>
            <p ref="reader" class="reader-text">{{ parts[page] }}</p>
            <PageControls v-model="page" :total="parts.length" />
            <DrawerClose as-child
                ><button type="button" class="game-action">
                    {{ t('close') }}
                </button></DrawerClose
            >
        </DrawerContent>
    </Drawer>
</template>
