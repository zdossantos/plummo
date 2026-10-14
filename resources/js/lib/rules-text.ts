/** Preserve Unicode character offsets so labels remain bold across reader pages. */
export function rulesTextRuns(text: string, start: number, length: number) {
    const characters = Array.from(text);
    const labels: Array<{ start: number; end: number }> = [];
    let offset = 0;
    for (const line of text.split('\n')) {
        const chars = Array.from(line);
        const colon = chars.indexOf(':');
        if (colon > 0) labels.push({ start: offset, end: offset + colon + 1 });
        offset += chars.length + 1;
    }
    const runs: Array<{ text: string; bold: boolean }> = [];
    for (
        let index = start;
        index < Math.min(start + length, characters.length);
        index++
    ) {
        const bold = labels.some(
            (label) => index >= label.start && index < label.end,
        );
        const previous = runs.at(-1);
        if (previous?.bold === bold) previous.text += characters[index];
        else runs.push({ text: characters[index], bold });
    }
    return runs;
}
