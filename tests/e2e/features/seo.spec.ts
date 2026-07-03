import { test, expect } from '@playwright/test';
import { validateFavicon, validateFeedLink } from '../../utils/seo-validator';
import { ALL_PAGE_PATHS } from '../../fixtures/test-data';

/**
 * SEO feature tests
 *
 * Tests for SEO metadata across all pages including:
 * - Meta description (55-200 chars)
 * - Open Graph tags (type, title, description, url, image)
 * - Twitter Card tags
 * - Favicon and feed link
 *
 * NOTE: SEO metadata is viewport-independent, so all tests only run on chromium
 * to avoid unnecessary duplication across mobile-chrome and tablet projects.
 */

// All SEO tests only run on chromium (viewport-independent)
test.describe('SEO Tests', () => {
  test.beforeEach(async ({}, testInfo) => {
    if (testInfo.project.name !== 'chromium') {
      test.skip();
    }
  });

test.describe('SEO - Meta Tags', () => {
  test('all pages have title tag', async ({ page }) => {
    for (const path of ALL_PAGE_PATHS) {
      await page.goto(path);

      const title = await page.title();
      expect(title).toBeTruthy();
      expect(title.length).toBeGreaterThan(10);
      expect(title.length).toBeLessThan(90); // Accommodates title + subtitle
    }
  });

  test('all pages have meta description', async ({ page }) => {
    for (const path of ALL_PAGE_PATHS) {
      await page.goto(path);

      const description = await page.locator('meta[name="description"]').getAttribute('content');
      expect(description).toBeTruthy();
      expect(description!.length).toBeGreaterThanOrEqual(55);
      expect(description!.length).toBeLessThanOrEqual(200);
    }
  });
});

test.describe('SEO - Open Graph Tags', () => {
  test('all pages have required OG tags', async ({ page }) => {
    for (const path of ALL_PAGE_PATHS) {
      await page.goto(path);

      const ogType = await page.locator('meta[property="og:type"]').getAttribute('content');
      const ogTitle = await page.locator('meta[property="og:title"]').getAttribute('content');
      const ogDescription = await page.locator('meta[property="og:description"]').getAttribute('content');
      const ogUrl = await page.locator('meta[property="og:url"]').getAttribute('content');
      const ogImage = await page.locator('meta[property="og:image"]').getAttribute('content');

      expect(ogType).toBeTruthy();
      expect(ogTitle).toBeTruthy();
      expect(ogDescription).toBeTruthy();
      expect(ogUrl).toBeTruthy();
      expect(ogImage).toBeTruthy();
    }
  });

  test('OG URLs are absolute', async ({ page }) => {
    for (const path of ALL_PAGE_PATHS) {
      await page.goto(path);

      const ogUrl = await page.locator('meta[property="og:url"]').getAttribute('content');
      const ogImage = await page.locator('meta[property="og:image"]').getAttribute('content');

      expect(ogUrl).toMatch(/^https?:\/\//);
      expect(ogImage).toMatch(/^https?:\/\//);
    }
  });
});

test.describe('SEO - Twitter Card Tags', () => {
  test('all pages have Twitter Card tags', async ({ page }) => {
    for (const path of ALL_PAGE_PATHS) {
      await page.goto(path);

      const twitterCard = await page.locator('meta[name="twitter:card"]').getAttribute('content');
      const twitterTitle = await page.locator('meta[name="twitter:title"]').getAttribute('content');
      const twitterDescription = await page.locator('meta[name="twitter:description"]').getAttribute('content');
      const twitterImage = await page.locator('meta[name="twitter:image"]').getAttribute('content');

      expect(twitterCard).toBe('summary_large_image');
      expect(twitterTitle).toBeTruthy();
      expect(twitterDescription).toBeTruthy();
      expect(twitterImage).toBeTruthy();
    }
  });
});

test.describe('SEO - Favicon and Feed', () => {
  test('all pages have favicon', async ({ page }) => {
    for (const path of ALL_PAGE_PATHS) {
      await page.goto(path);
      await validateFavicon(page);
    }
  });

  test('all pages have feed link', async ({ page }) => {
    for (const path of ALL_PAGE_PATHS) {
      await page.goto(path);
      await validateFeedLink(page);
    }
  });
});

}); // End SEO Tests wrapper
