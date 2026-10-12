<script setup lang="ts">
import { computed, ref, watch, onUnmounted } from 'vue';
import { useResizeObserver } from '@vueuse/core';
const surface = ref<HTMLElement>();
const surfaceWidth = ref(0);
const surfaceHeight = ref(0);
let resizeFrame = 0;
useResizeObserver(surface, ([entry]) => {
    if (!entry) return;
    cancelAnimationFrame(resizeFrame);
    // Update the SVG after observation to avoid a nested layout notification.
    resizeFrame = requestAnimationFrame(() => {
        surfaceWidth.value = entry.contentRect.width;
        surfaceHeight.value = entry.contentRect.height;
    });
});
const canvasWidth = computed(() =>
    Math.min(
        surfaceWidth.value,
        (surfaceHeight.value * 1000) / (props.canvasHeight ?? 600),
    ),
);
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import type { Stroke } from '@/types/rooms';
import { drawingFill } from '@/lib/drawing-raster';
import { bonusStrokePoints } from '@/lib/bonuses';
import { fillPath } from '@/lib/drawing-fill';
const props = defineProps<{
    strokes: Stroke[];
    canvasHeight?: number;
    editable?: boolean;
    busy?: boolean;
    color?: string;
    width?: number;
    giant?: boolean;
    clumsy?: boolean;
    tool?: 'brush' | 'fill' | 'erase';
    send?: (values: Record<string, unknown>) => Promise<boolean>;
}>();
const emit = defineEmits<{ pending: [value: boolean] }>();
const { t } = useTranslations('rooms');
const copy = () => props.strokes.map((s) => ({ ...s, points: [...s.points] }));
const local = ref<Stroke[]>(copy());
const failed = ref(false);
const limited = ref(false);
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
        ...(stroke.tool ? { tool: stroke.tool } : {}),
        ...(stroke.tool === 'fill' ? { spans: stroke.spans } : {}),
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
// Split a held stroke at a paint change so the prank starts and ends on time.
// Existing segments keep their wire colour for acknowledged chunk retries.
watch(
    () => [props.color, props.giant, props.clumsy] as const,
    ([color]) => {
        if (
            active === null ||
            !current ||
            current.tool === 'erase' ||
            (current.color === color &&
                !!current.clumsy === !!props.clumsy &&
                current.bonusWidth ===
                    (props.giant
                        ? Math.min(36, current.width * 3)
                        : current.width))
        )
            return;
        if (local.value.length >= 100) return;
        const last = current.points.at(-1);
        if (!last) return;
        local.value.push({
            id: local.value.length + 1,
            color: color ?? '#35236b',
            width: current.width,
            clumsy: props.clumsy,
            bonusWidth: props.giant
                ? Math.min(36, current.width * 3)
                : current.width,
            tool: current.tool,
            points: [[...last]],
        });
        current = local.value.at(-1);
        schedule();
    },
);
watch(
    () => props.tool,
    () => {
        active = null;
        current = undefined;
        emit('pending', pending());
        if (pending()) schedule();
    },
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
    const tool = props.tool ?? 'brush';
    const seed = point(event);
    const color = tool === 'erase' ? '#ffffff' : (props.color ?? '#35236b');
    const spans =
        tool === 'fill'
            ? drawingFill(local.value, seed, color, props.canvasHeight ?? 600)
            : [];
    if (tool === 'fill' && !spans.length) return;
    if (
        spans.length > 10000 ||
        local.value.reduce((sum, s) => sum + (s.spans?.length ?? 0), 0) +
            spans.length >
            20000
    ) {
        limited.value = true;
        return;
    }
    limited.value = false;
    active = tool === 'fill' ? null : event.pointerId;
    current = {
        id: local.value.length + 1,
        tool,
        ...(tool === 'fill' ? { spans } : {}),
        color,
        width: (props.width ?? 4) * (tool === 'erase' ? 3 : 1),
        clumsy: tool === 'brush' && props.clumsy,
        bonusWidth:
            tool === 'brush' && props.giant
                ? Math.min(36, (props.width ?? 4) * 3)
                : undefined,
        points: [seed],
    };
    local.value.push(current);
    current = tool === 'fill' ? undefined : local.value.at(-1);
    // Synthetic pointer events in browser tests have no active native pointer.
    if (event.isTrusted && active !== null)
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
    cancelAnimationFrame(resizeFrame);
    stopped = true;
    clearTimeout(timer);
});
</script>
<template>
    <div class="drawing-board">
        <div ref="surface" class="drawing-space">
            <svg
                :style="{
                    width: canvasWidth + 'px',
                    height: (canvasWidth * (canvasHeight ?? 600)) / 1000 + 'px',
                }"
                data-testid="drawing-board"
                :viewBox="`0 0 1000 ${canvasHeight ?? 600}`"
                :aria-label="t('drawing_canvas')"
                class="drawing-surface rounded-2xl border-2 border-primary/20 bg-white"
                :class="editable ? 'touch-none cursor-crosshair' : ''"
                @pointerdown.prevent="down"
                @pointermove="move"
                @pointerup="up"
                @pointercancel="up"
            >
                <g
                    v-for="stroke in local"
                    :key="stroke.id"
                    :data-tool="stroke.tool ?? 'brush'"
                >
                    <path
                        v-if="stroke.tool === 'fill'"
                        :d="fillPath(stroke.spans ?? [])"
                        :fill="stroke.color"
                    />
                    <template v-else>
                        <polyline
                            :points="
                                bonusStrokePoints(
                                    stroke.points,
                                    !!stroke.clumsy,
                                    stroke.tool ?? 'brush',
                                )
                                    .map(
                                        ([x, y]) =>
                                            `${x * 1000},${y * (canvasHeight ?? 600)}`,
                                    )
                                    .join(' ')
                            "
                            :stroke="stroke.color"
                            :stroke-width="stroke.bonusWidth ?? stroke.width"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            fill="none"
                        />
                        <circle
                            v-if="stroke.points.length === 1"
                            :cx="
                                bonusStrokePoints(
                                    stroke.points,
                                    !!stroke.clumsy,
                                    stroke.tool ?? 'brush',
                                )[0][0] * 1000
                            "
                            :cy="
                                bonusStrokePoints(
                                    stroke.points,
                                    !!stroke.clumsy,
                                    stroke.tool ?? 'brush',
                                )[0][1] * (canvasHeight ?? 600)
                            "
                            :r="(stroke.bonusWidth ?? stroke.width) / 2"
                            :fill="stroke.color"
                        />
                    </template>
                </g>
            </svg>
        </div>
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
                (limited ||
                    local.length >= 100 ||
                    local.reduce((sum, s) => sum + s.points.length, 0) >= 10000)
            "
            class="mt-3"
            role="status"
        >
            {{ t('drawing_limit') }}
        </p>
    </div>
</template>
