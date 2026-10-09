import type { InjectionKey, Ref } from 'vue';

export const soundSettings: InjectionKey<{
    enabled: Ref<boolean>;
    volume: Ref<number>;
}> = Symbol('plummo-sound');
export function ambianceVolume(volume: number, blind: boolean): number {
    return volume * (blind ? 0.06 : 0.3);
}

/** Original pentatonic theme; scheduling ahead keeps the loop independent of rendering. */
export class Soundscape {
    private context: AudioContext;
    private ambiance: GainNode;
    private voices: GainNode;
    private timer: ReturnType<typeof setInterval>;
    private next = 0;
    private step = 0;
    constructor() {
        this.context = new AudioContext();
        this.ambiance = this.context.createGain();
        this.voices = this.context.createGain();
        this.ambiance.gain.value = 0;
        this.voices.gain.value = 0;
        this.ambiance.connect(this.context.destination);
        this.voices.connect(this.context.destination);
        this.next = this.context.currentTime + 0.1;
        this.timer = setInterval(() => this.schedule(), 100);
    }
    async start() {
        await this.context.resume();
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
    private schedule() {
        if (this.context.state !== 'running') return;
        if (this.next < this.context.currentTime)
            this.next = this.context.currentTime + 0.1;
        const melody = [0, 7, 12, 16, 14, 7, 4, 12, 9, 16, 19, 14, 12, 7, 4, 7];
        while (this.next < this.context.currentTime + 0.25) {
            const bar = Math.floor(this.step / 16) % 4;
            const root = [130.81, 110, 87.31, 98][bar];
            if (this.step % 4 === 0)
                this.note(root, this.next, 2, 0.12, this.ambiance);
            this.note(
                261.63 * 2 ** (melody[this.step % 16] / 12),
                this.next,
                0.85,
                0.15,
                this.ambiance,
            );
            this.step++;
            this.next += 0.5;
        }
    }
    chirp(seed = 0) {
        if (this.context.state !== 'running') return;
        const now = this.context.currentTime;
        const pitch = 420 + (seed % 7) * 37;
        this.note(pitch, now, 0.16, 0.25, this.voices, 'triangle');
        this.note(pitch * 1.3, now + 0.12, 0.2, 0.2, this.voices, 'triangle');
    }
    close() {
        clearInterval(this.timer);
        void this.context.close();
    }
}
