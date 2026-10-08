import { chromium, expect } from '@playwright/test';
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
await page.goto(process.env.MOCKUPS_URL ?? 'http://127.0.0.1:8766/maquettes/');
await page.evaluate(() => document.fonts.ready);
await page.evaluate(() => window.mockups.setScene('phone', 'blind', true));
await expect(page.locator('.answer')).toHaveCount(8);
await page.locator('.read-small').first().click();
const joined = await page.evaluate(() =>
    window.mockups.state.reader.pages.join(''),
);
if (joined.length !== 402)
    throw Error(`Blind reader lost text: ${joined.length}`);
await page.getByRole('button', { name: 'Fermer', exact: true }).click();
await page.locator('.answer').nth(4).click();
await expect(page.locator('h1')).toHaveText('C’est envoyé !');
await page.evaluate(() => window.mockups.setScene('phone', 'vote', true));
await expect(page.locator('.answer').first()).toBeDisabled();
await page.locator('.read-small').first().click();
if (
    (await page.evaluate(
        () => window.mockups.state.reader.pages.join('').length,
    )) !== 391
)
    throw Error('Phrase text lost');
await page.evaluate(() => window.mockups.setScene('phone', 'write'));
await page.locator('textarea').fill('A'.repeat(60));
await page.locator('[data-action="editPage:1"]').click();
await page.locator('textarea').fill('B'.repeat(60));
await page.locator('[data-action="editPage:1"]').click();
await page.locator('textarea').fill('C'.repeat(30));
if (
    (await page.evaluate(() => window.mockups.state.draft.phrase)) !==
    'A'.repeat(60) + 'B'.repeat(60) + 'C'.repeat(30)
)
    throw Error('Paged draft lost text');
await page.locator('[data-action="editPage:-1"]').click();
await page.locator('[data-action="editPage:-1"]').click();
await page.locator('textarea').fill('A'.repeat(50));
await page.locator('textarea').pressSequentially('XY');
if (
    (await page.evaluate(() => window.mockups.state.draft.phrase)) !==
    'A'.repeat(50) + 'XY' + 'B'.repeat(60) + 'C'.repeat(30)
)
    throw Error('Editing deleted the following pages');
await page.evaluate(() => window.mockups.setScene('phone', 'drawing'));
const c = await page.locator('canvas').boundingBox();
await page.mouse.move(c.x + 20, c.y + 20);
await page.mouse.down();
await page.mouse.move(c.x + 90, c.y + 80);
await page.mouse.up();
if ((await page.evaluate(() => window.mockups.state.strokes.length)) !== 1)
    throw Error('Drawing missing');
await page.locator('[data-action=undo]').click();
if ((await page.evaluate(() => window.mockups.state.strokes.length)) !== 0)
    throw Error('Undo failed');
await page.evaluate(() => window.mockups.setScene('phone', 'sent'));
await page.locator('[name=chat]').fill('Bonjour les Plummos !');
await page.locator('[data-action=chat]').click();
await expect(page.locator('.bubble')).toHaveText('Bonjour les Plummos !');
await page.locator('[name=chat]').focus();
await page.locator('[name=chat]').evaluate((el) => el.setSelectionRange(4, 4));
await page.setViewportSize({ width: 390, height: 400 });
await page.waitForTimeout(180);
if ((await page.evaluate(() => document.activeElement.name)) !== 'chat')
    throw Error('Keyboard resize lost focus');
if (
    (await page.locator('[name=chat]').evaluate((el) => el.selectionStart)) !==
    4
)
    throw Error('Keyboard resize lost caret');
await page.setViewportSize({ width: 390, height: 844 });
await page.waitForTimeout(180);
await page.evaluate(() => window.mockups.setScene('admin', 'content'));
await page.locator('[name=adminType]').selectOption('blind');
await expect(page.locator('[name=songTitle]')).toBeVisible();
await page.evaluate(() => window.mockups.setScene('tv', 'quiz', true));
await page.locator('[data-action=readQuestion]').focus();
await page.locator('[data-action=readQuestion]').click();
if ((await page.evaluate(() => document.activeElement.tagName)) !== 'H1')
    throw Error('Reader did not receive focus');
if (
    (await page.evaluate(
        () => window.mockups.state.reader.pages.join('').length,
    )) !== 500
)
    throw Error('Question text lost');
await page.locator('[data-action=readerClose]').click();
if (
    (await page.evaluate(() => document.activeElement.dataset.action)) !==
    'readQuestion'
)
    throw Error('Reader failed to restore trigger focus');
console.log(
    'PASS: 8 simultaneous music choices, definitive answer, own vote blocked, integral 402/391/500-char readers, 150-char paged draft, drawing/undo, chat and admin types.',
);
await browser.close();
