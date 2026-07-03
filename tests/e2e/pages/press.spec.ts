import { test, expect } from '@playwright/test';
import { validateCanonicalURL } from '../../utils/canonical-url-validator';

/**
 * Press page tests
 *
 * Tests for /press page including:
 * - Press entries from data/press.php
 * - External links work
 * - Publication names and dates correct
 * - Images and descriptions render
 */

test.describe('Press Page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/press');
  });

  test('has correct canonical URL', async ({ page }) => {
    await validateCanonicalURL(page, '/press');
  });

  test('displays press entries', async ({ page }) => {
    // Should have at least one press entry
    const pressEntries = await page.locator('article, .press-entry, [class*="press"]').count();
    expect(pressEntries).toBeGreaterThan(0);
  });

  test('press entries have required elements', async ({ page }) => {
    const entries = await page.locator('article, .press-entry, [class*="press"]').all();

    for (const entry of entries) {
      // Should have text content
      const text = await entry.textContent();
      expect(text?.trim()).toBeTruthy();
      expect(text!.length).toBeGreaterThan(20);

      // Should have a link
      const links = await entry.locator('a').count();
      expect(links).toBeGreaterThan(0);
    }
  });

  test('press entries have dates', async ({ page }) => {
    const entries = await page.locator('article, .press-entry, [class*="press"]').all();

    for (const entry of entries) {
      // Look for time element or date pattern
      const timeElement = entry.locator('time');
      const timeCount = await timeElement.count();

      if (timeCount > 0) {
        const datetime = await timeElement.first().getAttribute('datetime');
        expect(datetime).toBeTruthy();

        // Should be valid date
        expect(() => new Date(datetime!)).not.toThrow();
      } else {
        // If no time element, should have date in text (year pattern)
        const text = await entry.textContent();
        expect(text).toMatch(/20[1-3][0-9]/);
      }
    }
  });

  test('press entries have external links', async ({ page }) => {
    const entries = await page.locator('article, .press-entry, [class*="press"]').all();
    expect(entries.length).toBeGreaterThan(0);

    for (const entry of entries) {
      const links = await entry.locator('a[href^="http"], a[target="_blank"]').all();

      // Every press entry SHOULD have at least one external link
      expect(links.length).toBeGreaterThan(0);

      for (const link of links) {
        const href = await link.getAttribute('href');
        expect(href).toBeTruthy();
        expect(href).toMatch(/^https?:\/\//);

        // Should have meaningful text
        const text = await link.textContent();
        expect(text?.trim()).toBeTruthy();
      }
    }
  });

  test('active navigation link', async ({ page }) => {
    const nav = page.locator('nav');
    const pressLink = nav.locator('a[href="/press"]');

    // Press link should have .active class
    const hasActiveClass = await pressLink.evaluate(el => el.classList.contains('active'));
    expect(hasActiveClass).toBeTruthy();
  });

  test('press entries are in reverse chronological order', async ({ page }) => {
    const dates = await page.locator('article time, .press-entry time, [class*="press"] time').all();

    if (dates.length < 2) {
      return; // Not enough entries to test order
    }

    const dateTimes: Date[] = [];
    for (const dateElement of dates) {
      const dateStr = await dateElement.getAttribute('datetime');
      if (dateStr) {
        dateTimes.push(new Date(dateStr));
      }
    }

    // Verify dates are in descending order (newest first)
    for (let i = 1; i < dateTimes.length; i++) {
      expect(dateTimes[i - 1].getTime()).toBeGreaterThanOrEqual(dateTimes[i].getTime());
    }
  });
});
