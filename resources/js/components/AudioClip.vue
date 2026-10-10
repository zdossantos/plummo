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
const audio = sound?.media ?? ref<HTMLAudioElement>();
const blocked = sound?.blocked ?? ref(false);
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
        if (audio.value && audio.value.getAttribute('src') !== props.src)
            audio.value.src = props.src;
        if (props.playing) {
            if (audio.value) {
                audio.value.loop = true;
                audio.value.volume = 0;
            }
            void play();
        } else {
            audio.value?.pause();
            if (audio.value) audio.value.volume = 0;
        }
    },
    { flush: 'post', immediate: true },
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
function mediaError() {
    failed.value = true;
    blocked.value = true;
}
onMounted(() => {
    audio.value?.addEventListener('error', mediaError);
    window.addEventListener('plummo-audio-unlocked', play);
});
onUnmounted(() => {
    clearTimeout(release);
    audio.value?.removeEventListener('error', mediaError);
    cancelFade?.();
    window.removeEventListener('plummo-audio-unlocked', play);
    generation++;
    audio.value?.pause();
});
</script>
<template>
    <audio
        v-if="!sound"
        ref="audio"
        :src="src"
        loop
        preload="auto"
        @error="mediaError"
    />
    <div v-if="blocked && playing" class="mt-5">
        <p v-if="failed" role="alert">{{ t('audio_error') }}</p>
        <p v-else role="status">{{ t('audio_autoplay_blocked') }}</p>
    </div>
</template>
