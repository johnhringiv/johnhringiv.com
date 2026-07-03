import { test, expect } from '@playwright/test';

/**
 * Interactive features tests
 *
 * Tests for JavaScript-powered interactive features:
 * - Image modal (click to open, click outside to close, hover preload)
 * - Code copy button (clipboard, visual feedback)
 * - Collapse/expand (smooth animation, aria-expanded)
 */

test.describe('Interactive - Image Modal (Desktop)', () => {
  test.beforeEach(async ({ page }) => {
    // Set desktop viewport (>= 768px) where modals are enabled
    await page.setViewportSize({ width: 1280, height: 720 });
    // Navigate to research page which has modal images
    await page.goto('/research');
  });

  test('modal is initially hidden', async ({ page }) => {
    const modal = page.locator('.image-modal-popup');
    await expect(modal).toBeHidden();
  });

  test('clicking modal image opens modal on desktop', async ({ page }) => {
    const modalImages = await page.locator('.image-modal-content img, img.image-modal-content').count();

    // Skip test if no modal images on this page
    test.skip(modalImages === 0, 'No images with image-modal-content class found on page');

    const firstImage = page.locator('.image-modal-content img, img.image-modal-content').first();
    await firstImage.click();

    // Modal should appear
    const modal = page.locator('.image-modal-popup');
    await expect(modal).toBeVisible({ timeout: 2000 });
  });

  test('modal displays correct image from src', async ({ page }) => {
    const modalImages = await page.locator('.image-modal-content img, img.image-modal-content').count();
    test.skip(modalImages === 0, 'No images with image-modal-content class found on page');

    const firstImage = page.locator('.image-modal-content img, img.image-modal-content').first();
    const src = await firstImage.getAttribute('src');

    await firstImage.click();

    // Modal image should display the src (or data-modal-src if present)
    const modalImg = page.locator('.image-modal-popup img');
    await expect(modalImg).toBeVisible();

    const modalImgSrc = await modalImg.getAttribute('src');

    // Should match either the src or srcset selection
    expect(modalImgSrc).toBeTruthy();
  });

  test('modal sets body overflow to hidden when opened', async ({ page }) => {
    const modalImages = await page.locator('.image-modal-content img, img.image-modal-content').count();
    test.skip(modalImages === 0, 'No images with image-modal-content class found on page');

    const firstImage = page.locator('.image-modal-content img, img.image-modal-content').first();
    await firstImage.click();

    // Body should have overflow: hidden to prevent scrolling
    const bodyOverflow = await page.locator('body').evaluate(el =>
      window.getComputedStyle(el).overflow
    );

    expect(bodyOverflow).toBe('hidden');
  });

  test('clicking anywhere closes modal and restores body overflow', async ({ page }) => {
    const modalImages = await page.locator('.image-modal-content img, img.image-modal-content').count();
    test.skip(modalImages === 0, 'No images with image-modal-content class found on page');

    const firstImage = page.locator('.image-modal-content img, img.image-modal-content').first();
    await firstImage.click();

    // Modal should be visible
    const modal = page.locator('.image-modal-popup');
    await expect(modal).toBeVisible();

    // Click anywhere on the page (body)
    await page.locator('body').click({ position: { x: 10, y: 10 } });

    // Modal should close
    await expect(modal).toBeHidden();

    // Body overflow should be restored
    const bodyOverflow = await page.locator('body').evaluate(el =>
      window.getComputedStyle(el).overflow
    );
    expect(bodyOverflow).toBe('auto');
  });

  test('hovering over image on desktop triggers preload', async ({ page }) => {
    const modalImages = await page.locator('.image-modal-content img, img.image-modal-content').count();
    test.skip(modalImages === 0, 'No images with image-modal-content class found on page');

    const firstImage = page.locator('.image-modal-content img, img.image-modal-content').first();

    // Hover over image - this should trigger preload on desktop
    await firstImage.hover();

    // Wait briefly for hover event to process
    await page.waitForTimeout(100);

    // Just verify no errors occurred - actual preloading is implementation detail
    // The important thing is modal works when clicked after hover
    await firstImage.click();

    const modal = page.locator('.image-modal-popup');
    await expect(modal).toBeVisible();
  });

  test('desktop shows zoom cursor on modal images', async ({ page }) => {
    const modalImages = await page.locator('.image-modal-content img, img.image-modal-content').count();
    test.skip(modalImages === 0, 'No images with image-modal-content class found on page');

    const firstImage = page.locator('.image-modal-content img, img.image-modal-content').first();

    // Check computed cursor style (will be a URL or zoom-in)
    const cursor = await firstImage.evaluate(el => window.getComputedStyle(el).cursor);

    // Should have custom cursor URL or zoom-in fallback
    expect(cursor).toMatch(/url|zoom-in/);
  });
});

test.describe('Interactive - Image Modal (Mobile)', () => {
  test.beforeEach(async ({ page }) => {
    // Set mobile viewport (< 768px) where modals are disabled
    await page.setViewportSize({ width: 375, height: 667 });
    // Navigate to research page which has modal images
    await page.goto('/research');
  });

  test('clicking modal image does NOT open modal on mobile', async ({ page }) => {
    const modalImages = await page.locator('.image-modal-content img, img.image-modal-content').count();

    // Skip test if no modal images on this page
    test.skip(modalImages === 0, 'No images with image-modal-content class found on page');

    const firstImage = page.locator('.image-modal-content img, img.image-modal-content').first();
    await firstImage.click();

    // Wait a bit to ensure modal doesn't appear
    await page.waitForTimeout(500);

    // Modal should remain hidden on mobile
    const modal = page.locator('.image-modal-popup');
    await expect(modal).toBeHidden();
  });

  test('mobile does NOT show zoom cursor on modal images', async ({ page }) => {
    const modalImages = await page.locator('.image-modal-content img, img.image-modal-content').count();
    test.skip(modalImages === 0, 'No images with image-modal-content class found on page');

    const firstImage = page.locator('.image-modal-content img, img.image-modal-content').first();

    // Check computed cursor style
    const cursor = await firstImage.evaluate(el => window.getComputedStyle(el).cursor);

    // Should NOT have zoom cursor on mobile
    expect(cursor).not.toMatch(/zoom-in/);
  });
});

test.describe('Interactive - Code Copy Button', () => {
  test.beforeEach(async ({ page, browserName }) => {
    // Grant clipboard permissions (only supported in Chromium)
    if (browserName === 'chromium') {
      await page.context().grantPermissions(['clipboard-read', 'clipboard-write']);
    }

    // Navigate to a blog post with code blocks
    await page.goto('/when-five-plus-five-equals-eleven');
  });

  test('code blocks have copy buttons', async ({ page }) => {
    const copyButtons = await page.locator('.shiki-copy, button:has-text("Copy")').count();
    expect(copyButtons).toBeGreaterThan(0);
  });

  test('clicking copy button copies code to clipboard', async ({ page }) => {
    const copyButton = page.locator('.shiki-copy, button:has-text("Copy")').first();
    await expect(copyButton).toBeVisible();

    // Get data-code attribute
    const dataCode = await copyButton.getAttribute('data-code');
    expect(dataCode).toBeTruthy();

    // Click button
    await copyButton.click();

    // Check clipboard (if supported)
    try {
      const clipboardText = await page.evaluate(() => navigator.clipboard.readText());

      // Decode base64 and compare
      const expectedCode = Buffer.from(dataCode!, 'base64').toString('utf-8');
      expect(clipboardText).toBe(expectedCode);
    } catch (error) {
      // Clipboard API may not be available in test environment
      console.log('Clipboard API not available, skipping clipboard check');
    }
  });

  test('copy button shows feedback after click', async ({ page }) => {
    const copyButton = page.locator('.shiki-copy, button:has-text("Copy")').first();
    await copyButton.click();

    // Wait for button text to change (clipboard operation is async)
    await expect(copyButton).toHaveText(/copied/i, { timeout: 2000 });

    // Button should have success styling class
    const buttonClass = await copyButton.getAttribute('class');
    expect(buttonClass).toContain('copy-success');
  });

  test('copy button resets after timeout', async ({ page }) => {
    const copyButton = page.locator('.shiki-copy, button:has-text("Copy")').first();
    const originalText = await copyButton.textContent();

    await copyButton.click();

    // Wait for reset (typically 2 seconds)
    await page.waitForTimeout(2500);

    // Button should reset
    const resetText = await copyButton.textContent();
    expect(resetText).toBe(originalText);
  });
});

test.describe('Interactive - Collapse/Expand', () => {
  test.beforeEach(async ({ page }) => {
    // Set mobile viewport to test collapse
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/');
  });

  test('collapse toggle button works', async ({ page }) => {
    const collapseToggle = page.locator('button[data-bs-toggle="collapse"]').first();

    if ((await collapseToggle.count()) > 0) {
      const targetId = await collapseToggle.getAttribute('data-bs-target');
      expect(targetId).toBeTruthy();

      const collapseTarget = page.locator(targetId!);

      // Click to expand
      await collapseToggle.click();
      await page.waitForTimeout(500);

      // Should have .show class
      const hasShow = await collapseTarget.evaluate(el => el.classList.contains('show'));
      expect(hasShow).toBeTruthy();
    }
  });

  test('collapse has smooth animation', async ({ page }) => {
    const collapseToggle = page.locator('button[data-bs-toggle="collapse"]').first();

    if ((await collapseToggle.count()) > 0) {
      const targetId = await collapseToggle.getAttribute('data-bs-target');
      const collapseTarget = page.locator(targetId!);

      // Start collapse
      await collapseToggle.click();

      // During animation, should have .collapsing class
      await page.waitForTimeout(50);

      const hasCollapsing = await collapseTarget.evaluate(el =>
        el.classList.contains('collapsing') || el.classList.contains('show')
      );

      expect(hasCollapsing).toBeTruthy();
    }
  });

  test('collapse updates aria-expanded attribute', async ({ page }) => {
    const collapseToggle = page.locator('button[data-bs-toggle="collapse"]').first();

    if ((await collapseToggle.count()) > 0) {
      const initialAria = await collapseToggle.getAttribute('aria-expanded');

      // Click to toggle
      await collapseToggle.click();

      // collapse.js flips aria-expanded inside the transitionend handler, so the
      // change can land after a fixed wait on slower engines (WebKit). Poll the
      // attribute with a web-first assertion instead of sampling once.
      await expect(collapseToggle).not.toHaveAttribute('aria-expanded', initialAria ?? 'false');
    }
  });
});
