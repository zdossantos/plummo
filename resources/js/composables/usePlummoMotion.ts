import { onMounted, onUnmounted, ref, watch } from 'vue';
import { gestureDuration, PlummoEvents } from '@/lib/plummo-motion';
import type { PlummoEvent, PlummoMotion } from '@/lib/plummo-motion';
import type { GameState } from '@/types/rooms';

// One shared clock chooses a waiting character instead of synchronizing the cast.
const waitingPlummos = new Map<
    symbol,
    { ready: () => boolean; play: () => void }
>();
let ambientTimer: ReturnType<typeof setTimeout> | undefined;
let previousPlummo: symbol | undefined;
function scheduleAmbient() {
    clearTimeout(ambientTimer);
    if (!waitingPlummos.size || document.hidden) return;
    ambientTimer = setTimeout(
        () => {
            if (!document.hidden) {
                const ready = [...waitingPlummos].filter(([, item]) =>
                    item.ready(),
                );
                const others = ready.filter(([id]) => id !== previousPlummo);
                const choices = others.length ? others : ready;
                const chosen =
                    choices[Math.floor(Math.random() * choices.length)];
                if (chosen) {
                    previousPlummo = chosen[0];
                    chosen[1].play();
                }
            }
            scheduleAmbient();
        },
        5000 + Math.random() * 6000,
    );
}

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
    const identity = Symbol('plummo');
    let previousGesture = '';
    const ambientGestures = ['wiggle', 'stretch', 'hello', 'boing'] as const;
    const reduced = ref(true);
    const seen = new Set<string>();
    let timer: ReturnType<typeof setTimeout> | undefined;
    let media: MediaQueryList | undefined;
    const reset = () => {
        clearTimeout(timer);
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
        timer = setTimeout(reset, gestureDuration);
    };
    const spontaneous = () => {
        const choices = ambientGestures.filter(
            (gesture) => gesture !== previousGesture,
        );
        const next = choices[Math.floor(Math.random() * choices.length)]!;
        previousGesture = next;
        active.value = next;
        timer = setTimeout(reset, gestureDuration);
    };
    const visibility = () => {
        scheduleAmbient();
        if (
            document.hidden &&
            ambientGestures.some((gesture) => gesture === active.value)
        )
            reset();
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
        waitingPlummos.set(identity, {
            ready: () => !reduced.value && active.value === 'idle',
            play: spontaneous,
        });
        if (waitingPlummos.size === 1) scheduleAmbient();
        document.addEventListener('visibilitychange', visibility);
    });
    watch([motion, key], play);
    onUnmounted(() => {
        clearTimeout(timer);
        media?.removeEventListener('change', preference);
        document.removeEventListener('visibilitychange', visibility);
        waitingPlummos.delete(identity);
        if (!waitingPlummos.size) {
            clearTimeout(ambientTimer);
            previousPlummo = undefined;
        }
    });
    return { active };
}
