import { expect, test } from 'bun:test';
import { drawingFill } from '../../resources/js/lib/drawing-raster';

test('fills below the old canvas boundary on square sheets and preserves legacy raster dimensions', () => {
    const previous = Object.getOwnPropertyDescriptor(globalThis, 'document');
    const dimensions: number[][] = [];
    Object.defineProperty(globalThis, 'document', {
        configurable: true,
        value: {
            createElement: () => ({
                getContext: () => ({
                    fillRect() {},
                    getImageData: (
                        _x: number,
                        _y: number,
                        width: number,
                        height: number,
                    ) => {
                        dimensions.push([width, height]);
                        const data = new Uint8ClampedArray(
                            width * height * 4,
                        ).fill(255);
                        // A closed horizontal wall separates the upper and lower areas.
                        for (let x = 0; height > 350 && x < width; x++)
                            data.set([0, 0, 0, 255], (350 * width + x) * 4);
                        return { data };
                    },
                }),
            }),
        },
    });
    try {
        const spans = drawingFill([], [0.5, 0.9], '#f05a78', 1000);
        expect(dimensions[0]).toEqual([500, 500]);
        expect(spans[0]).toEqual([351, 0, 500]);
        expect(spans.at(-1)).toEqual([499, 0, 500]);
        // Legacy rounds retain their shorter raster rather than stretching fills.
        drawingFill([], [0.5, 0.5], '#ffffff');
        expect(dimensions[1]).toEqual([500, 300]);
    } finally {
        if (previous) Object.defineProperty(globalThis, 'document', previous);
        else Reflect.deleteProperty(globalThis, 'document');
    }
});
