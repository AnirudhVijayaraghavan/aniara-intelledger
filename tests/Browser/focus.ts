import { expect, test } from '@playwright/test';
import type { Locator } from '@playwright/test';

/** Measure the rendered outer focus ring against its surrounding surface. */
export async function expectFocusRing(control: Locator): Promise<void> {
    await expect(control).toBeFocused();
    expect(
        await control.evaluate((element) => element.matches(':focus-visible')),
    ).toBe(true);
    const result = await control.evaluate((element) => {
        const style = getComputedStyle(element);
        const ring = style.getPropertyValue('--tw-ring-color').trim();
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = 1;
        const context = canvas.getContext('2d');
        if (!context || !ring)
            throw new Error('Cannot measure the focus ring.');
        const rgba = (color: string) => {
            if (!CSS.supports('color', color))
                throw new Error('Unsupported computed focus color.');
            context.clearRect(0, 0, 1, 1);
            context.fillStyle = color;
            context.fillRect(0, 0, 1, 1);
            return Array.from(context.getImageData(0, 0, 1, 1).data);
        };
        const blend = (top: number[], bottom: number[]) =>
            [0, 1, 2].map(
                (index) =>
                    (top[index] * top[3]) / 255 +
                    bottom[index] * (1 - top[3] / 255),
            );
        const layers: number[][] = [];
        for (
            let parent = element.parentElement;
            parent;
            parent = parent.parentElement
        )
            layers.push(rgba(getComputedStyle(parent).backgroundColor));
        let background = [255, 255, 255];
        for (const layer of layers.reverse())
            background = blend(layer, background);
        const foreground = blend(rgba(ring), background);
        const luminance = (channels: number[]) =>
            channels
                .map((channel) => {
                    const value = channel / 255;
                    return value <= 0.04045
                        ? value / 12.92
                        : ((value + 0.055) / 1.055) ** 2.4;
                })
                .reduce(
                    (total, value, index) =>
                        total + value * [0.2126, 0.7152, 0.0722][index],
                    0,
                );
        const a = luminance(foreground),
            b = luminance(background);
        return {
            shadow: style.boxShadow,
            contrast: (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05),
        };
    });
    expect(result.shadow).not.toBe('none');
    expect(
        result.contrast,
        'Rendered focus ring must have at least 3:1 contrast',
    ).toBeGreaterThanOrEqual(3);
    test.info().annotations.push({
        type: 'focus-contrast',
        description: JSON.stringify(result),
    });
}
