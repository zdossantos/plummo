<script setup lang="ts">
import { ref, watch, onUnmounted } from 'vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { Stroke } from '@/types/rooms';
const props = defineProps<{
    strokes: Stroke[];
    editable?: boolean;
    busy?: boolean;
    color?: string;
    width?: number;
    send?: (values: Record<string, unknown>) => Promise<boolean>;
}>();
const emit = defineEmits<{ pending: [value: boolean] }>();
const { t } = useTranslations('rooms');
const copy = () => props.strokes.map((s) => ({ ...s, points: [...s.points] }));
const local = ref<Stroke[]>(copy());
const failed = ref(false);
let active: number | null = null;
let current: Stroke | undefined;
let acknowledged = new Map<number, number>(
    props.strokes.map((s) => [s.id, s.points.length]),
);
let sending = false;
let stopped = false;
let timer: ReturnType<typeof setTimeout> | undefined;
const pending = () =>
    local.value.some((s) => (acknowledged.get(s.id) ?? 0) < s.points.length);
function schedule() {
    if (timer !== undefined) return;
    timer = setTimeout(() => {
        timer = undefined;
        void flush();
    }, 250);
}
async function flush() {
    if (stopped || sending || failed.value || !props.editable) return;
    if (props.busy) {
        schedule();
        return;
    }
    const stroke = local.value.find(
        (s) => (acknowledged.get(s.id) ?? 0) < s.points.length,
    );
    if (!stroke || !props.send) {
        emit('pending', active !== null);
        return;
    }
    const offset = acknowledged.get(stroke.id) ?? 0;
    const points = stroke.points.slice(offset, offset + 200);
    sending = true;
    const ok = await props.send({
        id: stroke.id,
        offset,
        points,
        color: stroke.color,
        width: stroke.width,
    });
    sending = false;
    if (stopped) return;
    if (ok) acknowledged.set(stroke.id, offset + points.length);
    else failed.value = true;
    emit('pending', active !== null || pending());
    if (ok && pending()) schedule();
}
watch(
    () => props.strokes,
    () => {
        if (active === null && !sending && !pending()) {
            local.value = copy();
            acknowledged = new Map(
                props.strokes.map((s) => [s.id, s.points.length]),
            );
        }
    },
    { deep: true },
);
watch(
    () => props.editable,
    (editable) => {
        if (!editable) {
            active = null;
            current = undefined;
            clearTimeout(timer);
            timer = undefined;
        } else if (pending()) schedule();
    },
);
function point(event: PointerEvent): [number, number] {
    const rect = (event.currentTarget as SVGSVGElement).getBoundingClientRect();
    return [
        Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width)),
        Math.min(1, Math.max(0, (event.clientY - rect.top) / rect.height)),
    ];
}
function down(event: PointerEvent) {
    if (
        !props.editable ||
        active !== null ||
        failed.value ||
        local.value.length >= 100 ||
        local.value.reduce((sum, s) => sum + s.points.length, 0) >= 10000
    )
        return;
    active = event.pointerId;
    current = {
        id: local.value.length + 1,
        color: props.color ?? '#35236b',
        width: props.width ?? 4,
        points: [point(event)],
    };
    local.value.push(current);
    current = local.value.at(-1);
    // Synthetic pointer events in browser tests have no active native pointer.
    if (event.isTrusted)
        (event.currentTarget as SVGSVGElement).setPointerCapture(
            event.pointerId,
        );
    emit('pending', true);
    schedule();
}
function move(event: PointerEvent) {
    if (active !== event.pointerId || !current || !props.editable) return;
    if (local.value.reduce((sum, s) => sum + s.points.length, 0) >= 10000)
        return;
    const next = point(event);
    const last = current.points.at(-1)!;
    if (Math.hypot(next[0] - last[0], next[1] - last[1]) >= 0.002) {
        current.points.push(next);
        schedule();
    }
}
function up(event: PointerEvent) {
    if (active !== event.pointerId) return;
    move(event);
    active = null;
    current = undefined;
    schedule();
}
onUnmounted(() => {
    stopped = true;
    clearTimeout(timer);
});
</script>
<template>
    <div>
        <svg
            data-testid="drawing-board"
            viewBox="0 0 1000 600"
            :aria-label="t('drawing_canvas')"
            class="w-full rounded-2xl border-2 border-primary/20 bg-white"
            :class="editable ? 'touch-none cursor-crosshair' : ''"
            @pointerdown.prevent="down"
            @pointermove="move"
            @pointerup="up"
            @pointercancel="up"
        >
            <polyline
                v-for="stroke in local"
                :key="stroke.id"
                :points="
                    stroke.points
                        .map(([x, y]) => `${x * 1000},${y * 600}`)
                        .join(' ')
                "
                :stroke="stroke.color"
                :stroke-width="stroke.width"
                stroke-linecap="round"
                stroke-linejoin="round"
                fill="none"
            />
            <circle
                v-for="stroke in local.filter((s) => s.points.length === 1)"
                :key="`dot-${stroke.id}`"
                :cx="stroke.points[0][0] * 1000"
                :cy="stroke.points[0][1] * 600"
                :r="stroke.width / 2"
                :fill="stroke.color"
            />
        </svg>
        <p v-if="failed" class="mt-3" role="alert">
            {{ t('drawing_sync_error') }}
            <Button
                variant="outline"
                :disabled="busy || !editable"
                @click="
                    failed = false;
                    schedule();
                "
                >{{ t('drawing_retry') }}</Button
            >
        </p>
        <p
            v-if="
                editable &&
                (local.length >= 100 ||
                    local.reduce((sum, s) => sum + s.points.length, 0) >= 10000)
            "
            class="mt-3"
            role="status"
        >
            {{ t('drawing_limit') }}
        </p>
    </div>
</template>
