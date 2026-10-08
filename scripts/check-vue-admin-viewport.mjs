import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
const base = process.env.PLUMMO_UI_URL ?? 'http://127.0.0.1:8781';
const browser = await chromium.launch({ headless: true });
let checks = 0;
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

const fixtureEnvironment = {
    ...process.env,
    APP_ENV: 'testing',
    DB_DATABASE: 'plummo_testing',
};
const fixtures = JSON.parse(
    execFileSync('php', ['scripts/ui-check-fixtures.php', 'create'], {
        env: fixtureEnvironment,
        encoding: 'utf8',
    }),
);
const sizes = [
    [320, 568],
    [390, 844],
    [844, 390],
    [1366, 768],
    [1920, 1080],
    [390, 400],
];
try {
    for (const locale of ['en', 'fr'])
        for (const [width, height] of sizes) {
            const ctx = await browser.newContext({
                viewport: { width, height },
                locale: locale === 'en' ? 'en-US' : 'fr-FR',
                reducedMotion: 'reduce',
            });
            const page = await ctx.newPage();
            const labels =
                locale === 'en'
                    ? {
                          next: 'Next',
                          login: 'Sign in',
                          edit: 'Edit',
                          cancel: 'Cancel',
                          tag: 'Add tag',
                          pack: 'Add pack',
                      }
                    : {
                          next: 'Suivant',
                          login: 'Se connecter',
                          edit: 'Modifier',
                          cancel: 'Annuler',
                          tag: 'Ajouter le tag',
                          pack: 'Ajouter le pack',
                      };
            await page.goto(base + '/admin/login');
            await fits(page, 'admin-login-email ' + width);
            await page.locator('#admin-email').fill(fixtures.email);
            await page
                .getByRole('button', { name: labels.next, exact: true })
                .click();
            await fits(page, 'admin-login-password');
            await page.locator('#admin-password').fill(fixtures.password);
            await page
                .getByRole('button', { name: labels.login, exact: true })
                .click();
            await page.waitForURL(base + '/admin', { timeout: 5000 });
            await fits(page, 'admin-dashboard');
            for (const route of ['contents', 'tags', 'packs', 'imports']) {
                await page.goto(base + '/admin/' + route);
                await fits(page, 'admin-' + route + ' ' + width);
            }
            await page.goto(base + '/admin/contents/create');
            await fits(page, 'admin-content-type');
            await page
                .getByRole('button', { name: labels.next, exact: true })
                .click();
            await page.locator('textarea:visible').fill('😀'.repeat(250));
            await fits(page, 'admin-content-emoji');
            await page.goto(base + '/admin/tags');
            await page
                .getByRole('button', { name: labels.edit, exact: true })
                .first()
                .click();
            await page
                .getByRole('button', { name: labels.cancel, exact: true })
                .first()
                .click();
            await page
                .getByRole('button', { name: labels.tag, exact: true })
                .first()
                .click();
            assert.equal(await page.locator('#tag-name').inputValue(), '');
            await fits(page, 'admin-tag-form');
            // A cancelled edit must create a new tag, not patch the previous tag. Abort mutation: this regression needs only the request contract.
            await page.route('**/admin/tags*', (r) =>
                r.request().method() === 'GET' ? r.continue() : r.abort(),
            );
            await page.locator('#tag-name').fill('viewport-regression');
            const tagRequest = page.waitForRequest(
                (r) => r.url().includes('/admin/tags') && r.method() !== 'GET',
            );
            await page
                .getByRole('button', { name: labels.tag, exact: true })
                .last()
                .click();
            assert.equal((await tagRequest).method(), 'POST');
            await page.unroute('**/admin/tags*');
            await page.goto(base + '/admin/packs');
            await page
                .getByRole('button', { name: labels.edit, exact: true })
                .first()
                .click();
            await page
                .getByRole('button', { name: labels.cancel, exact: true })
                .first()
                .click();
            await page
                .getByRole('button', { name: labels.pack, exact: true })
                .first()
                .click();
            assert.equal(await page.locator('#pack-name').inputValue(), '');
            await fits(page, 'admin-pack-name');
            await page
                .getByRole('button', { name: labels.next, exact: true })
                .click();
            await fits(page, 'admin-pack-tags');
            await page.route('**/admin/packs*', (r) =>
                r.request().method() === 'GET' ? r.continue() : r.abort(),
            );
            await page.locator('#pack-name').evaluate((e) => {
                e.value = 'viewport-regression';
                e.dispatchEvent(new Event('input', { bubbles: true }));
            });
            const packRequest = page.waitForRequest(
                (r) => r.url().includes('/admin/packs') && r.method() !== 'GET',
            );
            await page
                .getByRole('button', { name: labels.pack, exact: true })
                .last()
                .click();
            assert.equal((await packRequest).method(), 'POST');
            await page.screenshot({ path: `/tmp/plummo-admin-${width}.png` });
            await ctx.close();
            console.log('PASS admin', width, height);
        }
    console.log('Administration geometry/interaction checks:', checks);
} finally {
    await browser.close();
    execFileSync('php', ['scripts/ui-check-fixtures.php', 'cleanup'], {
        env: fixtureEnvironment,
        input: JSON.stringify(fixtures),
    });
}
