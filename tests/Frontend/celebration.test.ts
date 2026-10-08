import { expect, test } from 'bun:test';
import { roundCelebration } from '../../resources/js/lib/celebration';
import type { GameState } from '../../resources/js/types/rooms';

const round = {
    id: 12,
    type: 'quiz',
    phase: 'reveal',
    round: { number: 2, awards: { 1: 100, 2: 0, 3: 30 } },
} as unknown as GameState;
test('celebrates awarded players once across repeated snapshots without revealing choices', () => {
    const result = roundCelebration(round, '');
    expect(result).toEqual({ key: '12:2', gains: { 1: 100, 3: 30 } });
    expect(roundCelebration({ ...round }, result!.key)).toBeNull();
    expect(roundCelebration({ ...round, phase: 'answer' }, '')).toBeNull();
    expect(
        roundCelebration(
            { ...round, round: { ...round.round, number: 3 } },
            result!.key,
        )?.key,
    ).toBe('12:3');
    expect(roundCelebration(null, '')).toBeNull();
});
