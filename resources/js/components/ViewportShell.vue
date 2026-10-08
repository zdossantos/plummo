<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import RoomHeader from '@/components/RoomHeader.vue';
const height = ref('100dvh');
const compact = ref(false);
function resize() {
    compact.value = (window.visualViewport?.height ?? window.innerHeight) < 420;
    height.value = `${window.visualViewport?.height ?? window.innerHeight}px`;
}
onMounted(() => {
    resize();
    window.visualViewport?.addEventListener('resize', resize);
    window.addEventListener('resize', resize);
});
onUnmounted(() => {
    window.visualViewport?.removeEventListener('resize', resize);
    window.removeEventListener('resize', resize);
});
</script>
<template>
    <main
        class="viewport-shell"
        :style="{ height }"
        :data-compact="compact || undefined"
    >
        <RoomHeader><slot name="header" /></RoomHeader>
        <div class="viewport-stage"><slot /></div>
        <footer v-if="$slots.footer" class="viewport-footer">
            <slot name="footer" />
        </footer>
    </main>
</template>
