import { test, expect } from '@playwright/test';

test.describe('Skip to Main Content Link', () => {
  test('skip link exists on all pages', async ({ page }) => {
    const pages = ['/', '/research', '/blog', '/press'];

    for (const path of pages) {
      await page.goto(path);

      const skipLink = page.locator('a.skip-link');
      await expect(skipLink).toHaveCount(1);
      await expect(skipLink).toHaveText('Skip to main content');
      await expect(skipLink).toHaveAttribute('href', '#main-content');
    }
  });

  test('skip link is visually hidden by default', async ({ page }) => {
    await page.goto('/');

    const skipLink = page.locator('a.skip-link');
    const box = await skipLink.boundingBox();

    // Skip link should be positioned off-screen (top: -40px)
    // boundingBox returns null or has negative y-coordinate when off-screen
    expect(box).toBeTruthy();
    if (box) {
      expect(box.y).toBeLessThan(0);
    }
  });

  test('skip link becomes visible on focus', async ({ page, browserName }) => {
    test.skip(browserName === 'webkit', 'Webkit does not reliably trigger :focus styles on programmatic .focus()');

    await page.goto('/');

    const skipLink = page.locator('a.skip-link');

    // Focus the skip link (simulates Tab key)
    await skipLink.focus();

    // Wait for transition to complete (CSS has 0.2s transition)
    await page.waitForTimeout(250);

    // After focus, check computed top position is 0 (visible)
    const topPosition = await skipLink.evaluate((el) => {
      const styles = window.getComputedStyle(el);
      return parseInt(styles.top, 10);
    });

    expect(topPosition).toBe(0);
  });

  test('skip link navigates to main content', async ({ page }) => {
    await page.goto('/');

    const skipLink = page.locator('a.skip-link');
    await skipLink.focus();
    await skipLink.click();

    // Verify main content exists and has the correct ID
    const mainContent = page.locator('main#main-content');
    await expect(mainContent).toBeVisible();

    // Verify the URL has the hash
    expect(page.url()).toContain('#main-content');
  });

  test('keyboard navigation - skip link is first focusable element', async ({ page }) => {
    await page.goto('/');

    // Press Tab to focus first element
    await page.keyboard.press('Tab');

    // The focused element should be the skip link
    const focusedElement = await page.evaluate(() => {
      const el = document.activeElement;
      return {
        tagName: el?.tagName,
        className: el?.className,
        text: el?.textContent?.trim()
      };
    });

    expect(focusedElement.tagName).toBe('A');
    expect(focusedElement.className).toContain('skip-link');
    expect(focusedElement.text).toBe('Skip to main content');
  });
});
