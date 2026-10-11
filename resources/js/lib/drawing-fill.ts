export type FillSpan = [number, number, number];
/** Horizontal runs on a bounded raster; SVG and the wire share these coordinates. */
export function floodSpans(
    pixels: Uint8ClampedArray,
    width: number,
    height: number,
    x: number,
    y: number,
    color: string,
): FillSpan[] {
    if (
        pixels.length !== width * height * 4 ||
        x < 0 ||
        y < 0 ||
        x >= width ||
        y >= height
    )
        return [];
    const seed = (y * width + x) * 4;
    const target = [pixels[seed], pixels[seed + 1], pixels[seed + 2]];
    const rgb = [1, 3, 5].map((offset) =>
        parseInt(color.slice(offset, offset + 2), 16),
    );
    const close = (index: number, values: (number | undefined)[]) =>
        values.every(
            (value, channel) =>
                Math.abs(pixels[index * 4 + channel]! - value!) <= 24,
        );
    if (close(y * width + x, rgb)) return [];
    const visited = new Uint8Array(width * height);
    const queue = new Int32Array(width * height);
    let head = 0,
        tail = 1;
    queue[0] = y * width + x;
    visited[queue[0]!] = 1;
    while (head < tail) {
        const index = queue[head++]!;
        const column = index % width;
        const neighbors = [
            column > 0 ? index - 1 : -1,
            column + 1 < width ? index + 1 : -1,
            index - width,
            index + width,
        ];
        for (const next of neighbors) {
            if (
                next < 0 ||
                next >= visited.length ||
                visited[next] ||
                !close(next, target)
            )
                continue;
            visited[next] = 1;
            queue[tail++] = next;
        }
    }
    const spans: FillSpan[] = [];
    for (let row = 0; row < height; row++) {
        let start = -1;
        for (let column = 0; column <= width; column++) {
            const selected = column < width && visited[row * width + column];
            if (selected && start < 0) start = column;
            if (!selected && start >= 0) {
                spans.push([row, start, column]);
                start = -1;
            }
        }
    }
    return spans;
}
export function fillPath(spans: FillSpan[]): string {
    return spans
        .map(
            ([row, start, end]) =>
                `M${start * 2} ${row * 2}h${(end - start) * 2}v2H${start * 2}z`,
        )
        .join('');
}
