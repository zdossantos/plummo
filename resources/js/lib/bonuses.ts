import type { BonusEffect, BonusReceipt } from '@/types/rooms';

export function activeBonusEffects(
    effects: BonusEffect[],
    now: number,
    phase: string,
) {
    if (!['answer', 'drawing', 'writing'].includes(phase)) return [];
    return effects.filter(
        (effect) => effect.expiresAt === null || effect.expiresAt > now,
    );
}

export function bonusChoices(
    choices: string[],
    effects: BonusEffect[],
    now: number,
) {
    const items = choices.map((choice, index) => ({ choice, index }));
    const dice = effects.find(
        (effect) =>
            effect.kind === 'dice' &&
            effect.expiresAt !== null &&
            effect.expiresAt > now,
    );
    if (!dice || items.length < 2) return items;
    // A deterministic rotation changes every two seconds, without moving answer IDs.
    const step = Math.min(
        2,
        Math.max(0, Math.floor((now - dice.startedAt) / 2)),
    );
    const offset = 1 + (step % (items.length - 1));
    return [...items.slice(offset), ...items.slice(0, offset)];
}

export class BonusReceiptTracker<
    T extends { id: string; at: number } = BonusReceipt,
> {
    private gameId: number | null = null;
    private seen = new Set<string>();
    private reconnecting = false;

    observe(
        gameId: number,
        receipts: T[],
        now: number,
        connected: boolean,
    ): T[] {
        if (this.gameId !== gameId) {
            this.gameId = gameId;
            this.seen.clear();
        }
        if (!connected) {
            this.reconnecting = true;
            return [];
        }
        const fresh = receipts.filter(
            (receipt) =>
                !this.seen.has(receipt.id) &&
                now - receipt.at >= -1 &&
                now - receipt.at < 3,
        );
        for (const receipt of receipts) this.seen.add(receipt.id);
        if (this.reconnecting) {
            this.reconnecting = false;
            return [];
        }
        return fresh;
    }
}
