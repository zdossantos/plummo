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
    const kinds = ['bolt', 'dice', 'squatter', 'artist', 'accent', 'sneeze', 'stamp', 'paint', 'shield', 'soap', 'boomerang', 'pinata', 'washer', 'lock', 'mystery', 'bubbles', 'photobomb', 'jelly', 'strike', 'leak', 'freeze', 'extension', 'watch', 'overtime', 'last_breath', 'alarm', 'borrow', 'translator', 'advert', 'scratch', 'speed', 'singer', 'missing_word', 'alphabet', 'curtain', 'giant', 'mirror', 'clumsy'] as const;
    const signatures = kinds.map(kind => bonusSoundNotes(kind));
    expect(new Set(signatures.map(notes => JSON.stringify(notes))).size).toBe(38);
    for (const notes of signatures) {
        expect(notes.length).toBeGreaterThan(0);
        for (const note of notes) {
            expect(note.at + note.duration).toBeLessThanOrEqual(0.8);
            expect(note.gain).toBeLessThanOrEqual(0.3);
        }
    }
});

test('dice preserves all answer IDs through every shuffle for four and eight choices', () => {
    for (const count of [4, 8]) {
        const choices = Array.from({ length: count }, (_, index) => `answer-${index}`);
        for (const time of [100, 101.999, 102, 103.999, 104, 105.999, 106]) {
            const displayed = bonusChoices(choices, [dice], time);
            expect(displayed.map(item => item.index).sort((a, b) => a - b)).toEqual(choices.map((_, index) => index));
            for (const item of displayed) expect(item.choice).toBe(choices[item.index]!);
            expect(bonusChoices(choices, [dice], time)).toEqual(displayed);
        }
        expect(bonusChoices(choices, [dice], 102)).not.toEqual(bonusChoices(choices, [dice], 104));
    }
});

test('events arriving in batches play once and reconnection suppresses only the resumed batch', () => {
    const tracker = new BonusReceiptTracker<{ id: string; at: number }>();
    const events = [{ id: 'a', at: 100 }, { id: 'b', at: 100.5 }];
    expect(tracker.observe(1, events, 101, true)).toEqual(events);
    expect(tracker.observe(1, [...events, { id: 'c', at: 102 }], 102, true)).toEqual([{ id: 'c', at: 102 }]);
    tracker.observe(1, [], 103, false);
    expect(tracker.observe(1, [{ id: 'd', at: 104 }], 104, true)).toEqual([]);
    expect(tracker.observe(1, [{ id: 'd', at: 104 }, { id: 'e', at: 105 }], 105, true)).toEqual([{ id: 'e', at: 105 }]);
    expect(tracker.observe(2, [{ id: 'a', at: 106 }], 106, true)).toEqual([{ id: 'a', at: 106 }]);
});

test('phrase effects persist while writing but never leak into voting or results', () => {
    const effect: BonusEffect = { ...dice, kind: 'accent', expiresAt: null };
    expect(activeBonusEffects([effect], 10000, 'writing')).toEqual([effect]);
    for (const phase of ['paused', 'resuming', 'voting', 'results', 'reveal', 'selecting']) {
        expect(activeBonusEffects([effect], 101, phase)).toEqual([]);
    }
});

import { eatenQuestion, bonusStrokePoints, bonusPlaybackRate } from '../../resources/js/lib/bonuses';

test('missing word preserves punctuation and hides one meaningful word only', () => {
    expect(eatenQuestion('Combien de minutes contient une heure ?', true)).toBe('Combien de ▰▰▰▰ contient une heure ?');
    expect(eatenQuestion('Combien de minutes contient une heure ?', false)).toBe('Combien de minutes contient une heure ?');
});
test('clumsy strokes are deterministic, bounded and leave erasers untouched', () => {
    const input: [number, number][] = [[0, 0], [1, 1], [0.5, 0.5]];
    expect(bonusStrokePoints(input, true, 'erase')).toEqual(input);
    const one = bonusStrokePoints(input, true, 'brush');
    expect(one).toEqual(bonusStrokePoints(input, true, 'brush'));
    expect(one[2]).not.toEqual(input[2]);
    expect(one.every(p => p.every(n => n >= 0 && n <= 1))).toBe(true);
});
test('shared audio speed restores on expiry', () => {
    expect(bonusPlaybackRate([{id: 'fast',kind: 'speed',actorId: 1,startedAt: 0,expiresAt: 8}], 2)).toBe(1.25);
    expect(bonusPlaybackRate([{id: 'fast',kind: 'speed',actorId: 1,startedAt: 0,expiresAt: 8}], 8)).toBe(1);
});

test('fill uses the same preview geometry as a local giant clumsy brush', async () => {
    const { bonusRenderedStroke } = await import('../../resources/js/lib/bonuses');
    const local = { id: 1, color: '#35236b', width: 4, bonusWidth: 12, clumsy: true, tool: 'brush' as const, points: [[0.5,0.5],[0.8,0.5]] as [number,number][] };
    const displayed = bonusRenderedStroke(local);
    expect(displayed.width).toBe(12);
    expect(displayed.points).toEqual(bonusStrokePoints(local.points,true,'brush'));
    expect(bonusRenderedStroke(displayed)).toEqual(displayed);
    expect(local.width).toBe(4);
});
