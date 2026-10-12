import type { Stroke } from '@/types/rooms';
import { bonusRenderedStroke } from './bonuses';
import { floodSpans } from './drawing-fill';
export function drawingFill(
    strokes: Stroke[],
    point: [number, number],
    color: string,
    canvasHeight = 600,
) {
    const canvas = document.createElement('canvas');
    canvas.width = 500;
    const height = canvasHeight / 2;
    canvas.height = height;
    const context = canvas.getContext('2d', { willReadFrequently: true });
    if (!context) return [];
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, 500, height);
    for (const stroke of strokes.map(bonusRenderedStroke)) {
        context.fillStyle = stroke.color;
        if (stroke.tool === 'fill') {
            for (const [row, start, end] of stroke.spans ?? [])
                context.fillRect(start, row, end - start, 1);
            continue;
        }
        context.strokeStyle = stroke.color;
        context.lineWidth = stroke.width / 2;
        context.lineCap = 'round';
        context.lineJoin = 'round';
        context.beginPath();
        stroke.points.forEach(([x, y], index) =>
            index === 0
                ? context.moveTo(x * 500, y * height)
                : context.lineTo(x * 500, y * height),
        );
        context.stroke();
        if (stroke.points.length === 1) {
            context.beginPath();
            context.arc(
                stroke.points[0]![0] * 500,
                stroke.points[0]![1] * height,
                stroke.width / 4,
                0,
                Math.PI * 2,
            );
            context.fill();
        }
    }
    return floodSpans(
        context.getImageData(0, 0, 500, height).data,
        500,
        height,
        Math.min(499, Math.floor(point[0] * 500)),
        Math.min(height - 1, Math.floor(point[1] * height)),
        color,
    );
}
