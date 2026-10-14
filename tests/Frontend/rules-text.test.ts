import { expect, test } from 'bun:test';
import { rulesTextRuns } from '../../resources/js/lib/rules-text';

test('emphasizes section labels while preserving all text', () => {
    const text =
        'But : trouver.\n\nDéroulement : jouer.\n\nPoints : seul joueur : 65.';
    const runs = rulesTextRuns(text, 0, Array.from(text).length);
    expect(runs.map((run) => run.text).join('')).toBe(text);
    expect(runs.filter((run) => run.bold).map((run) => run.text)).toEqual([
        'But :',
        'Déroulement :',
        'Points :',
    ]);
});

test('keeps label formatting across page boundaries and escapes no text as HTML', () => {
    const text = 'Goal: find <b>🦉</b>.\n\nOn your phone: answer.';
    const split = Array.from(text).indexOf('p');
    const before = rulesTextRuns(text, 0, split);
    const after = rulesTextRuns(text, split, Array.from(text).length - split);
    expect([...before, ...after].map((run) => run.text).join('')).toBe(text);
    expect(after[0]).toEqual({ text: 'phone:', bold: true });
});
