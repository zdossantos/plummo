import { chromium } from '@playwright/test';
import { readFileSync } from 'node:fs';
import assert from 'node:assert/strict';

const root = process.cwd();
const cache = root + '/node_modules/.cache/plummo-bonus-presentation';
const sources = JSON.parse(
    readFileSync(root + '/public/audio/sfx/scenes/sources.json', 'utf8'),
);
const credits = readFileSync(
    root + '/public/audio/sfx/scenes/README.md',
    'utf8',
);
const types = readFileSync(root + '/resources/js/types/rooms.ts', 'utf8').split(
    'export type BonusItem',
)[0];
const kinds = [...types.matchAll(/'([a-z_]+)'/g)].map((match) => match[1]);
const browser = await chromium.launch({ headless: true });
try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.route('https://bonuses.local/**', async (route) => {
        const pathname = new URL(route.request().url()).pathname;
        if (pathname === '/') {
            await route.fulfill({
                contentType: 'text/html',
                body: '<style>body{margin:0}button{position:absolute;top:20px;left:20px}</style><button id="underneath">Playable</button><div id="app"></div>',
            });
        } else {
            try {
                await route.fulfill({
                    body: readFileSync(root + '/public' + pathname),
                    contentType: pathname.endsWith('.mp3')
                        ? 'audio/mpeg'
                        : 'image/svg+xml',
                });
            } catch {
                await route.abort();
            }
        }
    });
    await page.goto('https://bonuses.local/');
    await page.addStyleTag({
        content: readFileSync(cache + '/plummo.css', 'utf8'),
    });
    await page.addScriptTag({
        content: readFileSync(cache + '/bonuses.js', 'utf8'),
    });
    const coverage = await page.evaluate(() => ({
        kinds: Object.keys(window.bonusPresentation.bonusScenes),
        files: Object.keys(window.bonusPresentation.bonusScenes).map(
            window.bonusPresentation.bonusSoundFile,
        ),
    }));
    assert.deepEqual(
        coverage.kinds.sort(),
        kinds.sort(),
        'Every BonusKind must explicitly select a scene',
    );
    assert.equal(
        new Set(coverage.files).size,
        kinds.length,
        'Each object needs its own sound',
    );
    assert.deepEqual(
        Object.keys(sources).sort(),
        coverage.files.map((file) => file.split('/').pop()).sort(),
        'No missing or orphaned source recipes',
    );
    for (const file of coverage.files) {
        assert.match(file, /^\/audio\/sfx\/scenes\/[a-z_]+\.mp3$/);
        const recipe = sources[file.split('/').pop()];
        assert.ok(recipe.sources.length > 0, file + ': missing sources');
        for (const source of recipe.sources) {
            for (const field of [
                'path',
                'title',
                'author',
                'url',
                'license_url',
            ])
                assert.ok(
                    typeof source[field] === 'string' && source[field].trim(),
                    file + ': ' + field,
                );
            assert.ok(
                ['CC0-1.0', 'CC-BY-3.0'].includes(source.license),
                file + ': review and support any new license explicitly',
            );
            assert.match(source.sha256, /^[a-f0-9]{64}$/);
            assert.ok(
                credits.includes(source.url) &&
                    credits.includes(source.author) &&
                    credits.includes(source.title) &&
                    credits.includes(source.license_url),
                file + ': missing distributed credit',
            );
        }
    }
    const audio = await page.evaluate(async (files) => {
        const context = new AudioContext();
        try {
            return await Promise.all(
                files.map(async (file) => {
                    const response = await fetch(file);
                    if (!response.ok)
                        throw Error(file + ': HTTP ' + response.status);
                    const buffer = await context.decodeAudioData(
                        await response.arrayBuffer(),
                    );
                    let peak = 0,
                        active = 0;
                    for (
                        let channel = 0;
                        channel < buffer.numberOfChannels;
                        channel++
                    ) {
                        for (const value of buffer.getChannelData(channel)) {
                            if (!Number.isFinite(value))
                                throw Error(file + ': invalid audio');
                            peak = Math.max(peak, Math.abs(value));
                            if (Math.abs(value) > 0.005) active++;
                        }
                    }
                    return {
                        file,
                        duration: buffer.duration,
                        channels: buffer.numberOfChannels,
                        peak,
                        active:
                            active /
                            buffer.sampleRate /
                            buffer.numberOfChannels,
                    };
                }),
            );
        } finally {
            await context.close();
        }
    }, coverage.files);
    for (const sound of audio) {
        assert.ok(
            Math.abs(sound.duration - 2.8) < 0.05,
            sound.file + ': duration',
        );
        assert.equal(sound.channels, 2, sound.file + ': stereo');
        assert.ok(
            sound.peak > 0.01 && sound.peak < 0.9 && sound.active > 0.015,
            sound.file + ': silent or clipping',
        );
    }
    let scenes = 0;
    for (const viewport of [
        { width: 1280, height: 720 },
        { width: 1920, height: 1080 },
    ]) {
        await page.setViewportSize(viewport);
        for (const reducedMotion of ['no-preference', 'reduce']) {
            await page.emulateMedia({ reducedMotion });
            for (const kind of [...kinds, 'shield', 'soap', 'boomerang']) {
                const reaction = scenes % (kinds.length + 3) >= kinds.length;
                const result = await page.evaluate(
                    async ({ kind, reaction }) => {
                        window.mountBonus(kind, reaction);
                        const stage = document.querySelector(
                            '[data-testid="bonus-impact"]',
                        );
                        const normal =
                            stage.querySelector('.bonus-stage-scene');
                        const reduced = stage.querySelector(
                            '.bonus-stage-reduced',
                        );
                        await Promise.all(
                            [...stage.querySelectorAll('img')].map((img) =>
                                img.decode(),
                            ),
                        );
                        const rect = reduced.getBoundingClientRect();
                        return {
                            scene: stage.dataset.scene,
                            normalVisible:
                                getComputedStyle(normal).display !== 'none',
                            reducedVisible:
                                getComputedStyle(reduced).display !== 'none',
                            animations: normal.getAnimations({ subtree: true })
                                .length,
                            reducedAnimations: reduced.getAnimations({
                                subtree: true,
                            }).length,
                            icon: reduced
                                .querySelector('img')
                                .getAttribute('src'),
                            contained:
                                rect.left >= 0 &&
                                rect.top >= 0 &&
                                rect.right <= innerWidth &&
                                rect.bottom <= innerHeight,
                            clickable:
                                document.elementFromPoint(25, 25)?.id ===
                                'underneath',
                            overflow:
                                document.documentElement.scrollWidth >
                                    innerWidth ||
                                document.documentElement.scrollHeight >
                                    innerHeight,
                        };
                    },
                    { kind, reaction },
                );
                const label =
                    kind + ' / ' + reducedMotion + ' / ' + viewport.width;
                assert.ok(result.scene, label + ': no scene');
                assert.ok(
                    result.clickable && !result.overflow,
                    label + ': blocks play or overflows',
                );
                assert.equal(result.icon, '/bonuses/' + kind + '.svg');
                if (reducedMotion === 'reduce') {
                    assert.ok(
                        !result.normalVisible &&
                            result.reducedVisible &&
                            result.contained,
                        label + ': missing static alternative',
                    );
                    assert.equal(
                        result.animations + result.reducedAnimations,
                        0,
                        label + ': still animating',
                    );
                } else {
                    assert.ok(
                        result.normalVisible &&
                            !result.reducedVisible &&
                            result.animations > 1,
                        label + ': missing animated effect',
                    );
                }
                scenes++;
            }
        }
    }
    assert.deepEqual(errors, []);
    console.log(
        `${kinds.length} sons décodés et crédités ; ${scenes} scènes Vue vérifiées (réactions, deux formats et mouvement réduit).`,
    );
} finally {
    await browser.close();
}
