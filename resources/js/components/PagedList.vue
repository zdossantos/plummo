<script setup lang="ts" generic="T">
import { computed, ref, watch } from 'vue';
import { useElementSize, useResizeObserver } from '@vueuse/core';
import PageControls from '@/components/PageControls.vue';
const props = withDefaults(
    defineProps<{ items: T[]; rowHeight?: number; columns?: number }>(),
    { rowHeight: 90, columns: 1 },
);
const root = ref<HTMLElement>();
const { height } = useElementSize(root);
const page = ref(0);
const itemsRoot = ref<HTMLElement>();
const measuredRow = ref(0);
useResizeObserver(itemsRoot, () => {
    measuredRow.value = Math.max(
        0,
        ...Array.from(itemsRoot.value?.children ?? []).map(
            (child) => child.getBoundingClientRect().height + 8,
        ),
    );
});
const count = computed(
    () =>
        Math.max(
            1,
            Math.floor(
                Math.max(0, height.value - 52) /
                    Math.max(props.rowHeight, measuredRow.value),
            ),
        ) * props.columns,
);
const total = computed(() =>
    Math.max(1, Math.ceil(props.items.length / count.value)),
);
const visible = computed(() =>
    props.items.slice(page.value * count.value, (page.value + 1) * count.value),
);
watch(total, (value) => {
    page.value = Math.min(page.value, value - 1);
});
</script>
<template>
    <div ref="root" class="paged-list">
        <div
            ref="itemsRoot"
            class="paged-items"
            :style="{
                gridTemplateColumns: `repeat(${columns}, minmax(0,1fr))`,
            }"
        >
            <slot
                v-for="(item, index) in visible"
                :key="page * count + index"
                :item="item"
                :index="page * count + index"
            />
        </div>
        <PageControls v-model="page" :total="total" />
    </div>
</template>
