import { expect, test } from 'bun:test';
import { initialHomePath } from '../../resources/js/lib/home-mode';

test('orients phones including landscape towards joining and wide screens towards hosting', () => {
    for (const [width, height] of [
        [390, 844],
        [844, 390],
        [768, 1024],
    ]) {
        expect(initialHomePath(width, height)).toBe('/join');
    }
    for (const [width, height] of [
        [1920, 1080],
        [1280, 720],
        [1024, 768],
        [800, 600],
    ]) {
        expect(initialHomePath(width, height)).toBe('/host');
    }
});
