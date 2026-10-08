<script setup lang="ts">
import { computed, ref, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { useElementSize, useMediaQuery } from '@vueuse/core';
import {
    Drawer,
    DrawerContent,
    DrawerTitle,
    DrawerDescription,
    DrawerTrigger,
    DrawerClose,
} from '@/components/ui/drawer';
import { BookOpen } from '@lucide/vue';
import PageControls from '@/components/PageControls.vue';
import { useTranslations } from '@/composables/useTranslations';
const props = defineProps<{ text: string; label?: string }>();
const { t } = useTranslations('interface');
const desktop = useMediaQuery('(min-width: 768px)');
const page = ref(0);
const open = ref(false);
const anchor = ref<HTMLElement>();
const truncated = ref(false);
let observer: ResizeObserver | undefined;
function measure() {
    const parent = anchor.value?.parentElement;
    const text =
        parent?.closest('.text-summary') ??
        parent?.querySelector('.text-summary');
    truncated.value =
        !!text &&
        (text.scrollHeight > text.clientHeight + 1 ||
            text.scrollWidth > text.clientWidth + 1);
}
onMounted(() => {
    observer = new ResizeObserver(measure);
    const parent = anchor.value?.parentElement;
    if (parent) observer.observe(parent);
    const text =
        parent?.closest('.text-summary') ??
        parent?.querySelector('.text-summary');
    if (text) observer.observe(text);
    measure();
    void document.fonts.ready.then(measure);
});
watch(
    () => props.text,
    async () => {
        await nextTick();
        measure();
    },
);
onUnmounted(() => observer?.disconnect());
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
    <span ref="anchor" hidden aria-hidden="true" />
    <Drawer
        v-model:open="open"
        :swipe-direction="desktop ? 'right' : 'down'"
        @update:open="page = 0"
    >
        <DrawerTrigger as-child
            ><button
                v-if="truncated"
                type="button"
                class="text-reader-trigger"
                :aria-label="t('read') + (label ? ' · ' + label : '')"
            >
                <BookOpen aria-hidden="true" /><span>{{
                    t('read_short')
                }}</span>
            </button></DrawerTrigger
        >
        <DrawerContent v-if="open" class="game-drawer">
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
