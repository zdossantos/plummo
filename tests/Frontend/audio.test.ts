import { expect, test } from 'bun:test';
import { playAudio } from '../../resources/js/lib/audio';

test('reloads a failed media request before retry and restores its position after metadata', async () => {
    let loaded = false;
    let metadata: (() => void) | undefined;
    const element = {
        error: { code: 4 }, currentTime: 12,
        load() { loaded = true; this.currentTime = 0; },
        addEventListener(event: string, callback: () => void) { if (event === 'loadedmetadata') metadata = callback; },
        async play() { if (!loaded) throw new Error('Failed media remains unusable'); },
    };
    await playAudio(element as unknown as HTMLAudioElement);
    expect(loaded).toBe(true);
    metadata?.();
    expect(element.currentTime).toBe(12);
});

test('keeps buffered audio and its position for normal resume or autoplay refusal', async () => {
    let loads = 0;
    const element = { error: null, currentTime: 5, load() { loads++; }, async play() {} };
    await playAudio(element as unknown as HTMLAudioElement);
    expect(loads).toBe(0);
    expect(element.currentTime).toBe(5);
});
