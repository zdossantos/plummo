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
