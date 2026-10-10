<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';
import { Volume2, VolumeX } from '@lucide/vue';
import { Soundscape } from '@/lib/soundscape';
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
    try {
        sound ??= new Soundscape();
        await sound.start();
        started.value = true;
        emit('update:enabled', !props.enabled);
        window.dispatchEvent(new Event('plummo-audio-unlocked'));
    } catch {
        started.value = false;
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
    sound?.close();
});
</script>
<template>
    <div v-if="!closed" class="sound-controls">
        <button
            type="button"
            :aria-label="t(enabled ? 'sound_off' : 'sound_on')"
            :aria-pressed="enabled"
            @click="toggle"
        >
            <Volume2 v-if="enabled" /><VolumeX v-else />
            <span v-if="!started">{{ t('sound_on') }}</span>
        </button>
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
