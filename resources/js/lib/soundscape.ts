import type { InjectionKey, Ref } from 'vue';

export const soundSettings: InjectionKey<{
    enabled: Ref<boolean>;
    volume: Ref<number>;
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
    private note(
        frequency: number,
        start: number,
        duration: number,
        gain: number,
        output: GainNode,
        type: OscillatorType = 'sine',
    ) {
        const oscillator = this.context.createOscillator();
        const envelope = this.context.createGain();
        oscillator.type = type;
        oscillator.frequency.value = frequency;
        envelope.gain.setValueAtTime(0, start);
        envelope.gain.linearRampToValueAtTime(
            gain,
            start + Math.min(0.04, duration / 4),
        );
        envelope.gain.exponentialRampToValueAtTime(0.0001, start + duration);
        oscillator.connect(envelope);
        envelope.connect(output);
        oscillator.start(start);
        oscillator.stop(start + duration + 0.05);
        oscillator.onended = () => {
            oscillator.disconnect();
            envelope.disconnect();
        };
    }
    chirp(seed = 0) {
        if (this.context.state !== 'running') return;
        const now = this.context.currentTime;
        const pitch = 420 + (seed % 7) * 37;
        this.note(pitch, now, 0.16, 0.25, this.voices, 'triangle');
        this.note(pitch * 1.3, now + 0.12, 0.2, 0.2, this.voices, 'triangle');
    }
    close() {
        this.closed = true;
        this.music?.stop();
        this.music?.disconnect();
        void this.context.close();
    }
}
