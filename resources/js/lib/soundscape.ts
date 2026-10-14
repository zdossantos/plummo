import type { InjectionKey, Ref } from 'vue';
import type { BonusKind } from '@/types/rooms';

const bonusSamples: Record<BonusKind, string> = {
    bolt: 'scenes/bolt',
    dice: 'scenes/dice',
    squatter: 'scenes/squatter',
    artist: 'scenes/artist',
    accent: 'scenes/accent',
    sneeze: 'scenes/sneeze',
    stamp: 'scenes/stamp',
    paint: 'scenes/paint',
    shield: 'scenes/shield',
    soap: 'scenes/soap',
    boomerang: 'scenes/boomerang',
    pinata: 'scenes/pinata',
    washer: 'scenes/washer',
    lock: 'scenes/lock',
    mystery: 'scenes/mystery',
    bubbles: 'scenes/bubbles',
    photobomb: 'scenes/photobomb',
    jelly: 'scenes/jelly',
    strike: 'scenes/strike',
    leak: 'scenes/leak',
    freeze: 'scenes/freeze',
    extension: 'scenes/extension',
    watch: 'scenes/watch',
    overtime: 'scenes/overtime',
    last_breath: 'scenes/last_breath',
    alarm: 'scenes/alarm',
    borrow: 'scenes/borrow',
    translator: 'scenes/translator',
    advert: 'scenes/advert',
    scratch: 'scenes/scratch',
    speed: 'scenes/speed',
    singer: 'scenes/singer',
    missing_word: 'scenes/missing_word',
    alphabet: 'scenes/alphabet',
    curtain: 'scenes/curtain',
    giant: 'scenes/giant',
    mirror: 'scenes/mirror',
    clumsy: 'scenes/clumsy',
};

export function bonusSoundFile(kind: BonusKind): string {
    return `/audio/sfx/${bonusSamples[kind]}.mp3`;
}

export const soundSettings: InjectionKey<{
    enabled: Ref<boolean>;
    volume: Ref<number>;
    media: Ref<HTMLAudioElement | undefined>;
    blocked: Ref<boolean>;
}> = Symbol('plummo-sound');
export function ambianceVolume(volume: number, blind: boolean): number {
    return volume * (blind ? 0.06 : 0.3);
}

/** Decoded original theme loops continuously without a media seek at the junction. */
export class Soundscape {
    private context: AudioContext;
    private ambiance: GainNode;
    private voices: GainNode;
    private music?: AudioBufferSourceNode;
    private loading?: Promise<void>;
    private closed = false;
    private volume = 0;
    private playbackGeneration = 0;
    private samples = new Map<string, Promise<AudioBuffer>>();
    constructor() {
        this.context = new AudioContext();
        this.ambiance = this.context.createGain();
        this.voices = this.context.createGain();
        this.ambiance.gain.value = 0;
        this.voices.gain.value = 0;
        this.ambiance.connect(this.context.destination);
        this.voices.connect(this.context.destination);
    }
    async start() {
        if (this.closed) return;
        await this.context.resume();
        for (const file of [
            ...Object.keys(bonusSamples).map((kind) =>
                bonusSoundFile(kind as BonusKind),
            ),
            '/audio/sfx/select_001.mp3',
            '/audio/sfx/select_002.mp3',
        ]) {
            void this.loadSample(file).catch(() => {});
        }
        if (this.music) return;
        this.loading ??= this.loadMusic().catch((error: unknown) => {
            this.loading = undefined;
            throw error;
        });
        await this.loading;
    }
    private async loadMusic() {
        const response = await fetch('/audio/plummo-ambiance.mp3');
        if (!response.ok) throw new Error('Could not load ambiance');
        const buffer = await this.context.decodeAudioData(
            await response.arrayBuffer(),
        );
        if (this.closed) return;
        const source = this.context.createBufferSource();
        source.buffer = buffer;
        source.loop = true;
        source.connect(this.ambiance);
        source.start();
        this.music = source;
    }
    setVolume(volume: number, blind: boolean) {
        if (volume <= 0 && this.volume > 0) this.playbackGeneration++;
        this.volume = volume;
        this.ambiance.gain.setTargetAtTime(
            ambianceVolume(volume, blind),
            this.context.currentTime,
            0.4,
        );
        this.voices.gain.setTargetAtTime(
            volume * (blind ? 0.01 : 0.09),
            this.context.currentTime,
            0.1,
        );
    }
    private loadSample(file: string): Promise<AudioBuffer> {
        let loading = this.samples.get(file);
        if (!loading) {
            loading = (async () => {
                const response = await fetch(file);
                if (!response.ok)
                    throw new Error('Could not load sound effect');
                return this.context.decodeAudioData(
                    await response.arrayBuffer(),
                );
            })().catch((error: unknown) => {
                this.samples.delete(file);
                throw error;
            });
            this.samples.set(file, loading);
        }
        return loading;
    }
    private async sample(file: string) {
        if (this.closed || this.volume <= 0 || this.context.state !== 'running')
            return;
        const at = this.context.currentTime;
        const generation = this.playbackGeneration;
        try {
            const buffer = await this.loadSample(file);
            // A slow download must not announce an old action or undo a mute.
            if (
                this.closed ||
                generation !== this.playbackGeneration ||
                this.volume <= 0 ||
                this.context.state !== 'running' ||
                this.context.currentTime - at > 0.75
            )
                return;
            const source = this.context.createBufferSource();
            source.buffer = buffer;
            source.connect(this.voices);
            source.onended = () => source.disconnect();
            source.start();
        } catch {
            // Missing audio remains silent; the next event can retry the request.
        }
    }
    chirp(seed = 0) {
        return this.sample(`/audio/sfx/select_00${(seed % 2) + 1}.mp3`);
    }
    prank(kind: BonusKind) {
        return this.sample(bonusSoundFile(kind));
    }
    close() {
        this.closed = true;
        this.samples.clear();
        this.music?.stop();
        this.music?.disconnect();
        void this.context.close();
    }
}
