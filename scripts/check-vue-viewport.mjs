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
        const setup = document.querySelector('.setup-board');
        if (setup && visible(setup)) {
            for (const pair of [
                ['#game-type', '[data-choose-packs]'],
                ['#game-rounds', '#game-duration'],
            ]) {
                const fields = pair
                    .map((selector) => setup.querySelector(selector))
                    .filter(Boolean);
                if (fields.length !== 2) continue;
                const [a, b] = fields.map((field) => field.getBoundingClientRect());
                if (Math.abs(a.top - b.top) > 1 || Math.abs(a.height - b.height) > 1)
                    issues.push('misaligned setup fields');
            }
            const title = setup.querySelector('h2').getBoundingClientRect();
            const main = setup.querySelector('.setup-main').getBoundingClientRect();
            const launch = setup.querySelector('.setup-launch').getBoundingClientRect();
            if (main.top < title.bottom || main.bottom > launch.top)
                issues.push('overlapping setup regions');
        }
        for (const e of document.querySelectorAll(
            'input:not([type=checkbox]):not([type=radio]):not([type=range]):not([type=file]),select,textarea',
        )) {
            if (!visible(e)) continue;
            const s = getComputedStyle(e);
            if (
                s.backgroundColor !== 'rgb(255, 247, 230)' ||
                s.color !== 'rgb(33, 24, 47)'
            )
                issues.push('field contrast ' + e.id);
        }
        for (const e of document.querySelectorAll(
            '.viewport-shell button,.game-drawer button',
        )) {
            if (visible(e) && getComputedStyle(e).boxShadow === 'none')
                issues.push('missing button depth');
        }
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
            '.game-panel,.setup-main,.setup-details,.paged-items,.deck-stage,.reader-text,.game-drawer,textarea',
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
        controls: 'Controls',
        extend: 'Extend session',
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
        controls: 'Commandes',
        extend: 'Prolonger la session',
    },
};
try {
    // Authenticate a random account present exclusively in plummo_testing before creating any room.
    const probe = await browser.newContext({ locale: 'en-US' });
    const probePage = await probe.newPage();
    await probePage.goto(base + '/admin/login');
    await probePage.locator('#admin-email').fill(fixtures.email);
    if (
        await probePage
            .getByRole('button', { name: 'Next', exact: true })
            .isVisible()
    )
        await probePage
            .getByRole('button', { name: 'Next', exact: true })
            .click();
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
                .getByRole('button', { name: t.controls, exact: true })
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
                    pointTarget: 1000,
                },
                me,
                canChat: true,
                game: null,
                serverTime: Date.now() / 1000,
            };
            await page.route('**/rooms/' + code + '/presence', (r) =>
                r.fulfill({ json: snapshot }),
            );
            let setupPlayers = 8;
            await page.route('**/rooms/' + code + '/game-options', (r) =>
                r.fulfill({
                    json: {
                        packs: Array.from({ length: 30 }, (_, i) => ({
                            id: i + 1,
                            name: 'Long pack name '.repeat(6),
                        })),
                        connectedPlayers: setupPlayers,
                        distinctSongs: 8,
                        availability: { total: 100, unseen: 100 },
                    },
                }),
            );
            await page.waitForTimeout(2100);
            await page.locator('#game-type').selectOption('blind_test');
            for (const type of ['quiz', 'blind_test', 'drawing', 'phrase']) {
                await page.locator('#game-type').selectOption(type);
                await fits(page, 'setup-' + type);
                assert.equal(await page.locator('#game-rounds').isVisible(), true);
                assert.equal(await page.locator('#game-duration').isVisible(), true);
                assert.equal(await page.locator('.setup-board .page-controls').count(), 0);
                await page.locator('[data-choose-packs]').click();
                await fits(page, 'setup-pack-drawer-' + type);
                await page.locator('.setup-pack-drawer input[type=checkbox]').first().check();
                await page.keyboard.press('Escape');
                await fits(page, 'setup-selected-' + type);
                if (process.env.PLUMMO_UI_SETUP_ONLY) {
                    await page.screenshot({
                        path: `/tmp/plummo-setup-${locale}-${type}-${width}-${height}.png`,
                    });
                }
            }
            await page.screenshot({ path: `/tmp/plummo-setup-${locale}-${width}.png` });
            if (process.env.PLUMMO_UI_SETUP_ONLY) {
                setupPlayers = 1;
                for (const type of ['drawing', 'phrase']) {
                    await page.locator('#game-type').selectOption(type);
                    await page.locator('.setup-details [role=status]').waitFor();
                    await fits(page, 'setup-awaiting-players-' + type);
                }
                assert.equal(errors.length, 0, errors.join('\n'));
                console.log('PASS setup', locale, width, height);
                await context.close();
                continue;
            }
            await page
                .getByRole('button', { name: t.controls, exact: true })
                .click();
            await page
                .getByRole('button', { name: t.session, exact: true })
                .click();
            await fits(page, 'session');
            await page.screenshot({path: `/tmp/plummo-session-${locale}-${width}.png`});
            assert.equal(
                await page.locator('.session-board .page-controls').count(),
                0,
            );
            await page.locator('[data-session-extend]').click();
            await fits(page, 'session-extend');
            await page
                .getByRole('button', { name: t.close, exact: true })
                .click();
            await page
                .getByRole('button', { name: t.controls, exact: true })
                .click();
            await page
                .getByRole('button', { name: t.ranking, exact: true })
                .click();
            await fits(page, 'ranking-30');
            await page
                .getByRole('button', { name: t.controls, exact: true })
                .click();
            await page
                .locator('.command-drawer').getByRole('button', { name: t.chat, exact: true })
                .click();
            await fits(page, 'chat');

            await page
                .getByRole('button', { name: t.controls, exact: true })
                .click();
            await page
                .getByRole('button', { name: t.more, exact: true })
                .click();
            await fits(page, 'more');
            await page.screenshot({path: `/tmp/plummo-salon-${locale}-${width}.png`});
            assert.equal(
                await page
                    .locator('.room-command-board .page-controls')
                    .count(),
                0,
            );
            assert.equal(await page.locator('.phone-tabs').count(), 0);
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
            snapshot.game.round.question = 'Short question';
            snapshot.game.round.choices = Array.from({length: 8}, (_, i) => 'Answer ' + i);
            await page.waitForTimeout(2100);
            await page.screenshot({path: `/tmp/plummo-answer-${locale}-${width}.png`});
            assert.equal(await page.locator('.game-play .text-reader-trigger').count(), 0);
            assert.equal(await page.locator('.answer-texture').count(), 8);
            const answerColors = await page.locator('.choice-face').evaluateAll(elements => elements.map(e => getComputedStyle(e).backgroundColor));
            assert.deepEqual(answerColors.slice(0, 4), ['rgb(239, 230, 129)', 'rgb(241, 181, 200)', 'rgb(183, 225, 195)', 'rgb(170, 131, 236)']);
            assert.equal(new Set(answerColors.filter((_, i) => i % 2 === 0)).size, 4);
            assert.equal(new Set(answerColors.filter((_, i) => i % 2 === 1)).size, 4);
            const faceStyles = await page.locator('.choice-face').first().evaluate(e => {
                const face = getComputedStyle(e), wrapper = getComputedStyle(e.parentElement);
                return {align: face.textAlign, justify: face.justifyContent, wrapper: wrapper.backgroundColor};
            });
            assert.deepEqual(faceStyles, {align: 'center', justify: 'center', wrapper: 'rgba(0, 0, 0, 0)'});
            await page.route('**/rooms/' + code + '/presence', async r => {
                await new Promise(resolve => setTimeout(resolve, 500));
                await r.fulfill({json: snapshot});
            });
            await page.evaluate(() => {
                window.disabledFlashes = 0;
                window.answerObserver = new MutationObserver(records => {
                    for (const record of records) if(record.attributeName === 'disabled' && record.target.disabled) window.disabledFlashes++;
                });
                document.querySelectorAll('.choice-face').forEach(e => window.answerObserver.observe(e, {attributes: true}));
            });
            await page.waitForTimeout(5300);
            assert.equal(await page.evaluate(() => {window.answerObserver.disconnect(); return window.disabledFlashes;}), 0, 'presence must never flash disabled answers');
            await page.locator('.message-shortcut').click();
            await fits(page, 'message-shortcut-action-pending');
            await page.getByRole('button', {name: t.play, exact: true}).last().click();
            snapshot.game.phase = 'resuming';
            snapshot.game.deadline = Date.now() / 1000 + 15;
            await page.waitForTimeout(2600);
            await fits(page, 'large-resume-countdown');
            assert.equal(await page.locator('.resume-count').count(), 1);
            await page.screenshot({path: `/tmp/plummo-resume-${locale}-${width}.png`});
            snapshot.game.phase = 'answer';
            snapshot.game.deadline = Date.now() / 1000 + 60;
            await page.waitForTimeout(2600);
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
            await page.locator('.podium-place').first().waitFor({state: 'visible', timeout: 10000});
            await fits(page, 'eight-winners');
            assert.equal(await page.locator('.podium-place').count(), 3);
            const steps = await page.locator('.podium-step').evaluateAll(elements => elements.map(e => {
                const r = e.getBoundingClientRect();
                return {top: r.top, bottom: r.bottom};
            }));
            assert.ok(steps[0].top < steps[1].top && steps[1].top < steps[2].top, 'podium must have three distinct levels');
            assert.ok(steps.every(step => Math.abs(step.bottom - steps[0].bottom) < 1), 'podium steps must share a baseline');
            await page.screenshot({path: `/tmp/plummo-podium-${locale}-${width}.png`});
            const display = await context.newPage();
            await display.route('**/rooms/' + code + '/screen-presence', route => route.fulfill({json: {...snapshot, me: null, game: {...snapshot.game, me: null}}}));
            await display.goto(base + '/screen/' + code);
            await display.locator('.podium-place').first().waitFor({state: 'visible', timeout: 10000});
            await fits(display, 'passive-display-results');
            assert.equal(await display.locator('.podium-place').count(), 3);
            assert.equal(await display.locator('button,a,input,select,textarea,.page-controls,.text-reader-trigger').count(), 0);
            assert.equal(await display.locator('.game-panel').first().evaluate(e => getComputedStyle(e).backgroundColor), 'rgba(0, 0, 0, 0)');
            assert.equal(await display.locator('.display-decor').count(), 1);
            await display.screenshot({path: `/tmp/plummo-display-${locale}-${width}.png`});
            await display.close();
            snapshot.game.scores = {[me.id]: 100};
            snapshot.game.targetReached = false;
            await page.waitForTimeout(2600);
            await fits(page, 'solo-podium');
            assert.equal(await page.locator('.podium-place').count(), 3);
            assert.equal(await page.locator('[data-testid=winner-avatar]').count(), 1);
            assert.equal(await page.locator('.game-play .page-controls').count(), 0);
            await page.screenshot({path: `/tmp/plummo-solo-${locale}-${width}.png`});
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
