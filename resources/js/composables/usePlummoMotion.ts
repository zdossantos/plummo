import { onMounted, onUnmounted, ref, watch } from 'vue';
import { gestureDuration, PlummoEvents } from '@/lib/plummo-motion';
import type { PlummoEvent, PlummoMotion } from '@/lib/plummo-motion';
import type { GameState } from '@/types/rooms';

export function useGamePlummoMotion(
    game: () => GameState | null,
    player: () => number,
    connected: () => boolean,
) {
    const events = new PlummoEvents();
    const event = ref<PlummoEvent>({ motion: 'idle', key: '' });
    watch(
        [game, player, connected],
        ([snapshot, id, online]) => {
            const next = events.observe(snapshot, id, online);
            if (next) event.value = next;
        },
        { immediate: true },
    );
    return event;
}

export function usePlummoMotion(
    motion: () => PlummoMotion | undefined,
    key: () => string | undefined,
) {
    const active = ref<PlummoMotion | undefined>();
    const foreground = ref(false);
    const reduced = ref(true);
    const seen = new Set<string>();
    let timer: ReturnType<typeof setTimeout> | undefined;
    let media: MediaQueryList | undefined;
    const reset = () => {
        clearTimeout(timer);
        foreground.value = false;
        active.value = !reduced.value && motion() ? 'idle' : undefined;
    };
    const play = () => {
        const next = motion();
        const identity = `${next}:${key() ?? ''}`;
        if (!next || next === 'idle') {
            reset();
            return;
        }
        if (seen.has(identity)) return;
        seen.add(identity);
        reset();
        if (reduced.value) return;
        active.value = next;
        foreground.value = true;
        timer = setTimeout(reset, gestureDuration);
    };
    const preference = () => {
        reduced.value = media?.matches ?? true;
        reset();
    };
    onMounted(() => {
        media = window.matchMedia('(prefers-reduced-motion: reduce)');
        reduced.value = media.matches;
        media.addEventListener('change', preference);
        play();
    });
    watch([motion, key], play);
    onUnmounted(() => {
        clearTimeout(timer);
        media?.removeEventListener('change', preference);
    });
    return { active, foreground };
}
