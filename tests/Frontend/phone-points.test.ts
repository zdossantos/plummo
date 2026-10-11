import { expect, test } from 'bun:test';
import { PhonePointsTracker } from '../../resources/js/lib/phone-points';
import type { GameState } from '../../resources/js/types/rooms';

function snapshot(points: number | null, round = 1, id = 12): GameState {
    return { id, round: { number: round }, me: { points } } as GameState;
}

test('shows only new gains, including incremental drawing awards', () => {
    const tracker = new PhonePointsTracker();
    expect(tracker.observe(snapshot(null))).toBeNull();
    expect(tracker.observe(snapshot(100))).toBe(100);
    expect(tracker.observe(snapshot(100))).toBeNull();
    expect(tracker.observe(snapshot(130))).toBe(30);
    expect(tracker.observe(snapshot(0, 2))).toBeNull();
    expect(tracker.observe(snapshot(50, 2))).toBe(50);
    expect(tracker.observe(snapshot(0, 2))).toBeNull();
});

test('never replays initial results, disconnected gains or old rounds', () => {
    const tracker = new PhonePointsTracker();
    expect(tracker.observe(snapshot(100))).toBeNull();
    const stale = snapshot(100);
    expect(tracker.observe(stale, false)).toBeNull();
    expect(tracker.observe(stale, true)).toBeNull();
    expect(tracker.observe(snapshot(140), true)).toBeNull();
    expect(tracker.observe(snapshot(160), true)).toBe(20);
    expect(tracker.observe(snapshot(100, 2))).toBeNull();
    expect(tracker.observe(snapshot(200, 1, 13))).toBeNull();
    expect(tracker.observe(null)).toBeNull();
    expect(tracker.observe(snapshot(200, 1, 13))).toBeNull();
});
