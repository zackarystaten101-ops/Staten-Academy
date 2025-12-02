import { test, expect } from '@playwright/test';

const BASE = 'http://localhost/Staten%20Academy%20Webpage/demo-menu.html';

test.describe('Icon-only header mode', () => {
  test('hides labels and compacts buttons at 1400px', async ({ page }) => {
    await page.goto(BASE);
    // Set viewport to large width where icon-only should apply
    await page.setViewportSize({ width: 1400, height: 900 });

    // Wait for header to render
    const nav = page.locator('.site-nav');
    await expect(nav).toBeVisible();

    // nav-label elements should be hidden (display: none or not visible)
    const labels = page.locator('.nav-label');
    await expect(labels).toHaveCount(3);
    for (const label of await labels.elementHandles()) {
      const visible = await label.isVisible();
      // If the label is present but hidden by CSS it will not be visible
      expect(visible).toBeFalsy();
    }

    // Check button size (approx). Use bounding box for first button
    const btn = page.locator('.nav-btn').first();
    const box = await btn.boundingBox();
    // boundingBox can be null in headless in some conditions; guard that
    expect(box).not.toBeNull();
    if (box) {
      // width should be close to 44 (allow some tolerance for borders/margins)
      expect(box.width).toBeGreaterThan(36);
      expect(box.width).toBeLessThan(60);
      expect(box.height).toBeGreaterThan(36);
      expect(box.height).toBeLessThan(60);
    }
  });
});
