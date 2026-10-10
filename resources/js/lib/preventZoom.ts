export function preventZoom() {
    const prevent = (event: Event) => event.preventDefault();
    document.addEventListener('gesturestart', prevent, { passive: false });
    document.addEventListener('gesturechange', prevent, { passive: false });
    document.addEventListener(
        'touchmove',
        (event) => {
            if (event.touches.length > 1) event.preventDefault();
        },
        { passive: false },
    );
    document.addEventListener(
        'wheel',
        (event) => {
            if (event.ctrlKey || event.metaKey) event.preventDefault();
        },
        { passive: false },
    );
    document.addEventListener('keydown', (event) => {
        if (
            (event.ctrlKey || event.metaKey) &&
            ['+', '-', '=', '0'].includes(event.key)
        ) {
            event.preventDefault();
        }
    });
}
