<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { useElementSize } from '@vueuse/core';
import PageControls from '@/components/PageControls.vue';
const model = defineModel<string>({ required: true });
const props = defineProps<{
    maxlength: number;
    id?: string;
    disabled?: boolean;
}>();
const emit = defineEmits<{ input: [event: Event] }>();
const page = ref(0);
const fieldElement = ref<HTMLTextAreaElement>();
const { width } = useElementSize(fieldElement);
function partition(value: string) {
    const result: string[] = [];
    const field = fieldElement.value;
    const context = document.createElement('canvas').getContext('2d');
    if (context && field) context.font = getComputedStyle(field).font;
    const budget = Math.max(40, (width.value - 28) * 1.25);
    let part = '';
    let length = 0;
    let lines = 0;
    for (const char of Array.from(value)) {
        const advance = context?.measureText(char).width || 12;
        if (length + advance > budget || (char === '\n' && lines >= 1)) {
            result.push(part);
            part = '';
            length = 0;
            lines = 0;
        }
        part += char;
        length += advance;
        if (char === '\n') lines++;
    }
    result.push(part);
    return result;
}
const parts = computed(() => partition(model.value));
watch(
    () => parts.value.length,
    (n) => {
        page.value = Math.min(page.value, n - 1);
    },
);
function input(event: Event) {
    const field = event.target as HTMLTextAreaElement;
    const otherLength = parts.value.reduce(
        (total, part, index) =>
            total + (index === page.value ? 0 : Array.from(part).length),
        0,
    );
    const value = Array.from(field.value)
        .slice(0, Math.max(0, props.maxlength - otherLength))
        .join('');
    field.value = value;
    const caret =
        parts.value.slice(0, page.value).join('').length + field.selectionStart;
    const next = [...parts.value];
    next[page.value] = value;
    const nextValue = next.join('');
    const nextParts = partition(nextValue);
    model.value = nextValue;
    let offset = 0;
    for (let index = 0; index < nextParts.length; index++) {
        if (
            caret <= offset + nextParts[index].length ||
            index === nextParts.length - 1
        ) {
            page.value = index;
            const local = Math.min(caret - offset, nextParts[index].length);
            void nextTick(() => field.setSelectionRange(local, local));
            break;
        }
        offset += nextParts[index].length;
    }
    emit('input', event);
}
</script>
<template>
    <div class="paged-field">
        <textarea
            :id="id"
            ref="fieldElement"
            :value="parts[page]"
            :disabled="disabled"
            rows="3"
            @input="input"
        />
        <div class="field-pages">
            <span>{{ Array.from(model).length }} / {{ maxlength }}</span
            ><PageControls v-model="page" :total="parts.length" />
        </div>
    </div>
</template>
