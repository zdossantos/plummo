<script setup lang="ts">
import { playAudio, fadeAudio } from '@/lib/audio';
import { ref, watch, onUnmounted, onMounted, inject } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import { soundSettings } from '@/lib/soundscape';
const props = defineProps<{
    src: string;
    playing: boolean;
    revealing?: boolean;
}>();
const sound = inject(soundSettings, undefined);
let cancelFade: (() => void) | undefined;
let release: ReturnType<typeof setTimeout> | undefined;
let released = false;
function scheduleRelease() {
    clearTimeout(release);
    released = false;
    if (props.playing && props.revealing)
        release = setTimeout(() => {
            released = true;
            transition();
        }, 1000);
}
function transition(stop = false) {
    const element = audio.value;
    if (!element) return;
    cancelFade?.();
    const target =
        stop || released || (sound && !sound.enabled.value)
            ? 0
            : (sound?.volume.value ?? 1);
    cancelFade = fadeAudio(
        element,
        target,
        stop ? 250 : 1000,
        stop ? () => element.pause() : undefined,
    );
}
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
            transition();
        }
    } catch {
        if (attempt === generation && props.playing) blocked.value = true;
    }
}
watch(
    () => [props.src, props.playing, audio.value],
    () => {
        generation++;
        scheduleRelease();
        cancelFade?.();
        if (props.playing) {
            if (audio.value) audio.value.volume = 0;
            void play();
        } else {
            audio.value?.pause();
            if (audio.value) audio.value.volume = 0;
        }
    },
    { flush: 'post' },
);
watch(
    () => props.revealing,
    (revealing) => {
        scheduleRelease();
        if (!revealing && props.playing) transition();
    },
);
watch(
    () => [sound?.enabled.value, sound?.volume.value],
    () => transition(!props.playing),
);
onMounted(() => window.addEventListener('plummo-audio-unlocked', play));
onUnmounted(() => {
    clearTimeout(release);
    cancelFade?.();
    window.removeEventListener('plummo-audio-unlocked', play);
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
        <p v-else role="status">{{ t('audio_autoplay_blocked') }}</p>
    </div>
</template>
