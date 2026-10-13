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
const bluffEntries = Array.from({ length: 9 }, (_, i) => ({
    id: `bluff-${i}`,
    text: 'Une réponse de cent vingt caractères, suffisamment longue pour vérifier la lecture des propositions et leur mise en page.',
    own: i === 8,
    correct: null,
    authors: [],
    voters: [],
}));
games.bluffWriting = {
    ...base,
    type: 'bluff',
    phase: 'writing',
    round: { ...base.round, question: ('Une définition mystérieuse demande une explication inventée et convaincante pour tromper les autres joueurs. ').repeat(5).slice(0, 500), index: 0, entries: [] },
    me: {
        ...base.me,
        draft: '',
        submitted: false,
        foundTruth: false,
        canVote: false,
    },
};
games.bluffPresenting = {
    ...games.bluffWriting,
    phase: 'presenting',
    round: { ...games.bluffWriting.round, entries: bluffEntries },
};
games.bluffVoting = {
    ...games.bluffPresenting,
    phase: 'voting',
    me: { ...games.bluffWriting.me, canVote: true },
};
games.bluffReveal = {
    ...games.bluffVoting,
    phase: 'reveal',
    round: {
        ...games.bluffVoting.round,
        entries: bluffEntries.map((e, i) => ({
            ...e,
            correct: i === 0,
            authors: i === 0 ? [] : [2],
            voters: i === 0 ? [1] : [],
        })),
        awards: { 1: 65 },
    },
    me: { ...games.bluffVoting.me, points: 65 },
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
for (const [width, height] of formats.filter(
    ([w, h]) =>
        !process.env.PLUMMO_VIEWPORT_FORMAT ||
        process.env.PLUMMO_VIEWPORT_FORMAT === `${w}x${h}`,
))
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
        if (width < 600 && height > 450) {
            const hud = await page.evaluate(() => {
                const rect = (selector) => {
                    const r = document
                        .querySelector(selector)
                        ?.getBoundingClientRect();
                    return r
                        ? { left: r.left, top: r.top, bottom: r.bottom }
                        : null;
                };
                const controls = [
                    ...document.querySelectorAll(
                        '.bonus-object,.bonus-pending,.command-trigger,.message-shortcut,.game-pause',
                    ),
                ];
                return {
                    bonus: rect('.bonus-object'),
                    menu: rect('.command-trigger'),
                    pause: rect('.game-pause'),
                    blocked: controls
                        .filter((e) => {
                            const r = e.getBoundingClientRect();
                            const hit = document.elementFromPoint(
                                r.x + r.width / 2,
                                r.y + r.height / 2,
                            );
                            return !hit || !e.contains(hit);
                        })
                        .map((e) => e.className),
                };
            });
            assert.deepEqual(
                hud.blocked,
                [],
                `${mode} ${width}x${height}: blocked HUD controls`,
            );
            assert.ok(
                hud.menu.left < hud.bonus.left,
                `${mode}: commands must be left of bonuses`,
            );
            assert.ok(
                Math.abs(hud.menu.top - hud.bonus.top) < 1,
                `${mode}: HUD top edges must align`,
            );
            if (hud.pause)
                assert.ok(
                    Math.abs(hud.pause.bottom - hud.bonus.bottom) < 1,
                    `${mode}: HUD bottom edges must align`,
                );
        }
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
        if (mode === 'quiz' || mode === 'blind') {
            const geometry = () =>
                page.locator('.game-choice').evaluateAll((cards) =>
                    cards.map((card) => {
                        const r = card.getBoundingClientRect();
                        return {
                            id: card.dataset.answer,
                            x: r.x,
                            y: r.y,
                            width: r.width,
                            height: r.height,
                            color: getComputedStyle(
                                card.querySelector('.choice-face'),
                            ).backgroundColor,
                            pattern:
                                card.querySelector('.answer-texture').innerHTML,
                        };
                    }),
                );
            const baseline = await geometry();
            for (const kind of [
                'squatter',
                'photobomb',
                'singer',
                'strike',
                'bubbles',
                'soap',
            ]) {
                await page.evaluate((g) => window.updateGame(g), {
                    ...game,
                    bonuses: {
                        ...bonuses,
                        effects: [
                            {
                                id: 'geometry-' + kind,
                                kind,
                                actorId: 2,
                                startedAt: 100,
                                expiresAt: 140,
                            },
                        ],
                    },
                });
                await page.waitForTimeout(250);
                if (
                    ['squatter', 'photobomb', 'singer', 'strike'].includes(kind)
                )
                    assert.equal(
                        await page.locator('.bonus-visitor').count(),
                        1,
                        'Visitor effect was not rendered',
                    );
                assert.deepEqual(
                    await geometry(),
                    baseline,
                    kind +
                        ' changed answer geometry at ' +
                        width +
                        'x' +
                        height,
                );
            }
            await page.evaluate((g) => window.updateGame(g), game);
            await page.waitForTimeout(250);
            assert.deepEqual(
                await geometry(),
                baseline,
                'Expiring effects changed answer geometry',
            );
        }
        if (mode === 'quiz' && width >= 1000) {
            await page.evaluate((g) => window.updateGame(g), {
                ...game,
                bonuses: {
                    ...bonuses,
                    effects: [
                        {
                            id: 'display-reading',
                            kind: 'missing_word',
                            actorId: 2,
                            startedAt: 100,
                            expiresAt: 140,
                        },
                    ],
                },
            });
            assert.match(
                await page.locator('.game-question h1').textContent(),
                /▰▰▰▰/,
                'Mange-mot must affect the display too',
            );
            await page.evaluate((g) => window.updateGame(g), game);
        }
        if (mode === 'blind') {
            const identities = () =>
                page.locator('.game-choice').evaluateAll((cards) =>
                    Object.fromEntries(
                        cards.map((card) => [
                            card.dataset.answer,
                            {
                                color: getComputedStyle(
                                    card.querySelector('.choice-face'),
                                ).backgroundColor,
                                pattern:
                                    card.querySelector('.answer-texture')
                                        .innerHTML,
                            },
                        ]),
                    ),
                );
            const before = await identities();
            assert.equal(
                new Set(Object.values(before).map((value) => value.color)).size,
                8,
                'Eight answers must have distinct colors',
            );
            assert.equal(
                new Set(Object.values(before).map((value) => value.pattern))
                    .size,
                8,
                'Eight answers must have distinct SVG patterns',
            );
            await page.evaluate((g) => window.updateGame(g), {
                ...game,
                bonuses: {
                    ...bonuses,
                    effects: [
                        {
                            id: 'eight-dice',
                            kind: 'dice',
                            startedAt: 100,
                            expiresAt: 110,
                        },
                    ],
                },
            });
            for (const now of [100, 102, 104]) {
                await page.evaluate((now) => window.setServerNow(now), now);
                await page.waitForTimeout(450);
                assert.deepEqual(
                    await identities(),
                    before,
                    'Dice changed the color or pattern assigned to an answer',
                );
                assert.notDeepEqual(
                    await page
                        .locator('.game-choice')
                        .evaluateAll((cards) =>
                            cards.map((card) => card.dataset.answer),
                        ),
                    Array.from({ length: 8 }, (_, i) => String(i)),
                    'Dice did not move eight cards',
                );
            }
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

        if (mode.startsWith('bluff')) {
            assert.ok(
                await page.locator('[data-testid="bluff-play"]').isVisible(),
            );
            const expected =
                mode === 'bluffWriting'
                    ? 0
                    : mode === 'bluffPresenting'
                      ? 1
                      : 9;
            assert.equal(
                await page.locator('.bluff-choices .game-choice').count(),
                expected,
            );
            if (mode === 'bluffWriting' && width < 1000) {
                await page.evaluate(() => { window.bluffSlow = true; });
                const field = page.locator('#bluff-draft');
                await field.fill('Un bluff en cours');
                await page.waitForTimeout(450);
                assert.ok(await field.isEnabled(), 'Autosave disabled the editor');
                assert.ok(await field.evaluate((el) => document.activeElement === el), 'Autosave lost keyboard focus');
                await field.press('End');
                await field.press('!');
                await page.waitForTimeout(1400);
                await page.evaluate(() => { window.bluffSlow = false; });
            }
            if (mode === 'bluffVoting') {
                const colors = await page
                    .locator('.bluff-choices .choice-face')
                    .evaluateAll((cards) =>
                        cards.map((c) => getComputedStyle(c).backgroundColor),
                    );
                assert.equal(
                    new Set(colors).size,
                    9,
                    'Nine distinct bluff identities',
                );
                if (width < 1000) {
                    assert.ok(
                        await page
                            .locator('[data-answer="8"] .choice-face')
                            .isDisabled(),
                        'Cannot vote for own bluff',
                    );
                    await page
                        .locator('[data-answer="0"] .choice-face')
                        .click();
                    assert.equal(
                        await page.evaluate(
                            () => window.lastBluffAction?.values.choice,
                        ),
                        'bluff-0',
                    );
                }
            }
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
    `${results.length} viewport checks passed across ${process.env.PLUMMO_VIEWPORT_FORMAT ? 1 : formats.length} screen formats, including drawing tools, answer readers and stable dice colors.`,
);
