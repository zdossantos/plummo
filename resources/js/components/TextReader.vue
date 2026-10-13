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
defineOptions({ inheritAttrs: false });
const props = defineProps<{
    text: string;
    label?: string;
    triggerLabel?: string;
    force?: boolean;
    upside?: boolean;
}>();
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
        // Remove the reader's reserved space before measuring new content.
        truncated.value = false;
        await nextTick();
        measure();
    },
);
onUnmounted(() => observer?.disconnect());
const reader = ref<HTMLElement>();
const { width, height } = useElementSize(reader);
const parts = computed(() => {
    const element = reader.value;
    if (!element || !width.value || !height.value) return [props.text];
    // Measure browser word wrapping with the reader's actual typography.
    const probe = element.cloneNode(false) as HTMLElement;
    Object.assign(probe.style, {
        position: 'fixed',
        visibility: 'hidden',
        pointerEvents: 'none',
        display: 'block',
        width: `${width.value}px`,
        height: 'auto',
        minHeight: '0',
        flex: 'none',
        inset: '0 auto auto 0',
    });
    element.parentElement?.append(probe);
    const characters = Array.from(props.text);
    const pages: string[] = [];
    let offset = 0;
    try {
        while (offset < characters.length) {
            let low = 1;
            let high = characters.length - offset;
            let fits = 1;
            while (low <= high) {
                const length = Math.floor((low + high) / 2);
                probe.textContent = characters
                    .slice(offset, offset + length)
                    .join('');
                if (probe.getBoundingClientRect().height <= height.value) {
                    fits = length;
                    low = length + 1;
                } else {
                    high = length - 1;
                }
            }
            if (offset + fits < characters.length) {
                const boundary = characters
                    .slice(offset, offset + fits)
                    .findLastIndex((char) => /\s/.test(char));
                if (boundary > 0) fits = boundary + 1;
            }
            pages.push(characters.slice(offset, offset + fits).join(''));
            offset += fits;
        }
    } finally {
        probe.remove();
    }
    if (!pages.length) pages.push('');
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
                v-if="truncated || force"
                v-bind="$attrs"
                type="button"
                class="text-reader-trigger"
                :aria-label="
                    (triggerLabel || t('read')) + (label ? ' · ' + label : '')
                "
            >
                <BookOpen aria-hidden="true" /><span>{{
                    triggerLabel || t('read_short')
                }}</span>
            </button></DrawerTrigger
        >
        <DrawerContent v-if="open" class="game-drawer">
            <DrawerTitle>{{ label || t('read') }}</DrawerTitle>
            <DrawerDescription class="sr-only">{{
                t('read')
            }}</DrawerDescription>
            <p
                ref="reader"
                class="reader-text"
                :class="{ 'answer-upside': upside }"
            >
                {{ parts[page] }}
            </p>
            <PageControls v-model="page" :total="parts.length" />
            <DrawerClose as-child
                ><button type="button" class="game-action">
                    {{ t('close') }}
                </button></DrawerClose
            >
        </DrawerContent>
    </Drawer>
</template>
