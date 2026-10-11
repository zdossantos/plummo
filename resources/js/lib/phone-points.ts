import { PlummoSnapshotBaseline } from './plummo-motion';
import type { GameState } from '@/types/rooms';

/** Celebrate server-awarded deltas without replaying a restored snapshot. */
export class PhonePointsTracker {
    private baseline = new PlummoSnapshotBaseline();
    private context = '';
    private previous = 0;

    observe(game: GameState | null, connected = true): number | null {
        const state = this.baseline.observe(game, connected);
        if (state === 'waiting' || !game) return null;
        const context = `${game.id}:${game.round.number}`;
        const points = Math.max(0, game.me?.points ?? 0);
        const gain = points - this.previous;
        const restored = state === 'baseline' || context !== this.context;
        this.context = context;
        this.previous = points;
        return !restored && gain > 0 ? gain : null;
    }
}
