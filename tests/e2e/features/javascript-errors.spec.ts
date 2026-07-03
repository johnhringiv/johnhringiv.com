import { test, expect } from '@playwright/test';
import { ALL_PAGE_PATHS } from '../../fixtures/test-data';

/**
 * JavaScript error detection tests
 *
 * CRITICAL FOR QUALITY - Catches runtime JavaScript errors
 *
 * Tests include:
 * - No console errors on page load
 * - No uncaught exceptions
 * - No unhandled promise rejections
 *
 * NOTE: JavaScript errors are viewport-independent, so all tests only run on chromium
 * to avoid unnecessary duplication across mobile-chrome and tablet projects.
 */

// All JavaScript error tests only run on chromium (viewport-independent)
test.describe('JavaScript Error Detection', () => {
  test.beforeEach(async ({}, testInfo) => {
    if (testInfo.project.name !== 'chromium') {
      test.skip();
    }
  });

  test('all pages load without JavaScript errors', async ({ page }) => {
    const consoleErrors: string[] = [];
    const pageErrors: string[] = [];

    // Listen for console errors
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push(`Console error: ${msg.text()}`);
      }
    });

    // Listen for uncaught exceptions
    page.on('pageerror', error => {
      pageErrors.push(`Page error: ${error.message}`);
    });

    // Test each page
    for (const path of ALL_PAGE_PATHS) {
      consoleErrors.length = 0;
      pageErrors.length = 0;

      await page.goto(path);

      // Wait for page to be fully loaded and JS to execute
      await page.waitForLoadState('networkidle');

      // Trigger potential errors by clicking on the page body
      // (Many errors only happen after user interaction)
      await page.locator('body').click({ position: { x: 10, y: 10 } });

      // Wait a bit for any deferred errors to surface
      await page.waitForTimeout(100);

      // Check for errors
      expect(consoleErrors, `Console errors on ${path}:\n${consoleErrors.join('\n')}`).toHaveLength(0);
      expect(pageErrors, `Page errors on ${path}:\n${pageErrors.join('\n')}`).toHaveLength(0);
    }
  });

  test('interactive elements do not cause JavaScript errors', async ({ page }) => {
    const consoleErrors: string[] = [];
    const pageErrors: string[] = [];

    // Listen for console errors
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push(`Console error: ${msg.text()}`);
      }
    });

    // Listen for uncaught exceptions
    page.on('pageerror', error => {
      pageErrors.push(`Page error: ${error.message}`);
    });

    // Test navigation clicks
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Click navigation links (don't actually navigate, just test the click)
    const navLinks = await page.locator('nav a').all();
    for (let i = 0; i < Math.min(3, navLinks.length); i++) {
      await navLinks[i].hover();
      // Just hover, don't click to avoid navigation
    }

    // Check for errors
    expect(consoleErrors, `Console errors during interaction:\n${consoleErrors.join('\n')}`).toHaveLength(0);
    expect(pageErrors, `Page errors during interaction:\n${pageErrors.join('\n')}`).toHaveLength(0);
  });
});
