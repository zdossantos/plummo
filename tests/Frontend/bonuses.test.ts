import { expect, test } from 'bun:test';
import { bonusChoices, activeBonusEffects, BonusReceiptTracker } from '../../resources/js/lib/bonuses';
import type { BonusEffect, BonusReceipt } from '../../resources/js/types/rooms';

const dice: BonusEffect = { id: 'dice-1', kind: 'dice', actorId: 1, startedAt: 100, expiresAt: 106 };
test('shuffles three times while keeping answer identity and restores order at expiry', () => {
    const choices = ['one', 'two', 'three', 'four'];
    const first = bonusChoices(choices, [dice], 100);
    const second = bonusChoices(choices, [dice], 102);
    expect(first.map((item) => item.index)).not.toEqual([0, 1, 2, 3]);
    expect(second).not.toEqual(first);
    for (const item of second) expect(item.choice).toBe(choices[item.index]!);
    expect(bonusChoices(choices, [dice], 106).map((item) => item.index)).toEqual([0, 1, 2, 3]);
});
test('filters effects using server time and freezes them outside playable phases', () => {
    expect(activeBonusEffects([dice], 105, 'answer')).toHaveLength(1);
    expect(activeBonusEffects([dice], 106, 'answer')).toEqual([]);
    expect(activeBonusEffects([dice], 101, 'paused')).toEqual([]);
    expect(activeBonusEffects([dice], 101, 'reveal')).toEqual([]);
});
test('receipts animate only once and never replay after reconnect or late arrival', () => {
    const tracker = new BonusReceiptTracker();
    const receipt: BonusReceipt = { id: 'object-1', kind: 'bolt', recipientId: 2, at: 100 };
    expect(tracker.observe(1, [receipt], 101, true)).toEqual([receipt]);
    expect(tracker.observe(1, [{ ...receipt }], 102, true)).toEqual([]);
    tracker.observe(1, [], 103, false);
    expect(tracker.observe(1, [receipt], 104, true)).toEqual([]);
    expect(tracker.observe(2, [{ ...receipt, id: 'old', at: 90 }], 110, true)).toEqual([]);
});

test('each prank has its own short, restrained sound signature', async () => {
    const { bonusSoundNotes } = await import('../../resources/js/lib/soundscape');
    const kinds = ['bolt', 'dice', 'squatter', 'artist', 'accent', 'sneeze', 'stamp', 'paint'] as const;
    const signatures = kinds.map(kind => bonusSoundNotes(kind));
    expect(new Set(signatures.map(notes => JSON.stringify(notes))).size).toBe(8);
    for (const notes of signatures) {
        expect(notes.length).toBeGreaterThan(0);
        for (const note of notes) {
            expect(note.at + note.duration).toBeLessThanOrEqual(0.8);
            expect(note.gain).toBeLessThanOrEqual(0.3);
        }
    }
});
