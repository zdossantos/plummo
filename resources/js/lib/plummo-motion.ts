import { roundCelebration } from '@/lib/celebration';
import type { GameState } from '@/types/rooms';

export type PlummoMotion = 'idle' | 'answer' | 'points' | 'resume' | 'podium';
export type PlummoEvent = { motion: PlummoMotion; key: string };
export const gestureDuration = 1600;

/** Consume semantic server events, including the baseline on reconnect. */
export class PlummoEvents {
    private initialized = false;
    private context = '';
    private seen = new Set<string>();
    observe(game: GameState | null, playerId: number): PlummoEvent | null {
        if (!game) {
            const hadGame = this.context !== '';
            this.context = '';
            this.initialized = false;
            this.seen.clear();
            return hadGame ? { motion: 'idle', key: '' } : null;
        }
        const context = game ? `${game.id}:${game.round.number}` : '';
        const changed = this.initialized && context !== this.context;
        if (context !== this.context) this.seen.clear();
        this.context = context;
        const events: PlummoEvent[] = [];
        if (game) {
            const celebration = roundCelebration(game, '');
            if (celebration?.gains[playerId])
                events.push({ motion: 'points', key: `${context}:points` });
            if (game.phase === 'resuming')
                events.push({
                    motion: 'resume',
                    key: `${context}:resume:${game.deadline}`,
                });
            if (game.me) {
                const confirmed =
                    game.type === 'phrase'
                        ? game.me.voted
                            ? 'vote'
                            : game.me.submitted
                              ? 'submission'
                              : null
                        : game.type === 'drawing'
                          ? game.me.found
                              ? 'found'
                              : null
                          : game.me.answered
                            ? 'answer'
                            : null;
                if (confirmed)
                    events.push({
                        motion: 'answer',
                        key: `${context}:${confirmed}`,
                    });
            }
        }
        const next = events.find((event) => !this.seen.has(event.key));
        for (const event of events) this.seen.add(event.key);
        const baseline = !this.initialized;
        this.initialized = true;
        if (baseline) return null;
        if (next) return next;
        return changed ? { motion: 'idle', key: context } : null;
    }
}
