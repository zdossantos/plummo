<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import RoomHeader from '@/components/RoomHeader.vue';
defineProps<{ displayOnly?: boolean }>();
const height = ref('100dvh');
const top = ref('0px');
const compact = ref(false);
function resize() {
    compact.value = (window.visualViewport?.height ?? window.innerHeight) < 420;
    height.value = `${window.visualViewport?.height ?? window.innerHeight}px`;
    top.value = `${window.visualViewport?.offsetTop ?? 0}px`;
}
onMounted(() => {
    resize();
    window.visualViewport?.addEventListener('resize', resize);
    window.visualViewport?.addEventListener('scroll', resize);
    window.addEventListener('resize', resize);
});
onUnmounted(() => {
    window.visualViewport?.removeEventListener('resize', resize);
    window.visualViewport?.removeEventListener('scroll', resize);
    window.removeEventListener('resize', resize);
});
</script>
<template>
    <main
        class="viewport-shell"
        :style="{ height, top }"
        :data-compact="compact || undefined"
    >
        <svg
            v-if="displayOnly"
            class="display-decor"
            viewBox="0 0 1440 900"
            preserveAspectRatio="xMidYMid slice"
            aria-hidden="true"
        >
            <g fill="none" stroke="currentColor" stroke-width="3">
                <path
                    d="M-110 200a240 240 0 0 1 390-230M-90 200a190 190 0 0 1 320-200"
                />
                <path d="M1270 80h65v65h-65zM1302 60v105M1250 112h105" />
                <path
                    d="m90 610 18-18 18 18-18 18zM1360 460l18-18 18 18-18 18z"
                />
                <path
                    d="M1140 940a310 310 0 0 1 440-270M1190 950a260 260 0 0 1 360-220"
                />
            </g>
            <g fill="currentColor">
                <circle cx="330" cy="80" r="5" />
                <circle cx="1200" cy="240" r="4" />
                <circle cx="70" cy="410" r="4" />
                <circle cx="230" cy="780" r="6" />
                <circle cx="1030" cy="810" r="4" />
            </g>
        </svg>
        <RoomHeader :display-only="displayOnly"
            ><slot name="header"
        /></RoomHeader>
        <div class="viewport-stage"><slot /></div>
        <footer v-if="$slots.footer" class="viewport-footer">
            <slot name="footer" />
        </footer>
    </main>
</template>
