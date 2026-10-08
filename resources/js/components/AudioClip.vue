<script setup lang="ts">
import { playAudio } from '@/lib/audio';
import { ref, watch, onUnmounted } from 'vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
const props = defineProps<{ src: string; playing: boolean }>();
const { t } = useTranslations('rooms');
const audio = ref<HTMLAudioElement>();
const blocked = ref(false);
const failed = ref(false);
let generation = 0;
async function play() {
    const element = audio.value;
    if (!element || !props.playing) return;
    const attempt = generation;
    try {
        await playAudio(element);
        if (attempt !== generation || !props.playing) element.pause();
        else {
            blocked.value = false;
            failed.value = false;
        }
    } catch {
        if (attempt === generation && props.playing) blocked.value = true;
    }
}
watch(
    () => [props.src, props.playing, audio.value],
    () => {
        generation++;
        if (props.playing) void play();
        else audio.value?.pause();
    },
    { flush: 'post' },
);
onUnmounted(() => {
    generation++;
    audio.value?.pause();
});
</script>
<template>
    <audio
        ref="audio"
        :src="src"
        loop
        preload="auto"
        @error="
            failed = true;
            blocked = true;
        "
    />
    <div v-if="blocked && playing" class="mt-5">
        <p v-if="failed" role="alert">{{ t('audio_error') }}</p>
        <Button @click="play">{{ t('play_sound') }}</Button>
    </div>
</template>
