import { expect, test } from 'bun:test';
import * as motion from '../../resources/js/lib/plummo-motion';
import type { ChoiceGame } from '../../resources/js/types/rooms';
const game = (
    phase: ChoiceGame['phase'] = 'answer',
    answered = false,
    round = 1,
): ChoiceGame => ({
    id: 10,
    type: 'quiz',
    phase,
    deadline: 100,
    settings: { packs: [], rounds: 5, duration: 30 },
    exhausted: false,
    targetReached: false,
    scores: {},
    round: {
        number: round,
        total: 5,
        question: '',
        audio: null,
        choices: [],
        correct: null,
        awards: { 2: 100 },
    },
    me: { eligible: true, answered, choice: answered ? 1 : null, points: null },
});
test('confirmed events play once, reconnect snapshots stay quiet and next rounds reset the gesture', () => {
    expect(motion.PlummoEvents).toBeFunction();
    const events = new motion.PlummoEvents();
    expect(events.observe(game(), 2)).toBeNull();
    expect(events.observe(game('answer', true), 2)?.motion).toBe('answer');
    expect(events.observe(game('answer', true), 2)).toBeNull();
    expect(events.observe(game('reveal', true), 2)?.motion).toBe('points');
    expect(events.observe(game('reveal', true), 2)).toBeNull();
    expect(events.observe(game('answer', false, 2), 2)?.motion).toBe('idle');
    expect(events.observe(game('answer', true, 2), 2)?.motion).toBe('answer');
    expect(
        new motion.PlummoEvents().observe(game('reveal', true), 2),
    ).toBeNull();
    expect(
        new motion.PlummoEvents().observe(game('answer', true), 2),
    ).toBeNull();
    expect(events.observe(game('paused', true, 2), 2)).toBeNull();
    expect(events.observe(game('resuming', true, 2), 2)?.motion).toBe('resume');
    expect(events.observe(game('resuming', true, 2), 2)).toBeNull();
});

test('mounted reconnect consumes the first fresh snapshot and ignores the stale connected value', () => {
    const events = new motion.PlummoEvents();
    const before = game();
    events.observe(before, 2);
    expect(events.observe(before, 2, false)?.motion).toBe('idle');
    expect(events.observe(before, 2, true)).toBeNull();
    expect(events.observe(game('reveal', true), 2, true)).toBeNull();
    expect(events.observe(game('reveal', true), 2, true)).toBeNull();
    expect(events.observe(game('answer', false, 2), 2, true)?.motion).toBe(
        'idle',
    );
    expect(events.observe(game('answer', true, 2), 2, true)?.motion).toBe(
        'answer',
    );
});
