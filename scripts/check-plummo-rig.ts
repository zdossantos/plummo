import { chromium, webkit } from '@playwright/test';
import { readFileSync, mkdirSync } from 'node:fs';
import catalog from '../public/plummo/catalog.json';
import rig from '../public/plummo/rig.json';
import {
    composePlummo,
    composeAnimatedPlummo,
} from '../resources/js/lib/plummo';
const parts = Object.fromEntries(
    [
        'base.svg',
        ...catalog.accessories.flatMap((a) => [
            a.front,
            ...(a.back ? [a.back] : []),
        ]),
    ].map((file) => [file, readFileSync(`public/plummo/${file}`, 'utf8')]),
);
const combinations = [
    [],
    ...catalog.accessories.map((a) => [a.id]),
    catalog.slots.map(
        (slot) => catalog.accessories.find((a) => a.slot === slot)!.id,
    ),
];
mkdirSync('/tmp/plummo-motion', { recursive: true });
for (const [name, engine] of Object.entries({ chromium, webkit })) {
    const browser = await engine.launch();
    const page = await browser.newPage();
    let compared = 0;
    for (const color of catalog.colors)
        for (const selected of combinations) {
            const before = composePlummo(
                color.id,
                selected,
                catalog,
                parts,
                'a',
            );
            const after = composeAnimatedPlummo(
                color.id,
                selected,
                catalog,
                parts,
                rig,
                'b',
                false,
            );
            const differences = await page.evaluate(
                async ([before, after]) => {
                    async function pixels(markup: string) {
                        const image = new Image();
                        image.src =
                            'data:image/svg+xml;charset=utf-8,' +
                            encodeURIComponent(
                                `<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512">${markup}</svg>`,
                            );
                        await image.decode();
                        const canvas = document.createElement('canvas');
                        canvas.width = canvas.height = 512;
                        const ctx = canvas.getContext('2d')!;
                        ctx.drawImage(image, 0, 0);
                        return ctx.getImageData(0, 0, 512, 512).data;
                    }
                    const a = await pixels(before),
                        b = await pixels(after);
                    const changed = Array.from(a).flatMap((v, i) =>
                        v !== b[i] ? [i] : [],
                    );
                    return {
                        count: changed.length,
                        positions: changed
                            .slice(0, 12)
                            .map((i) => [
                                Math.floor(i / 4) % 512,
                                Math.floor(i / 2048),
                                a[i],
                                b[i],
                            ]),
                    };
                },
                [before, after],
            );
            if (differences.count)
                throw new Error(
                    `${name}: ${color.id}/${selected.join(',')}: ${JSON.stringify(differences)} changed channels`,
                );
            compared++;
        }
    await page.setContent(
        `<style>body{background:#302140;display:flex;flex-wrap:wrap}svg{width:220px;height:220px}</style>` +
            catalog.accessories
                .filter((a) => a.slot === 'hand')
                .map(
                    (a, i) =>
                        `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">${composeAnimatedPlummo('violet', [a.id, 'round-glasses'], catalog, parts, rig, `avatar-${i}`, true)}</svg>`,
                )
                .join(''),
    );
    const references = await page.evaluate(() =>
        [...document.querySelectorAll('svg')].every((svg) =>
            [...svg.querySelectorAll('[fill]')].every((e) => {
                const id = e
                    .getAttribute('fill')
                    ?.match(/url\(#([^)]*)\)/)?.[1];
                return !id || !!svg.querySelector(`[id="${id}"]`);
            }),
        ),
    );
    const coordinated = await page.evaluate(() =>
        [...document.querySelectorAll('svg')].every((svg) =>
            ['left', 'right'].every((side) =>
                svg
                    .querySelector(`[data-plummo-part="iris-${side}"]`)
                    ?.closest(`[data-plummo-part="eye-${side}"]`),
            ),
        ),
    );
    const attached = await page.evaluate(() =>
        [...document.querySelectorAll('svg')].every((svg) =>
            [
                ['highlight-left', 'iris-left'],
                ['highlight-right', 'iris-right'],
                ['shadow', 'body'],
                ['face', 'body'],
                ['tongue-reflection', 'tongue'],
            ].every(([child, parent]) =>
                svg
                    .querySelector(`[data-plummo-part="${child}"]`)
                    ?.parentElement?.closest(`[data-plummo-part="${parent}"]`),
            ),
        ),
    );
    const linked = await page.evaluate(() =>
        [...document.querySelectorAll('svg')].every((svg) =>
            ['large', 'side'].every(
                (part) =>
                    svg
                        .querySelector(
                            `[data-plummo-part="plume-${part}-reflection"]`,
                        )
                        ?.getAttribute('data-plummo-link') === `plume-${part}`,
            ),
        ),
    );
    if (!linked)
        throw new Error(
            'Overlapping plume reflections need linked independent transforms',
        );
    if (!attached)
        throw new Error(
            'Reflections and facial features must follow their attached surface',
        );
    if (!coordinated)
        throw new Error('Each iris must inherit its own eye movement');
    if (!references)
        throw new Error('Gradient referenced outside its own avatar');
    await page.screenshot({ path: `/tmp/plummo-motion/${name}-objects.png` });
    await page.addStyleTag({
        content: readFileSync('resources/css/plummo-motion.css', 'utf8'),
    });
    const linkedTransforms = await page.evaluate(() => {
        for (const svg of document.querySelectorAll('svg')) {
            (svg as SVGElement).style.setProperty(
                '--plummo-plume-large-angle',
                '12deg',
            );
            (svg as SVGElement).style.setProperty(
                '--plummo-plume-side-angle',
                '-8deg',
            );
            for (const part of ['large', 'side']) {
                const surface = svg.querySelector(
                    `[data-plummo-part="plume-${part}"]`,
                )!;
                const reflection = svg.querySelector(
                    `[data-plummo-part="plume-${part}-reflection"]`,
                )!;
                if (
                    getComputedStyle(surface).transform === 'none' ||
                    getComputedStyle(surface).transform !==
                        getComputedStyle(reflection).transform
                )
                    return false;
            }
            (svg as SVGElement).style.setProperty(
                '--plummo-face-transform',
                'translate(8px, -4px)',
            );
            const face = svg.querySelector('[data-plummo-part="face"]')!;
            const glasses = svg.querySelector(
                '[data-plummo-part="accessory-face"]',
            )!;
            if (
                getComputedStyle(face).transform === 'none' ||
                getComputedStyle(face).transform !==
                    getComputedStyle(glasses).transform
            )
                return false;
            (svg as SVGElement).style.removeProperty('--plummo-face-transform');
            (svg as SVGElement).style.removeProperty(
                '--plummo-plume-large-angle',
            );
            (svg as SVGElement).style.removeProperty(
                '--plummo-plume-side-angle',
            );
        }
        return true;
    });
    if (!linkedTransforms)
        throw new Error(
            'Independent transforms detach reflections or face accessories',
        );
    await page.evaluate(() => {
        for (const svg of document.querySelectorAll('svg'))
            svg.setAttribute('data-plummo-motion', 'points');
        for (const animation of document.getAnimations()) {
            animation.pause();
            animation.currentTime = 560;
        }
    });
    await page.screenshot({ path: `/tmp/plummo-motion/${name}-gestures.png` });
    await page.emulateMedia({ reducedMotion: 'reduce' });
    const moving = await page.evaluate(() => document.getAnimations().length);
    if (moving !== 0)
        throw new Error(
            `${name}: reduced motion has ${moving} active animations`,
        );
    console.log(
        `${name}: ${compared} rest comparisons identical, 8 local gradient sets valid`,
    );
    await browser.close();
}
