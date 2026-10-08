import { chromium } from '@playwright/test';
import fs from 'node:fs';
const browser = await chromium.launch({ headless: true });
const page = await browser.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));
await page.goto(process.env.MOCKUPS_URL ?? 'http://127.0.0.1:8766/maquettes/');
await page.evaluate(() => document.fonts.ready);
const scenes = await page.evaluate(() => window.mockups.scenes);
const findings = [];
let checked = 0;
for (const locale of ['fr', 'en']) {
    await page.evaluate((locale) => {
        window.mockups.state.locale = locale;
    }, locale);
    for (const [width, height] of [
        [1440, 900],
        [1366, 768],
        [390, 844],
        [375, 667],
        [320, 568],
        [844, 390],
        [390, 400],
    ]) {
        await page.setViewportSize({ width, height });
        for (const [surface, list] of Object.entries(scenes))
            for (const scene of list)
                for (const stress of [false, true]) {
                    await page.evaluate(
                        ([surface, scene, stress]) =>
                            window.mockups.setScene(surface, scene, stress),
                        [surface, scene, stress],
                    );
                    await page.waitForTimeout(10);
                    const result = await page.evaluate(() => {
                        const arena = document
                            .querySelector('.arena')
                            .getBoundingClientRect();
                        const issues = [];
                        for (const el of document.querySelectorAll(
                            '.arena > *, .arena button, .arena input, .arena textarea',
                        )) {
                            const r = el.getBoundingClientRect();
                            if (
                                !r.width ||
                                !r.height ||
                                getComputedStyle(el).display === 'none'
                            )
                                continue;
                            if (
                                r.top < arena.top - 2 ||
                                r.bottom > arena.bottom + 2
                            )
                                issues.push(
                                    `${el.tagName}.${el.className} outside arena ${Math.round(r.top - arena.top)}/${Math.round(r.bottom - arena.bottom)}`,
                                );
                            if (r.right > innerWidth + 1 || r.left < -1)
                                issues.push(`${el.tagName} outside width`);
                            if (
                                !el.classList.contains('summary') &&
                                ['BUTTON', 'TEXTAREA', 'INPUT'].includes(
                                    el.tagName,
                                ) &&
                                el.scrollHeight > el.clientHeight + 3
                            )
                                issues.push(
                                    `${el.tagName} text overflow ${el.scrollHeight}/${el.clientHeight}`,
                                );
                        }
                        if (
                            document.documentElement.scrollHeight >
                                innerHeight + 1 ||
                            document.documentElement.scrollWidth >
                                innerWidth + 1
                        )
                            issues.push('document overflow');
                        return [...new Set(issues)];
                    });
                    if (result.length)
                        findings.push({
                            width,
                            height,
                            surface,
                            scene,
                            stress,
                            issues: result,
                        });
                    checked++;
                }
    }
}
const evidence = new URL('../.impeccable/review/', import.meta.url).pathname;
fs.mkdirSync(evidence, { recursive: true });
await page.evaluate(() => {
    window.mockups.state.locale = 'fr';
});
await page.setViewportSize({ width: 1440, height: 900 });
await page.evaluate(() => window.mockups.setScene('tv', 'quiz'));
await page.screenshot({ path: `${evidence}/desktop.png` });
await page.setViewportSize({ width: 390, height: 844 });
await page.evaluate(() => window.mockups.setScene('phone', 'blind'));
await page.screenshot({ path: `${evidence}/mobile.png` });
fs.writeFileSync(
    `${evidence}/geometry.json`,
    JSON.stringify({ checked, errors, findings }, null, 2),
);
console.log(
    JSON.stringify(
        {
            checked,
            errors,
            failures: findings.length,
            examples: findings.slice(0, 18),
        },
        null,
        2,
    ),
);
await browser.close();

if (errors.length || findings.length) process.exitCode = 1;
