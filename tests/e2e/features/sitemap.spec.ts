import { test, expect } from '@playwright/test';
import { ALL_PAGE_PATHS } from '../../fixtures/test-data';

/**
 * Sitemap validation tests
 *
 * CRITICAL FOR SEO - Ensures search engines can discover all content
 *
 * Tests include:
 * - Valid XML structure (well-formed, no parse errors)
 * - Sitemap schema compliance
 * - All important pages included
 * - URLs are absolute with production domain
 * - No .php extensions in URLs
 * - Valid lastmod dates
 * - Appropriate priority values
 *
 * NOTE: Sitemap content is viewport-independent, so all tests only run on chromium
 * to avoid unnecessary duplication across mobile-chrome and tablet projects.
 */

const SITEMAP_PATH = '/sitemap.xml';

// All sitemap tests only run on chromium (viewport-independent)
test.describe('Sitemap Tests', () => {
  test.beforeEach(async ({}, testInfo) => {
    if (testInfo.project.name !== 'chromium') {
      test.skip();
    }
  });

test.describe('Sitemap - XML Validity', () => {
  test('sitemap returns valid XML', async ({ page }) => {
    const response = await page.goto(SITEMAP_PATH);

    expect(response?.status()).toBe(200);

    const contentType = response?.headers()['content-type'];
    expect(contentType).toMatch(/xml/);
  });

  test('sitemap is well-formed XML (no parse errors)', async ({ page }) => {
    await page.goto(SITEMAP_PATH);

    const content = await page.content();

    // Try parsing as XML to ensure it's well-formed
    await page.evaluate((xmlContent) => {
      const parser = new DOMParser();
      const xmlDoc = parser.parseFromString(xmlContent, 'application/xml');

      // Check for parsing errors
      const parseError = xmlDoc.querySelector('parsererror');
      if (parseError) {
        throw new Error(`XML Parse Error: ${parseError.textContent}`);
      }

      return true;
    }, content);
  });

  test('sitemap has correct namespace', async ({ page }) => {
    await page.goto(SITEMAP_PATH);

    const content = await page.content();

    // Should have sitemap namespace
    expect(content).toContain('xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"');
  });

  test('sitemap has urlset root element', async ({ page }) => {
    await page.goto(SITEMAP_PATH);

    const content = await page.content();

    expect(content).toContain('<urlset');
    expect(content).toContain('</urlset>');
  });
});

test.describe('Sitemap - URL Structure', () => {
  test('sitemap contains all pages', async ({ page }) => {
    await page.goto(SITEMAP_PATH);

    const content = await page.content();

    // Extract URLs from sitemap
    const urlMatches = content.match(/<loc>([^<]+)<\/loc>/g);
    expect(urlMatches).toBeTruthy();

    const urls = urlMatches!.map(match => {
      const url = match.replace(/<\/?loc>/g, '');
      return new URL(url).pathname;
    });

    // Check that all expected pages are included
    for (const path of ALL_PAGE_PATHS) {
      const normalized = path === '/' ? '/' : path.replace(/\/$/, '');
      expect(urls, `Expected sitemap to contain ${path}`).toContain(normalized);
    }
  });

  test('all URLs use production domain', async ({ page }) => {
    await page.goto(SITEMAP_PATH);

    const content = await page.content();

    const urlMatches = content.match(/<loc>([^<]+)<\/loc>/g);
    expect(urlMatches).toBeTruthy();

    for (const match of urlMatches!) {
      const url = match.replace(/<\/?loc>/g, '');
      expect(url).toMatch(/^https:\/\/johnhringiv\.com\//);
    }
  });

  test('no URLs have .php extension', async ({ page }) => {
    await page.goto(SITEMAP_PATH);

    const content = await page.content();

    const urlMatches = content.match(/<loc>([^<]+)<\/loc>/g);
    expect(urlMatches).toBeTruthy();

    for (const match of urlMatches!) {
      const url = match.replace(/<\/?loc>/g, '');

      // URLs should not have .php extension
      expect(url).not.toMatch(/\.php$/);
      expect(url).not.toContain('.php?');
    }
  });
});

test.describe('Sitemap - Required Fields', () => {
  test('all URLs have required elements', async ({ page }) => {
    await page.goto(SITEMAP_PATH);

    const content = await page.content();

    // Count <url> elements
    const urlElements = content.match(/<url>/g);
    expect(urlElements).toBeTruthy();
    expect(urlElements!.length).toBeGreaterThan(0);

    // Each <url> should have <loc>
    const locElements = content.match(/<loc>/g);
    expect(locElements!.length).toBe(urlElements!.length);
  });

  test('URLs have lastmod dates', async ({ page }) => {
    await page.goto(SITEMAP_PATH);

    const content = await page.content();

    // Should have lastmod elements
    expect(content).toContain('<lastmod>');

    // Extract lastmod dates
    const lastmodMatches = content.match(/<lastmod>([^<]+)<\/lastmod>/g);
    expect(lastmodMatches).toBeTruthy();

    // Verify dates are valid ISO 8601 format
    for (const match of lastmodMatches!) {
      const dateStr = match.replace(/<\/?lastmod>/g, '');

      // Should be valid date
      const date = new Date(dateStr);
      expect(date.toString()).not.toBe('Invalid Date');
    }
  });

  test('URLs have priority values', async ({ page }) => {
    await page.goto(SITEMAP_PATH);

    const content = await page.content();

    // Should have priority elements
    expect(content).toContain('<priority>');

    // Extract priority values
    const priorityMatches = content.match(/<priority>([^<]+)<\/priority>/g);
    expect(priorityMatches).toBeTruthy();

    // Verify priorities are between 0.0 and 1.0
    for (const match of priorityMatches!) {
      const priorityStr = match.replace(/<\/?priority>/g, '');
      const priority = parseFloat(priorityStr);

      expect(priority).toBeGreaterThanOrEqual(0.0);
      expect(priority).toBeLessThanOrEqual(1.0);
    }
  });
});

test.describe('Sitemap - Priority Values', () => {
  test('homepage has highest priority', async ({ page }) => {
    await page.goto(SITEMAP_PATH);

    const content = await page.content();

    // Find homepage URL and its priority
    const homepageMatch = content.match(
      /<url>[\s\S]*?<loc>https:\/\/johnhringiv\.com\/<\/loc>[\s\S]*?<priority>([^<]+)<\/priority>[\s\S]*?<\/url>/
    );

    expect(homepageMatch).toBeTruthy();

    const homePriority = parseFloat(homepageMatch![1]);

    // Homepage should have priority 1.0 or close to it
    expect(homePriority).toBeGreaterThanOrEqual(0.9);
  });

  test('priorities are reasonable for content types', async ({ page }) => {
    await page.goto(SITEMAP_PATH);

    const content = await page.content();

    // Extract all URL/priority pairs
    const urlBlocks = content.match(/<url>[\s\S]*?<\/url>/g);
    expect(urlBlocks).toBeTruthy();

    for (const block of urlBlocks!) {
      const locMatch = block.match(/<loc>([^<]+)<\/loc>/);
      const priorityMatch = block.match(/<priority>([^<]+)<\/priority>/);

      if (locMatch && priorityMatch) {
        const url = locMatch[1];
        const priority = parseFloat(priorityMatch[1]);

        // Blog posts and main pages should have decent priority (>= 0.6)
        if (url.includes('/blog') || url.includes('/research') || url.includes('/press')) {
          expect(priority).toBeGreaterThanOrEqual(0.6);
        }
      }
    }
  });
});

test.describe('Sitemap - Completeness', () => {
  test('sitemap has reasonable number of URLs', async ({ page }) => {
    await page.goto(SITEMAP_PATH);

    const content = await page.content();

    const urlElements = content.match(/<url>/g);
    expect(urlElements).toBeTruthy();

    // Should have at least all pages + feed
    const minUrls = ALL_PAGE_PATHS.length + 1; // +1 for feed
    expect(urlElements!.length).toBeGreaterThanOrEqual(minUrls);

    // But shouldn't have too many (avoid spam/auto-generated pages)
    expect(urlElements!.length).toBeLessThan(1000);
  });

  test('no duplicate URLs in sitemap', async ({ page }) => {
    await page.goto(SITEMAP_PATH);

    const content = await page.content();

    const urlMatches = content.match(/<loc>([^<]+)<\/loc>/g);
    expect(urlMatches).toBeTruthy();

    const urls = urlMatches!.map(match => match.replace(/<\/?loc>/g, ''));

    // Check for duplicates
    const uniqueUrls = new Set(urls);
    expect(uniqueUrls.size).toBe(urls.length);
  });
});

test.describe('Sitemap - SEO Best Practices', () => {
  test('sitemap uses HTTPS', async ({ page }) => {
    await page.goto(SITEMAP_PATH);

    const content = await page.content();

    const urlMatches = content.match(/<loc>([^<]+)<\/loc>/g);
    expect(urlMatches).toBeTruthy();

    // All URLs should use HTTPS
    for (const match of urlMatches!) {
      const url = match.replace(/<\/?loc>/g, '');
      expect(url).toMatch(/^https:\/\//);
      expect(url).not.toContain('http://');
    }
  });

  test('sitemap accessible at standard location', async ({ page }) => {
    // Sitemap should be at /sitemap.xml
    const response = await page.goto('/sitemap.xml');
    expect(response?.status()).toBe(200);
  });
});

}); // End Sitemap Tests wrapper
