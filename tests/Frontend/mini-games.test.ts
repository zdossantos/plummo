import { expect, test } from 'bun:test';
import { cupPositions, sceneElapsed } from '../../resources/js/lib/mini-games';
const swaps = [
    { at: 2.5, duration: 0.7, positions: [0, 1] as [number, number] },
    { at: 3.38, duration: 0.7, positions: [1, 2] as [number, number] },
];
test('cup identities follow successive position swaps and end on exact slots', () => {
    expect(cupPositions(swaps, 0).map(p => p.x)).toEqual([0, 1, 2]);
    expect(cupPositions(swaps, 3.3).map(p => p.x)).toEqual([1, 0, 2]);
    expect(cupPositions(swaps, 9).map(p => p.x)).toEqual([2, 0, 1]);
    const halfway = cupPositions(swaps, 2.85);
    expect(halfway[0]!.x).toBeCloseTo(0.5);
    expect(halfway[1]!.x).toBeCloseTo(0.5);
    expect(halfway[0]!.y).toBeGreaterThan(0);
    expect(halfway[1]!.y).toBeLessThan(0);
});
test('scene clock uses the shared deadline and preserves paused or resumed progress', () => {
    expect(sceneElapsed('observing', 8.8, 2, 110, 104)).toBeCloseTo(2.8);
    expect(sceneElapsed('paused', 8.8, 2, 110, 200)).toBe(2);
    expect(sceneElapsed('resuming', 8.8, 2, 110, 200)).toBe(2);
    expect(sceneElapsed('answer', 8.8, 2, 110, 200)).toBe(8.8);
});
