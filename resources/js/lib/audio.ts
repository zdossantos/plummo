export async function playAudio(element: HTMLAudioElement): Promise<void> {
    if (element.error) {
        const position = element.currentTime;
        element.load();
        if (position > 0) {
            element.addEventListener(
                'loadedmetadata',
                () => {
                    element.currentTime = position;
                },
                { once: true },
            );
        }
    }
    await element.play();
}

/** Returns a cancellation function so a new transition cannot fight an old fade. */
export function fadeAudio(
    element: Pick<HTMLAudioElement, 'volume'>,
    target: number,
    duration: number,
    done?: () => void,
): () => void {
    const start = performance.now();
    const initial = element.volume;
    let frame = 0;
    function tick(now: number) {
        const progress = Math.min(1, (now - start) / duration);
        element.volume = initial + (target - initial) * progress;
        if (progress < 1) frame = requestAnimationFrame(tick);
        else done?.();
    }
    frame = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(frame);
}
