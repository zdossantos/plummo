import type { Stroke } from '@/types/rooms';
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

export function eatenQuestion(question: string, active: boolean): string {
    if (!active) return question;
    const words = question.match(/[\p{L}\p{N}]+/gu) ?? [];
    const candidate =
        words.slice(1).find((word) => word.length >= 6) ??
        words.find((word) => word.length >= 4);
    return candidate ? question.replace(candidate, '▰▰▰▰') : question;
}

export function bonusStrokePoints(
    points: [number, number][],
    active: boolean,
    tool: string,
): [number, number][] {
    if (!active || tool !== 'brush') return points;
    return points.map(([x, y]) => [
        Math.min(1, Math.max(0, x + 0.008 * Math.sin(y * 90))),
        Math.min(1, Math.max(0, y + 0.012 * Math.sin(x * 70))),
    ]);
}

export function bonusPlaybackRate(effects: BonusEffect[], now: number): number {
    return effects.some(
        (e) =>
            e.kind === 'speed' && (e.expiresAt === null || e.expiresAt > now),
    )
        ? 1.25
        : 1;
}

/** Resolve unsent preview geometry once, before raster fill. */
export function bonusRenderedStroke(stroke: Stroke): Stroke {
    const { clumsy, bonusWidth, ...canonical } = stroke;
    return {
        ...canonical,
        width: bonusWidth ?? stroke.width,
        points: bonusStrokePoints(
            stroke.points,
            clumsy ?? false,
            stroke.tool ?? 'brush',
        ),
    };
}
