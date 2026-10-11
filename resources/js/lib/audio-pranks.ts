import { activeBonusEffects, bonusPlaybackRate } from './bonuses';
import type { BonusEffect } from '@/types/rooms';

type Media = Pick<
    HTMLAudioElement,
    'playbackRate' | 'currentTime' | 'paused' | 'duration'
>;
type Scheduler = { every: (fn: () => void) => () => void };

/** One loop per effect/media, always restored when its playback context ends. */
export class AudioPranks {
    private media?: Media;
    private scratchId?: string;
    private cancel?: () => void;
    constructor(
        private scheduler: Scheduler = {
            every(fn) {
                const timer = setInterval(fn, 350);
                return () => clearInterval(timer);
            },
        },
    ) {}

    update(
        media: Media | undefined,
        effects: BonusEffect[],
        now: number,
        playing: boolean,
    ) {
        if (media !== this.media) this.dispose();
        this.media = media;
        if (!media) return;
        const active = playing
            ? activeBonusEffects(effects, now, 'answer')
            : [];
        media.playbackRate = bonusPlaybackRate(active, now);
        const effect = active.find((item) => item.kind === 'scratch');
        if (effect?.id === this.scratchId) return;
        this.cancel?.();
        this.cancel = undefined;
        this.scratchId = effect?.id;
        if (!effect) return;
        const position = media.currentTime;
        this.cancel = this.scheduler.every(() => {
            if (!media.paused && Number.isFinite(media.duration))
                media.currentTime = Math.min(
                    position,
                    Math.max(0, media.duration - 0.35),
                );
        });
    }

    dispose() {
        this.cancel?.();
        this.cancel = undefined;
        this.scratchId = undefined;
        if (this.media) this.media.playbackRate = 1;
        this.media = undefined;
    }
}
