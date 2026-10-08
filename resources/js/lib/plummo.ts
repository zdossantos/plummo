import type { Accessory, Catalog } from '@/types/rooms';

export function selectAccessory(
    selected: string[],
    id: string,
    accessories: Accessory[],
): string[] {
    if (selected.includes(id)) return selected.filter((item) => item !== id);
    const accessory = accessories.find((item) => item.id === id);
    if (!accessory) return selected;
    const next = selected.filter(
        (item) =>
            accessories.find((entry) => entry.id === item)?.slot !==
            accessory.slot,
    );
    return [...next, id];
}

export function composePlummo(
    colorId: string,
    selected: string[],
    catalog: Catalog,
    parts: Record<string, string>,
    prefix: string,
): string {
    const body = (file: string) =>
        (parts[file] ?? '')
            .replace(/<svg[^>]*>/, '')
            .replace(/<\/svg>\s*$/, '')
            .replace(/<title>.*?<\/title>/gs, '');
    const accessories = catalog.accessories.filter((item) =>
        selected.includes(item.id),
    );
    let base = body('base.svg');
    if (accessories.some((item) => item.coversPlumes))
        base = base.replace(/<g id="plummo-plumes">.*?<\/g>/s, '');
    const color =
        catalog.colors.find((entry) => entry.id === colorId) ??
        catalog.colors[0];
    base = base
        .replaceAll('#b99aef', color.light)
        .replaceAll('#9672dc', color.color)
        .replaceAll('#6950ac', color.dark);
    return [
        ...accessories.map((item) => (item.back ? body(item.back) : '')),
        base,
        ...accessories.map((item) => body(item.front)),
    ]
        .join('')
        .replace(/id="([^"]+)"/g, `id="${prefix}-$1"`)
        .replace(/url\(#([^)]+)\)/g, `url(#${prefix}-$1)`);
}

/** Select independently from each occupied accessory slot. */
export function randomAppearance(
    catalog: Catalog,
    random: () => number = Math.random,
): { color: string; accessories: string[] } {
    const pick = <T>(items: T[]): T | undefined =>
        items[
            Math.min(
                items.length - 1,
                Math.max(0, Math.floor(random() * items.length)),
            )
        ];
    const slots = [...new Set(catalog.accessories.map((item) => item.slot))];
    return {
        color: pick(catalog.colors)?.id ?? 'violet',
        accessories: slots.flatMap((slot) => {
            const item = pick(
                catalog.accessories.filter((item) => item.slot === slot),
            );
            return item ? [item.id] : [];
        }),
    };
}
