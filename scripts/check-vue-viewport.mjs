import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import { execFileSync } from 'node:child_process';
const base = process.env.PLUMMO_UI_URL ?? 'http://127.0.0.1:8781';
const browser = await chromium.launch({ headless: true });
let checks = 0;
const fixtureEnvironment = {
    ...process.env,
    APP_ENV: 'testing',
    DB_DATABASE: 'plummo_testing',
};
const fixtures = JSON.parse(
    execFileSync('php', ['scripts/ui-check-fixtures.php', 'probe'], {
        env: fixtureEnvironment,
        encoding: 'utf8',
    }),
);
fixtures.rooms = [];

async function fits(page, label) {
    await page.waitForTimeout(150);
    const failures = await page.evaluate(() => {
        const visible = (e) => e.checkVisibility() && !e.closest('[inert]');
        const issues = [];
        for (const e of document.querySelectorAll(
            'button,input,select,textarea,a,[role=dialog]',
        )) {
            if (!visible(e)) continue;
            const r = e.getBoundingClientRect();
            if (
                r.width &&
                r.height &&
                (r.left < -1 ||
                    r.top < -1 ||
                    r.right > innerWidth + 1 ||
                    r.bottom > innerHeight + 1)
            )
                issues.push(
                    (
                        e.id ||
                        e.getAttribute('aria-label') ||
                        e.textContent.trim()
                    ).slice(0, 70) +
                        ' ' +
                        JSON.stringify({
                            x: r.x,
                            y: r.y,
                            w: r.width,
                            h: r.height,
                        }),
                );
        }
        for (const e of document.querySelectorAll(
            '.game-panel,.paged-items,.deck-stage,.reader-text,.game-drawer,textarea',
        )) {
            if (!visible(e)) continue;
            if (
                e.scrollHeight > e.clientHeight + 2 ||
                e.scrollWidth > e.clientWidth + 2
            )
                issues.push(
                    'overflow ' +
                        e.className +
                        ' ' +
                        e.scrollHeight +
                        '/' +
                        e.clientHeight +
                        ' ' +
                        e.scrollWidth +
                        '/' +
                        e.clientWidth,
                );
        }
        if (
            document.documentElement.scrollHeight > innerHeight ||
            document.documentElement.scrollWidth > innerWidth
        )
            issues.push('document scroll');
        return issues;
    });
    checks++;
    if (failures.length) {
        await page.screenshot({ path: '/tmp/plummo-vue-failure.png' });
        throw new Error(label + '\n' + failures.join('\n'));
    }
}
const cases = process.env.PLUMMO_UI_CASES
    ? JSON.parse(process.env.PLUMMO_UI_CASES)
    : process.env.PLUMMO_UI_KEYBOARD
      ? [
            [390, 400],
            [320, 400],
        ]
      : process.env.PLUMMO_UI_QUICK
        ? [[844, 390]]
        : [
              [320, 568],
              [390, 844],
              [844, 390],
              [1366, 768],
              [1920, 1080],
          ];
const locales = {
    en: {
        continue: 'Continue',
        enter: 'Enter the room',
        head: 'Head',
        face: 'Face',
        neck: 'Neck',
        hand: 'Hands',
        close: 'Close',
        next: 'Next',
        previous: 'Previous',
        play: 'Play',
        session: 'Session',
        ranking: 'Overall leaderboard',
        chat: 'Bubble',
        more: 'Room',
    },
    fr: {
        continue: 'Continuer',
        enter: 'Entrer dans le salon',
        head: 'Tête',
        face: 'Visage',
        neck: 'Cou',
        hand: 'Mains',
        close: 'Fermer',
        next: 'Suivant',
        previous: 'Précédent',
        play: 'Jouer',
        session: 'Session',
        ranking: 'Classement global',
        chat: 'Bulle',
        more: 'Salon',
    },
};
try {
    // Authenticate a random account present exclusively in plummo_testing before creating any room.
    const probe = await browser.newContext({ locale: 'en-US' });
    const probePage = await probe.newPage();
    await probePage.goto(base + '/admin/login');
    await probePage.locator('#admin-email').fill(fixtures.email);
    await probePage.getByRole('button', { name: 'Next', exact: true }).click();
    await probePage.locator('#admin-password').fill(fixtures.password);
    await probePage
        .getByRole('button', { name: 'Sign in', exact: true })
        .click();
    await probePage.waitForURL(base + '/admin', { timeout: 5000 });
    await probe.close();
    for (const locale of process.env.PLUMMO_UI_LOCALE
        ? [process.env.PLUMMO_UI_LOCALE]
        : ['en', 'fr'])
        for (const [width, height] of cases) {
            const context = await browser.newContext({
                viewport: { width, height },
                locale: locale === 'fr' ? 'fr-FR' : 'en-US',
                reducedMotion: 'reduce',
            });
            const page = await context.newPage();
            const t = locales[locale];
            const errors = [];
            page.on('pageerror', (e) => errors.push(e.message));
            await page.goto(base + '/');
            const code = page.url().split('/').pop();
            fixtures.rooms.push(code);
            await fs.appendFile('/tmp/plummo-ui-rooms', code + '\n');
            await fits(page, `${locale} ${width} screen-lobby`);
            await page.goto(base + '/join/' + code);
            await fits(page, 'profile-name');
            await page.locator('#player-name').fill('Camille');
            await page
                .getByRole('button', { name: t.continue, exact: true })
                .click();
            await fits(page, 'wardrobe');
            for (const slot of ['head', 'face', 'neck', 'hand']) {
                const trigger = page.getByRole('button', {
                    name: t[slot],
                    exact: true,
                });
                await trigger.click();
                await fits(page, 'drawer ' + slot);
                assert.equal(
                    await page
                        .locator('[data-slot=drawer-content][data-state=open]')
                        .count(),
                    1,
                );
                await page
                    .locator('.drawer-accessories button')
                    .first()
                    .click();
                await page.keyboard.press('Escape');
                await assert.doesNotReject(() =>
                    trigger.waitFor({ state: 'visible' }),
                );
                assert.equal(
                    await trigger.evaluate((e) => document.activeElement === e),
                    true,
                    'focus returns to accessory',
                );
            }
            const response = page.waitForResponse(
                (r) =>
                    r.url().endsWith('/players') &&
                    r.request().method() === 'POST',
            );
            await page
                .getByRole('button', { name: t.enter, exact: true })
                .click();
            let snapshot = await (await response).json();
            assert.equal(snapshot.me.accessories.length, 4);
            await page
                .getByRole('button', { name: t.play, exact: true })
                .waitFor();
            const me = snapshot.me;
            const players = Array.from({ length: 8 }, (_, i) => ({
                ...me,
                id: i ? me.id + i : me.id,
                name: ('Player ' + i + ' abcdefghijklmnopqrstuv').slice(0, 30),
                score: i * 65,
                chat: null,
            }));
            snapshot = {
                ...snapshot,
                room: {
                    ...snapshot.room,
                    players,
                    ranking: Array.from({ length: 30 }, (_, i) => ({
                        ...players[i % 8],
                        id: me.id + i,
                        rank: i + 1,
                    })),
                    pointTarget: null,
                },
                me,
                canChat: true,
                game: null,
                serverTime: Date.now() / 1000,
            };
            await page.route('**/rooms/' + code + '/presence', (r) =>
                r.fulfill({ json: snapshot }),
            );
            await page.route('**/rooms/' + code + '/game-options', (r) =>
                r.fulfill({
                    json: {
                        packs: Array.from({ length: 30 }, (_, i) => ({
                            id: i + 1,
                            name: 'Long pack name '.repeat(6),
                        })),
                        connectedPlayers: 8,
                        distinctSongs: 8,
                        availability: { total: 100, unseen: 100 },
                    },
                }),
            );
            await page.waitForTimeout(2100);
            await fits(page, 'setup-type');
            await page
                .getByRole('button', { name: t.continue, exact: true })
                .click();
            await fits(page, 'setup-packs');
            await page
                .getByRole('button', { name: t.continue, exact: true })
                .click();
            await fits(page, 'setup-settings');
            await page
                .getByRole('button', { name: t.session, exact: true })
                .click();
            await fits(page, 'session');
            await page
                .locator('.page-deck')
                .getByRole('button', { name: t.next, exact: true })
                .click();
            await fits(page, 'session-extend');
            await page
                .locator('.page-deck')
                .getByRole('button', { name: t.next, exact: true })
                .click();
            await fits(page, 'session-reset');
            await page
                .getByRole('button', { name: t.ranking, exact: true })
                .click();
            await fits(page, 'ranking-30');
            await page
                .getByRole('button', { name: t.chat, exact: true })
                .click();
            await fits(page, 'chat');
            await page
                .getByRole('button', { name: t.more, exact: true })
                .click();
            await fits(page, 'more');
            const game = {
                id: 1,
                type: 'blind_test',
                phase: 'answer',
                exhausted: false,
                targetReached: false,
                settings: { packs: [1], rounds: 5, duration: 60 },
                deadline: Date.now() / 1000 + 60,
                scores: {},
                round: {
                    number: 1,
                    total: 5,
                    question: 'W'.repeat(80),
                    audio: null,
                    choices: Array.from({ length: 8 }, () => 'W'.repeat(40)),
                    correct: null,
                    awards: {},
                },
                me: {
                    eligible: true,
                    answered: false,
                    choice: null,
                    points: null,
                },
            };
            snapshot.game = game;
            snapshot.canChat = false;
            await page.waitForTimeout(2100);
            await fits(page, 'blind-eight-long');
            assert.equal(await page.locator('.game-choices > *').count(), 8);
            await page
                .locator('.game-choice .text-reader-trigger')
                .first()
                .click();
            await fits(page, 'choice-reader');
            const dialog = page.getByRole('dialog');
            if (
                await dialog
                    .getByRole('button', { name: t.next, exact: true })
                    .count()
            )
                await dialog
                    .getByRole('button', { name: t.next, exact: true })
                    .click();
            await fits(page, 'choice-reader-next');
            await page.keyboard.press('Escape');
            const longQuestion = '😀\n'.repeat(80) + 'W'.repeat(500);
            snapshot.game.round.question = longQuestion;
            await page.waitForTimeout(2100);
            await page.locator('.game-question .text-reader-trigger').click();
            const reader = page.getByRole('dialog');
            let complete = '';
            for (let part = 0; part < 100; part++) {
                await fits(page, 'unicode-reader');
                complete += await reader.locator('.reader-text').textContent();
                const next = reader.getByRole('button', {
                    name: t.next,
                    exact: true,
                });
                if (await next.isDisabled()) break;
                await next.click();
            }
            assert.equal(
                complete,
                longQuestion,
                'reader preserves all Unicode and newlines',
            );
            await page.keyboard.press('Escape');
            snapshot.game = {
                ...game,
                type: 'phrase',
                phase: 'writing',
                round: {
                    number: 1,
                    total: 1,
                    prompt: 'A long phrase prompt '.repeat(10),
                    entries: [],
                    awards: {},
                },
                me: {
                    eligible: true,
                    draft: '',
                    submitted: false,
                    ownEntry: null,
                    voted: false,
                    choice: null,
                    points: null,
                },
            };
            await page.route(
                '**/rooms/' + code + '/phrases/draft',
                async (r) => {
                    snapshot.game.me.draft = r.request().postDataJSON().suffix;
                    await r.fulfill({ json: snapshot });
                },
            );
            await page.waitForTimeout(2100);
            await fits(page, 'phrase-writing');
            await page.locator('#phrase-suffix').fill('😀'.repeat(150));
            await page.waitForTimeout(600);
            assert.equal(snapshot.game.me.draft, '😀'.repeat(150));
            await fits(page, 'phrase-150');
            snapshot.game.round.number = 2;
            snapshot.game.me.draft = '';
            await page.waitForTimeout(2300);
            await page
                .locator('#phrase-suffix')
                .pressSequentially('a'.repeat(60) + 'XYZ');
            await page.waitForTimeout(800);
            assert.equal(
                snapshot.game.me.draft,
                'a'.repeat(60) + 'XYZ',
                'typing preserves order across pages',
            );
            snapshot.game.phase = 'voting';
            snapshot.game.round.entries = Array.from({ length: 8 }, (_, i) => ({
                id: 'entry' + i,
                text: 'Long sentence '.repeat(27),
            }));
            snapshot.game.me.ownEntry = 'entry0';
            await page.waitForTimeout(2100);
            await fits(page, 'phrase-eight-long');
            snapshot.game = {
                ...game,
                type: 'drawing',
                phase: 'drawing',
                round: {
                    number: 1,
                    total: 2,
                    tour: 1,
                    artistId: me.id,
                    word: null,
                    canvas: [],
                    revision: 1,
                    awards: {},
                },
                me: {
                    eligible: true,
                    found: false,
                    near: false,
                    points: 0,
                    words: [],
                    word: 'Elephant',
                    canDraw: true,
                    canSkip: false,
                    votedSkip: false,
                    canGuess: false,
                },
            };
            await page.waitForTimeout(2100);
            await fits(page, 'drawing-tools');
            const ratio = await page
                .locator('[data-testid=drawing-board]')
                .evaluate((e) => {
                    const r = e.getBoundingClientRect();
                    return r.width / r.height;
                });
            if (!(Math.abs(ratio - 5 / 3) < 0.02)) {
                await page.screenshot({
                    path: '/tmp/plummo-drawing-geometry.png',
                });
                console.log(
                    await page
                        .locator('[data-testid=drawing-board]')
                        .evaluate((e) => {
                            const a = [];
                            for (let p = e; p; p = p.parentElement) {
                                const r = p.getBoundingClientRect();
                                a.push({
                                    class: p.className?.baseVal ?? p.className,
                                    w: r.width,
                                    h: r.height,
                                    scroll: p.scrollHeight,
                                    client: p.clientHeight,
                                    style: p.getAttribute('style'),
                                });
                            }
                            return a;
                        }),
                );
            }
            assert.ok(Math.abs(ratio - 5 / 3) < 0.02);
            snapshot.game.phase = 'results';
            snapshot.game.targetReached = true;
            snapshot.game.scores = Object.fromEntries(
                players.map((p) => [p.id, 100]),
            );
            await page.waitForTimeout(2100);
            await fits(page, 'eight-winners');
            assert.deepEqual(errors, []);
            await page.screenshot({
                path: `/tmp/plummo-integrated-${locale}-${width}.png`,
            });
            await context.close();
            console.log('PASS', locale, width, height);
        }
    console.log('Vue geometry/interaction checks:', checks);
} finally {
    await browser.close();
    execFileSync('php', ['scripts/ui-check-fixtures.php', 'cleanup'], {
        env: fixtureEnvironment,
        input: JSON.stringify(fixtures),
    });
}
