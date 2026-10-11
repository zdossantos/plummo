import { BonusReceiptTracker } from './bonuses';
import { PlummoSnapshotBaseline } from './plummo-motion';
import type { BonusItem, GameState } from '@/types/rooms';

export type BonusLaunchEvent = BonusItem & { at: number; actorId: number };

export class BonusLaunchTracker {
    private baseline = new PlummoSnapshotBaseline();
    private receipts = new BonusReceiptTracker<BonusLaunchEvent>();
    private context: number | undefined;

    observe(
        game: GameState | null,
        now: number,
        connected: boolean,
    ): BonusLaunchEvent[] {
        const state = this.baseline.observe(game, connected);
        if (state === 'waiting' || !game) return [];
        const restored = state === 'baseline' || this.context !== game.id;
        this.context = game.id;
        const fresh = this.receipts.observe(
            game.id,
            game.bonuses?.launches ?? [],
            now,
            true,
        );
        return restored ? [] : fresh;
    }
}
