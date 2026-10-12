import { chromium } from '@playwright/test';
import fs from 'node:fs';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
const root = process.cwd();
const cache = root + '/node_modules/.cache/plummo-viewport';
const browser = await chromium.launch({ headless: true });
const page = await browser.newPage();
page.setDefaultTimeout(5000);
const errors = [];
page.on('console', (m) => {
    if (m.type() === 'error') errors.push(m.text());
});
await page.route('https://offline.local/**', async (r) => {
    if (r.request().url() === 'https://offline.local/') {
        await r.fulfill({ contentType: 'text/html', body: '<div></div>' });
        return;
    }
    const path = root + '/public' + new URL(r.request().url()).pathname;
    try {
        await r.fulfill({
            body: fs.readFileSync(path),
            contentType: path.endsWith('.svg') ? 'image/svg+xml' : 'font/ttf',
        });
    } catch {
        await r.abort();
    }
});
page.on('pageerror', (e) => {
    errors.push(e.message);
    console.error(e.message);
});
await page.goto('https://offline.local/');
const css = fs.readFileSync(cache + '/plummo.css', 'utf8'),
    js = fs.readFileSync(cache + '/layout.js', 'utf8');
const translations = JSON.parse(
    execFileSync(
        process.env.PHP_BINARY || 'php',
        [
            '-r',
            `echo json_encode(['rooms'=>require '${root}/lang/en/rooms.php','interface'=>require '${root}/lang/en/interface.php']);`,
        ],
        { encoding: 'utf8' },
    ),
);
const bonuses = {
    enabled: true,
    inventory: [
        { id: '1', kind: 'shield' },
        { id: '2', kind: 'bolt' },
    ],
    pending: { id: '3', kind: 'soap' },
    effects: [],
    canUse: true,
    receipts: [],
    launches: [],
};
const base = {
    id: 1,
    settings: { rounds: 5, duration: 60 },
    scores: {},
    exhausted: false,
    targetReached: false,
    deadline: 145,
    bonuses,
    round: { number: 1, total: 5, awards: {} },
    me: { eligible: true, answered: false, choice: null, points: 0 },
};
const question =
    'Quel personnage accompagne les voyageurs dans cette aventure et les aide à retrouver leur chemin à travers la forêt ?';
const games = {
    quiz: {
        ...base,
        type: 'quiz',
        phase: 'answer',
        round: {
            ...base.round,
            question,
            audio: null,
            choices: Array(4).fill(
                'Un petit personnage qui accompagne les voyageurs et les aide à retrouver leur chemin.',
            ),
            correct: null,
        },
    },
    blind: {
        ...base,
        type: 'blind_test',
        phase: 'answer',
        round: {
            ...base.round,
            question: 'Écoute et reconnais la chanson',
            audio: null,
            choices: Array(8).fill('Une chanson très connue'),
            correct: null,
        },
    },
    drawing: {
        ...base,
        type: 'drawing',
        phase: 'drawing',
        round: {
            ...base.round,
            tour: 1,
            word: null,
            artistId: 1,
            canvas: [],
            canvasHeight: 1000,
            revision: 1,
        },
        me: {
            eligible: true,
            canDraw: true,
            word: 'Chocolat',
            canGuess: false,
            found: false,
            canSkip: false,
        },
    },
    phrase: {
        ...base,
        type: 'phrase',
        phase: 'writing',
        round: {
            ...base.round,
            prompt: 'Le serveur nous a expliqué que le plat du jour était tellement exceptionnel que',
            entries: [],
        },
        me: { eligible: true, draft: '', submitted: false },
    },
    mini: {
        ...base,
        type: 'mini_games',
        phase: 'answer',
        round: {
            ...base.round,
            trial: 'hide',
            question:
                'Trouve le Plummo vert avec cet accessoire : Tricorne pirate.',
            choices: Array.from({ length: 12 }, (_, i) => String(i + 1)),
            correct: null,
            scene: null,
            scenePhase: 'answer',
            observeDuration: 0,
            elapsed: 0,
        },
        me: { ...base.me, authorNumber: null },
    },
};
games.phraseVoting = {
    ...games.phrase,
    phase: 'voting',
    round: {
        ...games.phrase.round,
        entries: [1, 2, 3].map((id) => ({
            id: String(id),
            text: 'Pour retrouver leur chemin dans la forêt enchantée, les voyageurs demandent au petit personnage de dessiner une carte avec tous les arbres et tous les chemins.',
        })),
    },
    me: { eligible: true, voted: false, ownEntry: '1', choice: null },
};
games.tribunal = {
    ...base,
    type: 'tribunal',
    phase: 'speaking_first',
    round: {
        ...base.round,
        tour: 1,
        question:
            'Faut-il interdire les chaussettes dépareillées dans les réunions très sérieuses ?',
        advocates: [1, 2],
        speakerId: 1,
        counts: [0, 0],
        votes: {},
        canceled: false,
    },
    me: { eligible: true, canSpeak: true, canVote: false, answered: false },
};
games.tribunalVoting = {
    ...games.tribunal,
    phase: 'voting',
    round: { ...games.tribunal.round, speakerId: null },
    me: { eligible: true, canSpeak: false, canVote: true, answered: false },
};
games.countReveal = {
    ...games.mini,
    phase: 'reveal',
    round: {
        ...games.mini.round,
        trial: 'count',
        question: 'Combien de Plummos sont restés dans la maison ?',
        choices: Array.from({ length: 9 }, (_, i) => String(i)),
        correct: 8,
        scenePhase: 'reveal',
        scene: { events: [], residents: Array(8).fill('violet') },
    },
    me: { ...base.me, answered: true, choice: 8 },
};
const formats = [
    [320, 568],
    [360, 640],
    [375, 667],
    [390, 664],
    [430, 740],
    [667, 375],
    [844, 390],
    [768, 1024],
    [1280, 720],
    [1920, 1080],
];
const results = [];
for (const [width, height] of formats)
    for (const [mode, game] of Object.entries(games)) {
        await page.setViewportSize({ width, height });
        await page.setContent(
            '<base href="https://offline.local"><div id="app"></div>',
        );
        await page.addStyleTag({ content: css });
        await page.evaluate((t) => (window.translations = t), translations);
        await page.evaluate(
            () => (window.process = { env: { NODE_ENV: 'production' } }),
        );
        await page.addScriptTag({ content: js });
        await page.evaluate(
            ({ game, phone }) => window.mountLayout(game, phone),
            { game, phone: width < 1000 },
        );
        await page.waitForTimeout(100);
        const geometry = await page.evaluate(() => {
            const canvas = document
                .querySelector('[data-testid="drawing-board"]')
                ?.getBoundingClientRect();
            const space = document
                .querySelector('.drawing-space')
                ?.getBoundingClientRect();
            return {
                drawingSpace: space
                    ? { width: space.width, height: space.height }
                    : null,
                canvas: canvas
                    ? { width: canvas.width, height: canvas.height }
                    : null,
                overflow:
                    document.documentElement.scrollHeight > innerHeight + 1 ||
                    document.documentElement.scrollWidth > innerWidth + 1,
                outside: [
                    ...document.querySelectorAll(
                        'button,input,textarea,.game-choice',
                    ),
                ]
                    .filter((e) => {
                        const r = e.getBoundingClientRect();
                        return (
                            r.width &&
                            r.height &&
                            (r.top < 0 ||
                                r.bottom > innerHeight + 1 ||
                                r.left < 0 ||
                                r.right > innerWidth + 1)
                        );
                    })
                    .map((e) => e.className),
            };
        });
        results.push({ width, height, mode, ...geometry });
        assert.equal(
            geometry.overflow,
            false,
            `${mode} ${width}x${height}: page overflow`,
        );
        assert.deepEqual(
            geometry.outside,
            [],
            `${mode} ${width}x${height}: controls outside viewport`,
        );
        if (mode === 'countReveal' && width >= 1000) {
            const residents = page.locator('.mini-house-residents');
            assert.equal(await residents.locator('svg').count(), 8);
            const layers = await page.evaluate(() => ({
                residents: Number(
                    getComputedStyle(
                        document.querySelector('.mini-house-residents'),
                    ).zIndex,
                ),
                house: Number(
                    getComputedStyle(document.querySelector('.mini-house'))
                        .zIndex,
                ),
            }));
            assert.ok(
                layers.residents > layers.house,
                'House masks revealed residents',
            );
        }
        if (mode === 'drawing') {
            assert.ok(
                Math.abs(geometry.canvas.width - geometry.canvas.height) < 2,
                'Square drawing proportions must match the TV',
            );
        }
        if (mode === 'drawing' && width < 1000) {
            assert.ok(
                geometry.canvas.height >=
                    Math.min(
                        geometry.drawingSpace.width,
                        geometry.drawingSpace.height,
                    ) -
                        2,
                `Drawing should use available height: ${width}x${height}`,
            );
            assert.ok(
                geometry.canvas.height >= (width < height ? 170 : 100),
                `Drawing canvas too short: ${width}x${height}`,
            );
            const tools = await page
                .locator('.drawing-toolbar button')
                .evaluateAll((buttons) =>
                    buttons.map((b) => {
                        const r = b.getBoundingClientRect();
                        return { x: r.x, y: r.y, w: r.width, h: r.height };
                    }),
                );
            assert.equal(tools.length, 5);
            for (let i = 0; i < tools.length; i++) {
                assert.ok(
                    tools[i].w >= 40 && tools[i].h >= 40,
                    'Toolbar touch target too small',
                );
                for (const other of tools.slice(i + 1))
                    assert.ok(
                        tools[i].x + tools[i].w <= other.x + 1 ||
                            other.x + other.w <= tools[i].x + 1 ||
                            tools[i].y + tools[i].h <= other.y + 1 ||
                            other.y + other.h <= tools[i].y + 1,
                        'Drawing toolbar overlap',
                    );
            }
            await page.locator('[data-drawing-settings]').click();
            await page.locator('[data-drawing-color="#ffffff"]').click();
            assert.equal(
                await page
                    .locator('[data-drawing-color="#ffffff"]')
                    .getAttribute('aria-pressed'),
                'true',
            );
            await page
                .locator('.drawing-settings-drawer [data-slot="drawer-close"]')
                .click();
        }
        if (mode === 'quiz' && width < 1000) {
            const colors = await page
                .locator('.game-choice')
                .evaluateAll((cards) =>
                    Object.fromEntries(
                        cards.map((card) => [
                            card.dataset.answer,
                            {
                                color: card.dataset.color,
                                background:
                                    getComputedStyle(card).backgroundColor,
                            },
                        ]),
                    ),
                );
            const dice = {
                id: 'dice-test',
                kind: 'dice',
                startedAt: 100,
                expiresAt: 110,
            };
            await page.evaluate((g) => window.updateGame(g), {
                ...game,
                bonuses: { ...bonuses, effects: [dice] },
            });
            for (const now of [100, 102, 104]) {
                await page.evaluate((now) => window.setServerNow(now), now);
                await page.waitForTimeout(450);
                const order = await page
                    .locator('.game-choice')
                    .evaluateAll((cards) =>
                        cards.map((card) => card.dataset.answer),
                    );
                assert.notDeepEqual(
                    order,
                    ['0', '1', '2', '3'],
                    'Dice did not move cards',
                );
                const movedColors = await page
                    .locator('.game-choice')
                    .evaluateAll((cards) =>
                        Object.fromEntries(
                            cards.map((card) => [
                                card.dataset.answer,
                                {
                                    color: card.dataset.color,
                                    background:
                                        getComputedStyle(card).backgroundColor,
                                },
                            ]),
                        ),
                    );
                assert.deepEqual(
                    movedColors,
                    colors,
                    'Dice changed answer colors',
                );
            }
            await page.locator('.text-reader-trigger').last().click();
            assert.ok(
                await page.locator('.reader-text').isVisible(),
                'Full answer reader inaccessible',
            );
            await page
                .locator('.game-drawer [data-slot="drawer-close"]')
                .click();
        }

        if (process.env.VIEWPORT_SCREENSHOTS)
            await page.screenshot({
                path: cache + `/${mode}-${width}x${height}.png`,
            });
        await page.evaluate(() => window.unmountLayout());
    }
await browser.close();
fs.writeFileSync(cache + '/results.json', JSON.stringify(results, null, 2));
assert.deepEqual(errors, [], 'Vue rendering or console errors');
console.log(
    `${results.length} viewport checks passed across ${formats.length} screen formats, including drawing tools, answer readers and stable dice colors.`,
);
