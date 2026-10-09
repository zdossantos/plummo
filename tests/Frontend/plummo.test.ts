import { expect, test } from 'bun:test';
import { composePlummo, selectAccessory, randomAppearance } from '../../resources/js/lib/plummo';

const catalog = { colors: [{ id: 'blue', color: '#123456', light: '#abcdef', dark: '#654321' }], accessories: [
    { id: 'cap', slot: 'head', front: 'cap.svg', coversPlumes: true },
    { id: 'crown', slot: 'head', front: 'crown.svg' },
    { id: 'round-glasses', slot: 'face', front: 'round-glasses.svg' },
    { id: 'tie', slot: 'neck', front: 'tie.svg', back: 'tie-back.svg' },
    { id: 'wand', slot: 'hand', front: 'wand.svg' },
] };
const parts = { 'base.svg': '<svg><defs><linearGradient id="tone"/></defs><g id="plummo-plumes"><path fill="#b99aef"/></g><path fill="#9672dc" stroke="#6950ac" style="fill:url(#tone)"/></svg>', 'cap.svg': '<svg><path id="cap"/></svg>', 'crown.svg': '<svg><path id="crown"/></svg>', 'tie.svg': '<svg><path id="tie"/></svg>', 'tie-back.svg': '<svg><path id="behind"/></svg>' };

test('replaces accessories in the same slot and combines every other slot', () => {
    expect(selectAccessory(['cap', 'tie'], 'crown', catalog.accessories)).toEqual(['tie', 'crown']);
    expect(selectAccessory(['cap', 'tie'], 'wand', catalog.accessories)).toEqual(['cap', 'tie', 'wand']);
    expect(selectAccessory(['cap', 'tie', 'wand'], 'round-glasses', catalog.accessories)).toEqual(['cap', 'tie', 'wand', 'round-glasses']);
    expect(selectAccessory(['cap', 'tie'], 'cap', catalog.accessories)).toEqual(['tie']);
});
test('composes back and front layers with recolored base, covered plumes and unique gradient references', () => {
    const svg = composePlummo('blue', ['cap', 'tie'], catalog, parts, 'avatar-1');
    expect(svg).not.toContain('plummo-plumes');
    expect(svg).toContain('fill="#123456"');
    expect(svg).toContain('stroke="#654321"');
    expect(svg).toContain('url(#avatar-1-tone)');
    expect(svg.indexOf('avatar-1-behind')).toBeLessThan(svg.indexOf('avatar-1-tone'));
    expect(svg.indexOf('avatar-1-cap')).toBeGreaterThan(svg.indexOf('avatar-1-tone'));
    expect(composePlummo('blue', ['crown'], catalog, parts, 'avatar-2')).toContain('avatar-2-plummo-plumes');
});

test('random appearance selects a valid color and one item from every available slot', () => {
    for (const value of [0, 0.3, 0.999999]) {
        const result = randomAppearance(catalog, () => value);
        expect(result.color).toBe('blue');
        expect(result.accessories).toHaveLength(4);
        expect(new Set(result.accessories.map(id => catalog.accessories.find(item => item.id === id)?.slot)).size).toBe(4);
    }
    expect(randomAppearance({ colors: [], accessories: [] }, () => 0)).toEqual({ color: 'violet', accessories: [] });
});

// Exercise the real vector kit, including every two-layer hand object.
import * as composer from '../../resources/js/lib/plummo';
import realCatalog from '../../public/plummo/catalog.json';
import { createHash } from 'node:crypto';
import { readFileSync, existsSync } from 'node:fs';
const realParts = Object.fromEntries(['base.svg', ...realCatalog.accessories.flatMap(a => [a.front, ...(a.back ? [a.back] : [])])].map(file => [file, readFileSync(`public/plummo/${file}`, 'utf8')]));

test('articulated composition exposes independent features and keeps objects attached to the right hand', () => {
    expect(composer.composeAnimatedPlummo).toBeFunction();
    expect(existsSync('public/plummo/rig.json')).toBe(true);
    const rig = JSON.parse(readFileSync('public/plummo/rig.json', 'utf8'));
    expect(rig.sourceHash).toBe(createHash('sha256').update(realParts['base.svg']).digest('hex'));
    const svg = composer.composeAnimatedPlummo('violet', [], realCatalog, realParts, rig, 'rig', false);
    for (const name of ['body', 'shadow', 'foot-left', 'foot-right', 'arm-left', 'arm-right', 'plume-large', 'plume-pink', 'plume-side', 'brow-left', 'brow-right', 'eye-left', 'eye-right', 'iris-left', 'iris-right', 'highlight-left', 'highlight-right', 'cheek-left', 'cheek-right', 'mouth', 'tongue']) expect(svg).toContain(`data-plummo-part="${name}"`);
    for (const item of realCatalog.accessories.filter(a => a.slot === 'hand')) {
        const animated = composer.composeAnimatedPlummo('violet', [item.id], realCatalog, realParts, rig, 'rig', true);
        const arm = animated.slice(animated.indexOf('data-plummo-part="arm-right"'));
        expect(arm.indexOf('data-plummo-part="object-back"')).toBeLessThan(arm.indexOf('data-plummo-part="hand-right"'));
        expect(arm.indexOf('data-plummo-part="hand-right"')).toBeLessThan(arm.indexOf('data-plummo-part="object-front"'));
        expect(animated.indexOf('data-plummo-part="arm-right"')).toBeGreaterThan(animated.indexOf('data-plummo-part="face"'));
    }
    const cap = realCatalog.accessories.find(a => a.coversPlumes)!;
    expect(composer.composeAnimatedPlummo('violet', [cap.id], realCatalog, realParts, rig, 'rig', false)).not.toContain('data-plummo-part="plume-large"');
    for (const color of realCatalog.colors) {
        const rendered = composer.composeAnimatedPlummo(color.id, [], realCatalog, realParts, rig, color.id, false);
        expect(rendered).toContain(color.color);
        expect(rendered).toContain(`url(#${color.id}-plummo-tone)`);
        expect(rendered).not.toContain('url(#plummo-tone)');
    }
});
