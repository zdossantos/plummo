import { expect, test } from 'bun:test';
import { floodSpans } from '../../resources/js/lib/drawing-fill';
function image(width: number, height: number) {
    const pixels = new Uint8ClampedArray(width * height * 4).fill(255);
    const black = (x: number, y: number) => pixels.set([0, 0, 0, 255], (y * width + x) * 4);
    return { pixels, black };
}
test('fills only the connected region and stops at a closed outline', () => {
    const { pixels, black } = image(7, 7);
    for (let n = 1; n <= 5; n++) { black(n, 1); black(n, 5); black(1, n); black(5, n); }
    expect(floodSpans(pixels, 7, 7, 3, 3, '#f05a78')).toEqual([[2,2,5], [3,2,5], [4,2,5]]);
    expect(floodSpans(pixels, 7, 7, 0, 0, '#f05a78').some(([row,start,end]) => row === 3 && start <= 3 && end > 3)).toBeFalse();
});
test('an opening joins the inside and outside, same colour is a no-op', () => {
    const { pixels, black } = image(7, 7);
    for (let n = 1; n <= 5; n++) { black(n, 1); black(n, 5); black(1, n); black(5, n); }
    pixels.set([255,255,255,255], (1*7+3)*4);
    expect(floodSpans(pixels, 7, 7, 3, 3, '#f05a78').some(([row,start,end]) => row === 0 && start === 0 && end === 7)).toBeTrue();
    expect(floodSpans(pixels, 7, 7, 3, 3, '#ffffff')).toEqual([]);
});
