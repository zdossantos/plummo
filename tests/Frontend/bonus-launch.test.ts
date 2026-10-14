import { expect, test } from 'bun:test';
import {
    BonusLaunchTracker,
    selectImpactEvents,
} from '../../resources/js/lib/bonus-launch';
import type { GameState } from '../../resources/js/types/rooms';
function snapshot(ids: string[], id = 12): GameState {
    return {
        id,
        bonuses: {
            launches: ids.map((id) => ({
                id,
                kind: 'bolt',
                at: 100,
                actorId: 2,
            })),
        },
    } as GameState;
}
test('announces each new launch in a batch once and skips restored game events', () => {
    const tracker = new BonusLaunchTracker();
    expect(tracker.observe(snapshot(['old']), 100, true)).toEqual([]);
    expect(
        tracker
            .observe(snapshot(['old', 'a', 'b']), 100, true)
            .map((item) => item.id),
    ).toEqual(['a', 'b']);
    expect(tracker.observe(snapshot(['old', 'a', 'b']), 101, true)).toEqual([]);
    expect(tracker.observe(snapshot(['a'], 13), 101, true)).toEqual([]);
    expect(
        tracker
            .observe(snapshot(['a', 'b'], 13), 101, true)
            .map((item) => item.id),
    ).toEqual(['b']);
});
test('waits for the first new snapshot after reconnect before accepting launches', () => {
    const tracker = new BonusLaunchTracker();
    const stale = snapshot([]);
    expect(tracker.observe(stale, 100, true)).toEqual([]);
    expect(tracker.observe(stale, 100, false)).toEqual([]);
    expect(tracker.observe(stale, 100, true)).toEqual([]);
    expect(tracker.observe(snapshot(['missed']), 100, true)).toEqual([]);
    expect(
        tracker
            .observe(snapshot(['missed', 'live']), 100, true)
            .map((item) => item.id),
    ).toEqual(['live']);
    expect(tracker.observe(null, 100, true)).toEqual([]);
    expect(tracker.observe(snapshot(['live']), 100, true)).toEqual([]);
    expect(tracker.observe(snapshot(['live', 'late']), 104, true)).toEqual([]);
});

test('keeps the attack visible when several defensive reactions follow it', () => {
    const attack = { id: 'attack', kind: 'bolt', actorId: 1, at: 100 };
    const reactions = [2, 3, 4].map((actorId) => ({
        id: `shield-${actorId}`,
        kind: 'shield',
        actorId,
        at: 100,
        reaction: true,
    }));
    expect(selectImpactEvents([attack, ...reactions])).toEqual([
        attack,
        reactions[2],
    ]);
    expect(selectImpactEvents([attack])).toEqual([attack]);
    expect(selectImpactEvents(reactions)).toEqual(reactions.slice(-2));
});
