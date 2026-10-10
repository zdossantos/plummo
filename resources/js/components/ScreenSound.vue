<script setup lang="ts">
import { computed, inject, onUnmounted, ref, watch } from 'vue';
import { Volume2, VolumeX } from '@lucide/vue';
import { playAudio } from '@/lib/audio';
import { Soundscape, soundSettings } from '@/lib/soundscape';
import { BonusReceiptTracker } from '@/lib/bonuses';
import { useTranslations } from '@/composables/useTranslations';
import type { BonusItem, GameState, RoomState } from '@/types/rooms';
const props = defineProps<{
    game: GameState | null;
    room: RoomState | null;
    enabled: boolean;
    volume: number;
    closed: boolean;
    serverNow: number;
    connected: boolean;
}>();
const emit = defineEmits<{
    'update:enabled': [value: boolean];
    'update:volume': [value: number];
}>();
const { t } = useTranslations('rooms');
const started = ref(false);
const settings = inject(soundSettings);
const media = settings?.media ?? ref<HTMLAudioElement>();
const blocked = settings?.blocked ?? ref(false);
let sound: Soundscape | undefined;
const launches = new BonusReceiptTracker<BonusItem & { at: number }>();
let idle: ReturnType<typeof setTimeout> | undefined;
const blind = computed(
    () =>
        props.game?.type === 'blind_test' &&
        ['answer', 'reveal'].includes(props.game.phase),
);
function updateVolume() {
    sound?.setVolume(
        props.enabled && !props.closed ? props.volume : 0,
        blind.value,
    );
}
async function toggle() {
    if (props.enabled && !blocked.value) {
        emit('update:enabled', false);
        return;
    }
    try {
        // Both playback APIs must be invoked inside the click, before any await.
        // Keep this media element for every excerpt: WebKit permission is per element.
        const element = media.value;
        if (!element) return;
        if (!blind.value) {
            element.src = '/audio/plummo-ambiance.mp3';
            element.loop = false;
        }
        element.volume = props.volume;
        const playback = playAudio(element);
        sound ??= new Soundscape();
        const ambiance = sound.start();
        await Promise.all([playback, ambiance]);
        if (!blind.value) element.pause();
        started.value = true;
        blocked.value = false;
        emit('update:enabled', true);
        window.dispatchEvent(new Event('plummo-audio-unlocked'));
    } catch {
        if (!blind.value) media.value?.pause();
        blocked.value = true;
    }
}
function scheduleIdle() {
    idle = setTimeout(
        () => {
            const players =
                props.room?.players.filter(
                    (player) => player.status === 'connected',
                ) ?? [];
            if (
                props.enabled &&
                !props.closed &&
                !blind.value &&
                players.length
            )
                sound?.chirp(
                    players[Math.floor(Math.random() * players.length)].id,
                );
            scheduleIdle();
        },
        18000 + Math.random() * 22000,
    );
}
scheduleIdle();
watch(
    () => [props.enabled, props.volume, props.closed, blind.value],
    updateVolume,
);
watch(
    () => props.game?.phase,
    (phase, previous) => {
        if (phase === 'reveal' && previous !== phase && props.enabled)
            sound?.chirp(props.game?.id);
    },
);
watch(
    () =>
        props.room?.players
            .map((player) => player.chat?.expiresAt ?? 0)
            .join(','),
    (_, previous) => {
        if (previous && props.enabled) sound?.chirp();
    },
);
watch(
    () => [props.game?.bonuses?.launches, props.connected] as const,
    ([events]) => {
        if (!props.game) return;
        const fresh = launches.observe(
            props.game.id,
            events ?? [],
            props.serverNow,
            props.connected,
        );
        if (props.enabled && !props.closed)
            for (const event of fresh) sound?.prank(event.kind);
    },
    { immediate: true },
);
onUnmounted(() => {
    clearTimeout(idle);
    media.value?.pause();
    sound?.close();
});
</script>
<template>
    <audio ref="media" src="/audio/plummo-ambiance.mp3" preload="auto" />
    <div v-if="!closed" class="sound-controls">
        <button
            type="button"
            :aria-label="
                t(blocked ? 'audio_retry' : enabled ? 'sound_off' : 'sound_on')
            "
            :aria-pressed="enabled"
            :title="enabled ? t('audio_ready_hint') : undefined"
            @click="toggle"
        >
            <Volume2 v-if="enabled" /><VolumeX v-else />
            <span v-if="blocked || !started">{{
                t(blocked ? 'audio_retry' : 'sound_on')
            }}</span>
        </button>
        <span
            v-if="started && enabled && !game"
            class="sr-only"
            role="status"
            >{{ t('audio_ready_hint') }}</span
        >
        <input
            v-if="started"
            type="range"
            min="0"
            max="1"
            step="0.05"
            :value="volume"
            :aria-label="t('sound_volume')"
            @input="
                emit(
                    'update:volume',
                    Number(($event.target as HTMLInputElement).value),
                )
            "
        />
    </div>
</template>
