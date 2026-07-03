import { test, expect } from '@playwright/test';
import { ALL_PAGE_PATHS } from '../../fixtures/test-data';

/**
 * Content Security Policy (CSP) violation detection tests
 *
 * CRITICAL FOR SECURITY - Catches CSP violations that could indicate:
 * - Inline styles in SVGs or HTML
 * - Unsafe script execution
 * - Resource loading from unauthorized domains
 *
 * Tests include:
 * - No CSP violations on page load
 * - No CSP violations after interactions
 *
 * NOTE: Firefox is stricter about CSP enforcement than Chrome, so we test
 * on Firefox to catch violations that might be silently ignored in Chrome.
 */

test.describe('CSP Violation Detection', () => {
  test.beforeEach(async ({}, testInfo) => {
    // Only run on Firefox (stricter CSP enforcement)
    if (testInfo.project.name !== 'firefox') {
      test.skip();
    }
  });

  test('all pages load without CSP violations', async ({ page }) => {
    const cspViolations: string[] = [];

    // Listen for CSP violations in console
    page.on('console', msg => {
      const text = msg.text();
      if (text.includes('Content-Security-Policy') || text.includes('CSP')) {
        cspViolations.push(`CSP violation: ${text}`);
      }
    });

    // Test each page
    for (const path of ALL_PAGE_PATHS) {
      cspViolations.length = 0;

      await page.goto(path);

      // Wait for page to be fully loaded
      await page.waitForLoadState('networkidle');

      // Interact with page to trigger dynamic content
      await page.locator('body').click({ position: { x: 10, y: 10 } });

      // Wait for any deferred violations to surface
      await page.waitForTimeout(100);

      // Check for CSP violations
      expect(
        cspViolations,
        `CSP violations on ${path}:\n${cspViolations.join('\n')}`
      ).toHaveLength(0);
    }
  });

  test('social icons do not cause CSP violations', async ({ page }) => {
    const cspViolations: string[] = [];

    // Listen for CSP violations
    page.on('console', msg => {
      const text = msg.text();
      if (text.includes('Content-Security-Policy') || text.includes('CSP')) {
        cspViolations.push(`CSP violation: ${text}`);
      }
    });

    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Hover over social icons to ensure SVGs are rendered
    const socialIcons = await page.locator('.social-network a').all();
    for (const icon of socialIcons) {
      await icon.hover();
      await page.waitForTimeout(50);
    }

    // Check for CSP violations
    expect(
      cspViolations,
      `CSP violations from social icons:\n${cspViolations.join('\n')}`
    ).toHaveLength(0);
  });

  test('interactive elements do not cause CSP violations', async ({ page }) => {
    const cspViolations: string[] = [];

    // Listen for CSP violations
    page.on('console', msg => {
      const text = msg.text();
      if (text.includes('Content-Security-Policy') || text.includes('CSP')) {
        cspViolations.push(`CSP violation: ${text}`);
      }
    });

    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Test navbar toggler (mobile menu)
    const toggler = page.locator('.navbar-toggler');
    if (await toggler.isVisible()) {
      await toggler.click();
      await page.waitForTimeout(500);
    }

    // Test image modals (if present)
    const modalImages = await page.locator('.image-modal-content').all();
    if (modalImages.length > 0) {
      await modalImages[0].click();
      await page.waitForTimeout(500);
      await page.keyboard.press('Escape');
    }

    // Check for CSP violations
    expect(
      cspViolations,
      `CSP violations during interaction:\n${cspViolations.join('\n')}`
    ).toHaveLength(0);
  });
});
