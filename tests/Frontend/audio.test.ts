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

test('keeps ambiance audible during blind excerpts and restores it afterwards', async () => {
    const { ambianceVolume } = await import('../../resources/js/lib/soundscape');
    expect(ambianceVolume(0.6, true)).toBeCloseTo(0.036);
    expect(ambianceVolume(0.6, false)).toBeCloseTo(0.18);
    expect(ambianceVolume(0, true)).toBe(0);
});

test('fades smoothly and cancels an obsolete transition before it can overwrite the volume', async () => {
    const { fadeAudio } = await import('../../resources/js/lib/audio');
    const oldRequest = globalThis.requestAnimationFrame;
    const oldCancel = globalThis.cancelAnimationFrame;
    let callback: FrameRequestCallback | undefined;
    let finished = false;
    globalThis.requestAnimationFrame = (fn) => { callback = fn; return 1; };
    globalThis.cancelAnimationFrame = () => { callback = undefined; };
    try {
        const element = { volume: 0 };
        const start = performance.now();
        const cancel = fadeAudio(element, 0.6, 1000, () => { finished = true; });
        callback?.(start + 500);
        expect(element.volume).toBeGreaterThan(0.29);
        expect(element.volume).toBeLessThan(0.31);
        expect(finished).toBe(false);
        cancel();
        expect(callback).toBeUndefined();
        const next = performance.now();
        fadeAudio(element, 0, 200, () => { finished = true; });
        callback?.(next + 201);
        expect(element.volume).toBe(0);
        expect(finished).toBe(true);
    } finally {
        globalThis.requestAnimationFrame = oldRequest;
        globalThis.cancelAnimationFrame = oldCancel;
    }
});

test('keeps audio volume valid when the first animation timestamp precedes the fade start', async () => {
    const { fadeAudio } = await import('../../resources/js/lib/audio');
    const oldRequest = globalThis.requestAnimationFrame;
    const oldCancel = globalThis.cancelAnimationFrame;
    let callback: FrameRequestCallback | undefined;
    globalThis.requestAnimationFrame = (fn) => { callback = fn; return 1; };
    globalThis.cancelAnimationFrame = () => {};
    try {
        let volume = 0;
        const element = {
            get volume() { return volume; },
            set volume(value: number) {
                if (value < 0 || value > 1) throw new RangeError('Invalid media volume');
                volume = value;
            },
        };
        const before = performance.now() - 16;
        fadeAudio(element, 0.6, 1000);
        callback?.(before);
        expect(volume).toBe(0);
        callback?.(performance.now() + 1001);
        expect(volume).toBe(0.6);
    } finally {
        globalThis.requestAnimationFrame = oldRequest;
        globalThis.cancelAnimationFrame = oldCancel;
    }
});

test('starts one continuous decoded loop and stops it when disposed', async () => {
    const originalContext = globalThis.AudioContext;
    const originalFetch = globalThis.fetch;
    let starts = 0;
    let stops = 0;
    let loads = 0;
    const source = { buffer: null, loop: false, connect() {}, start() { starts++; }, stop() { stops++; }, disconnect() {} };
    class FakeContext {
        currentTime = 0;
        state = 'running';
        destination = {};
        createGain() { return { gain: { value: 0, setTargetAtTime() {} }, connect() {} }; }
        createBufferSource() { return source; }
        async decodeAudioData() { return { duration: 147.692 }; }
        async resume() {}
        async close() { this.state = 'closed'; }
    }
    globalThis.AudioContext = FakeContext as unknown as typeof AudioContext;
    globalThis.fetch = (async () => { loads++; return new Response(new ArrayBuffer(8)); }) as typeof fetch;
    try {
        const { Soundscape } = await import('../../resources/js/lib/soundscape');
        const sound = new Soundscape();
        await Promise.all([sound.start(), sound.start()]);
        expect(loads).toBe(1);
        expect(starts).toBe(1);
        expect(source.loop).toBe(true);
        sound.setVolume(0, false);
        await sound.start();
        expect(starts).toBe(1);
        sound.close();
        expect(stops).toBe(1);
    } finally {
        globalThis.AudioContext = originalContext;
        globalThis.fetch = originalFetch;
    }
});
