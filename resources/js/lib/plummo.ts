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

export interface PlummoRig {
    version: number;
    sourceHash: string;
    defs: string;
    attributes: string;
    parts: Record<string, { svg: string; pivot: number[]; parent: string }>;
}

/** Repository-owned fragments only. Rest order matches the original SVG. */
export function composeAnimatedPlummo(
    colorId: string,
    selected: string[],
    catalog: Catalog,
    parts: Record<string, string>,
    rig: PlummoRig,
    prefix: string,
    foreground: boolean,
): string {
    const chosen = catalog.accessories.filter((a) => selected.includes(a.id));
    const body = (file?: string) =>
        file
            ? (parts[file] ?? '')
                  .replace(/<svg[^>]*>/, '')
                  .replace(/<\/svg>\s*$/, '')
                  .replace(/<title>.*?<\/title>/gs, '')
            : '';
    const group = (name: string, svg: string, pivot = [256, 320]) =>
        `<g data-plummo-part="${name}" style="transform-origin:${pivot[0]}px ${pivot[1]}px">${svg}</g>`;
    const piece = (name: string) =>
        group(name, rig.parts[name].svg, rig.parts[name].pivot);
    const hand = chosen.find((a) => a.slot === 'hand');
    const arm = (side: string) =>
        group(
            `arm-${side}`,
            [
                side === 'right' && foreground
                    ? group('object-back', body(hand?.back))
                    : '',
                `<g fill="url(#plummo-tone)">${piece(`hand-${side}`)}${foreground ? piece(`hand-${side}-reflection`) : ''}</g>`,
                side === 'right' && foreground
                    ? group('object-front', body(hand?.front))
                    : '',
            ].join(''),
            rig.parts[`hand-${side}`].pivot,
        );
    const accessory = (slot: string, svg: string) =>
        group(`accessory-${slot}`, svg).replace(
            ' style=',
            slot === 'face' ? ' data-plummo-link="face" style=' : ' style=',
        );
    const behind = chosen
        .map((a) =>
            foreground && a.slot === 'hand'
                ? ''
                : accessory(a.slot, body(a.back)),
        )
        .join('');
    const front = chosen
        .map((a) =>
            foreground && a.slot === 'hand'
                ? ''
                : accessory(a.slot, body(a.front)),
        )
        .join('');
    const feet = `<g fill="url(#plummo-tone)">${piece('foot-left')}${piece('foot-right')}</g>`;
    const plumes = chosen.some((a) => a.coversPlumes)
        ? ''
        : Object.keys(rig.parts)
              .filter((name) => name.startsWith('plume-'))
              .map((name) =>
                  name.endsWith('-reflection')
                      ? piece(name).replace(
                            ' style=',
                            ` data-plummo-link="${rig.parts[name].parent}" style=`,
                        )
                      : piece(name),
              )
              .join('');
    const eye = (side: string) =>
        group(
            `eye-${side}`,
            piece(`eye-white-${side}`) +
                group(
                    `iris-${side}`,
                    rig.parts[`iris-${side}`].svg + piece(`highlight-${side}`),
                    rig.parts[`iris-${side}`].pivot,
                ),
            rig.parts[`iris-${side}`].pivot,
        );
    const mouth = group(
        'mouth',
        piece('mouth-opening') +
            group(
                'tongue',
                rig.parts.tongue.svg + piece('tongue-reflection'),
                rig.parts.tongue.pivot,
            ),
        rig.parts['mouth-opening'].pivot,
    );
    const face = group(
        'face',
        piece('brow-left') +
            piece('brow-right') +
            eye('left') +
            eye('right') +
            piece('cheek-left') +
            piece('cheek-right') +
            mouth,
    );
    // Moving arms are painted last; their objects and reflections share the pivot.
    const pose = group(
        'pose',
        group(
            'body',
            behind +
                `<g${rig.attributes}>${feet}${plumes}${foreground ? '' : arm('left') + arm('right') + piece('hands-reflection')}${rig.parts.body.svg}${piece('shadow')}${face}</g>` +
                front +
                (foreground
                    ? `<g${rig.attributes}>${arm('left')}${arm('right')}</g>`
                    : ''),
            rig.parts.body.pivot,
        ),
    );
    const color =
        catalog.colors.find((c) => c.id === colorId) ?? catalog.colors[0];
    return (rig.defs + pose)
        .replaceAll('#b99aef', color.light)
        .replaceAll('#9672dc', color.color)
        .replaceAll('#6950ac', color.dark)
        .replace(/id="([^"]+)"/g, `id="${prefix}-$1"`)
        .replace(/url\(#([^)]+)\)/g, `url(#${prefix}-$1)`);
}
