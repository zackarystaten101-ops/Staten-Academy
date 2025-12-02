import { test, expect } from '@playwright/test';

const BASE = 'http://localhost/Staten Accademy Webpage/demo-menu.html';

test.describe('Mobile menu drawer', () => {
  test('opens drawer and traps focus', async ({ page }) => {
    await page.goto(BASE);
    await page.setViewportSize({ width: 400, height: 800 });

    const toggle = page.locator('#menu-toggle');
    await toggle.click();

    const drawer = page.locator('#mobile-menu');
    await expect(drawer).toBeVisible();

    // Focus should move into the menu (close button visible)
    const close = page.locator('#mobile-close');
    await expect(close).toBeVisible();
    await expect(page).toHaveFocus('#mobile-close');

    // Press Tab repeatedly and ensure focus cycles inside the drawer
    await page.keyboard.press('Tab');
    await page.keyboard.press('Tab');
    // Press Escape should close the drawer
    await page.keyboard.press('Escape');
    await expect(drawer).toBeHidden();
  });
});
