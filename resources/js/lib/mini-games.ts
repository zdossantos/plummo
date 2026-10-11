import type { CupSwap } from '@/types/rooms';

/** Position swaps keep cup identity stable across polls, reconnects and pauses. */
export function cupPositions(swaps: CupSwap[], elapsed: number) {
    const order = [0, 1, 2];
    const positions = order.map((x) => ({ x, y: 0 }));
    for (const swap of swaps) {
        if (elapsed < swap.at) break;
        const [a, b] = swap.positions;
        const first = order[a]!;
        const second = order[b]!;
        const progress = Math.min(1, (elapsed - swap.at) / swap.duration);
        const ease = (1 - Math.cos(Math.PI * progress)) / 2;
        positions[first] = {
            x: a + (b - a) * ease,
            y: Math.sin(Math.PI * progress),
        };
        positions[second] = {
            x: b + (a - b) * ease,
            y: -Math.sin(Math.PI * progress),
        };
        if (progress < 1) break;
        [order[a], order[b]] = [second, first];
    }
    return positions;
}

export function sceneElapsed(
    phase: string,
    duration: number,
    snapshot: number,
    deadline: number,
    now: number,
) {
    if (phase === 'paused' || phase === 'resuming') return snapshot;
    if (phase !== 'observing') return duration;
    return Math.min(
        duration,
        Math.max(0, duration - Math.max(0, deadline - now)),
    );
}
