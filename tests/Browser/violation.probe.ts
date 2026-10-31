import { expectAccessible, test } from './accessibility';

test('intentional unnamed button must fail the real WCAG assertion', async ({
    page,
}) => {
    await page.evaluate(() => {
        const button = document.createElement('button');
        button.id = 'intentional-unnamed-button';
        document.querySelector('main')?.append(button);
    });
    await expectAccessible(page);
});
