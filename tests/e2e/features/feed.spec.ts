import { test, expect, Page } from '@playwright/test';
import { FEED_CONFIG } from '../../fixtures/test-data';

/**
 * Feed feature tests
 *
 * Tests for Atom feed functionality:
 * - Valid XML with correct content-type
 * - Contains entries from blog, research, press
 * - Entries sorted by date descending
 * - Required Atom fields present
 * - Media RSS thumbnails
 *
 * NOTE: Feed content is viewport-independent, so all tests only run on chromium
 * to avoid unnecessary duplication across mobile-chrome and tablet projects.
 */

/**
 * Helper function to get raw feed content (not HTML-rendered)
 */
async function getFeedContent(page: Page): Promise<string> {
  const response = await page.goto(FEED_CONFIG.path);
  return await response!.text();
}

// All feed tests only run on chromium (viewport-independent)
test.describe('Feed Tests', () => {
  test.beforeEach(async ({}, testInfo) => {
    if (testInfo.project.name !== 'chromium') {
      test.skip();
    }
  });

test.describe('Feed - Basic Functionality', () => {
  test('feed returns valid XML', async ({ page }) => {
    const response = await page.goto(FEED_CONFIG.path);

    expect(response?.status()).toBe(200);

    const contentType = response?.headers()['content-type'];
    expect(contentType).toContain(FEED_CONFIG.contentType);
  });

  test('feed is well-formed XML (no parse errors)', async ({ page }) => {
    const response = await page.goto(FEED_CONFIG.path);

    // Get raw response body, not rendered HTML
    const content = await response!.text();

    // Try parsing as XML to ensure it's well-formed
    await page.evaluate((xmlContent) => {
      const parser = new DOMParser();
      const xmlDoc = parser.parseFromString(xmlContent, 'text/xml');

      // Check for parsing errors
      const parseError = xmlDoc.querySelector('parsererror');
      if (parseError) {
        throw new Error(`XML Parse Error: ${parseError.textContent}`);
      }

      return true;
    }, content);
  });

  test('feed has Atom namespace', async ({ page }) => {
    await page.goto(FEED_CONFIG.path);

    const content = await getFeedContent(page);

    // Should have Atom namespace
    expect(content).toContain('xmlns="http://www.w3.org/2005/Atom"');
  });

  test('feed has required elements', async ({ page }) => {
    await page.goto(FEED_CONFIG.path);

    const content = await getFeedContent(page);

    // Required Atom feed elements
    expect(content).toContain('<feed');
    expect(content).toContain('<title>');
    expect(content).toContain('<link');
    expect(content).toContain('<id>');
    expect(content).toContain('<updated>');
  });
});

test.describe('Feed - Entries', () => {
  test('feed contains multiple entries', async ({ page }) => {
    await page.goto(FEED_CONFIG.path);

    const content = await getFeedContent(page);

    // Count <entry> elements
    const entryMatches = content.match(/<entry>/g);
    expect(entryMatches).toBeTruthy();
    expect(entryMatches!.length).toBeGreaterThanOrEqual(FEED_CONFIG.minEntries);
  });

  test('entries have required Atom fields', async ({ page }) => {
    await page.goto(FEED_CONFIG.path);

    const content = await getFeedContent(page);

    // Each entry should have these fields
    expect(content).toContain('<title>');
    expect(content).toContain('<link');
    expect(content).toContain('<id>');
    expect(content).toContain('<published>');
    expect(content).toContain('<updated>');
  });

  test('entries are sorted by date descending', async ({ page }) => {
    await page.goto(FEED_CONFIG.path);

    const content = await getFeedContent(page);

    // Extract published dates
    const publishedMatches = content.match(/<published>([^<]+)<\/published>/g);

    if (publishedMatches && publishedMatches.length > 1) {
      const dates = publishedMatches.map(match => {
        const dateStr = match.replace(/<\/?published>/g, '');
        return new Date(dateStr);
      });

      // Verify dates are in descending order
      for (let i = 1; i < dates.length; i++) {
        expect(dates[i - 1].getTime()).toBeGreaterThanOrEqual(dates[i].getTime());
      }
    }
  });
});

test.describe('Feed - Media RSS Thumbnails', () => {
  test('entries have Media RSS thumbnails', async ({ page }) => {
    await page.goto(FEED_CONFIG.path);

    const content = await getFeedContent(page);

    // Should have Media RSS namespace
    expect(content).toContain('xmlns:media=');

    // Should have media:thumbnail elements
    expect(content).toContain('<media:thumbnail');
  });

  test('media thumbnails have URLs', async ({ page }) => {
    await page.goto(FEED_CONFIG.path);

    const content = await getFeedContent(page);

    const thumbnailMatches = content.match(/<media:thumbnail url="([^"]+)"/g);

    if (thumbnailMatches) {
      for (const match of thumbnailMatches) {
        const urlMatch = match.match(/url="([^"]+)"/);
        expect(urlMatch).toBeTruthy();

        const url = urlMatch![1];
        expect(url).toMatch(/^https?:\/\//);
      }
    }
  });
});

test.describe('Feed - Content Types', () => {
  test('feed includes blog posts', async ({ page }) => {
    await page.goto(FEED_CONFIG.path);

    const content = await getFeedContent(page);

    // Should contain at least one blog post title
    expect(content).toMatch(/NCC:|When 5\+5|Python Threading|Home Web Hosting/);
  });

  test('feed includes research entries', async ({ page }) => {
    await page.goto(FEED_CONFIG.path);

    const content = await getFeedContent(page);

    // Feed should aggregate content from research (if there are publications)
    // This is a loose check since content varies
    const hasMultipleTypes = content.split('<entry>').length > 4;
    expect(hasMultipleTypes).toBeTruthy();
  });
});

test.describe('Feed - Links', () => {
  test('feed entries have canonical links', async ({ page }) => {
    await page.goto(FEED_CONFIG.path);

    const content = await getFeedContent(page);

    // Extract entry links
    const linkMatches = content.match(/<link[^>]+href="([^"]+)"/g);

    expect(linkMatches).toBeTruthy();

    for (const match of linkMatches!) {
      const hrefMatch = match.match(/href="([^"]+)"/);

      if (hrefMatch) {
        const href = hrefMatch[1];

        // All links should be absolute URLs with HTTPS
        expect(href).toMatch(/^https:\/\//);

        // For johnhringiv.com links, ensure no .php extension
        if (href.includes('johnhringiv.com') && !href.includes('/feed')) {
          expect(href).not.toContain('.php');
        }
      }
    }
  });
});

test.describe('Feed - Atom Spec Compliance', () => {
  test('feed has required Atom elements', async ({ page }) => {
    await page.goto(FEED_CONFIG.path);

    const content = await getFeedContent(page);

    // Required feed-level elements per Atom spec
    expect(content).toContain('<feed');
    expect(content).toContain('<id>');
    expect(content).toContain('<title>');
    expect(content).toContain('<updated>');

    // Should have at least one author (feed or entry level)
    expect(content).toContain('<author>');
  });

  test('feed self-link is correct', async ({ page }) => {
    await page.goto(FEED_CONFIG.path);

    const content = await getFeedContent(page);

    // Self link should exist
    const selfLinkMatch = content.match(/<link[^>]+rel="self"[^>]+>/);
    expect(selfLinkMatch).toBeTruthy();

    // Should point to feed URL
    expect(selfLinkMatch![0]).toContain('johnhringiv.com/feed');
    expect(selfLinkMatch![0]).toContain('type="application/atom+xml"');
  });

  test('feed alternate link points to website', async ({ page }) => {
    await page.goto(FEED_CONFIG.path);

    const content = await getFeedContent(page);

    // Alternate link should exist
    const alternateLinkMatch = content.match(/<link[^>]+rel="alternate"[^>]+>/);
    expect(alternateLinkMatch).toBeTruthy();

    // Should point to homepage
    expect(alternateLinkMatch![0]).toContain('johnhringiv.com/');
    expect(alternateLinkMatch![0]).toContain('type="text/html"');
  });

  test('entries have unique IDs', async ({ page }) => {
    await page.goto(FEED_CONFIG.path);

    const content = await getFeedContent(page);

    // Extract all entry IDs
    const idMatches = content.match(/<entry>[\s\S]*?<id>([^<]+)<\/id>[\s\S]*?<\/entry>/g);

    if (idMatches && idMatches.length > 1) {
      const ids = idMatches.map(match => {
        const idMatch = match.match(/<id>([^<]+)<\/id>/);
        return idMatch ? idMatch[1] : '';
      });

      // All IDs should be unique
      const uniqueIds = new Set(ids);
      expect(uniqueIds.size).toBe(ids.length);
    }
  });

  test('entry IDs use tag URI scheme', async ({ page }) => {
    await page.goto(FEED_CONFIG.path);

    const content = await getFeedContent(page);

    const entryIdMatches = content.match(/<entry>[\s\S]*?<id>([^<]+)<\/id>/g);

    if (entryIdMatches) {
      for (const match of entryIdMatches) {
        const idMatch = match.match(/<id>([^<]+)<\/id>/);
        if (idMatch) {
          const id = idMatch[1];

          // IDs should use tag: URI scheme (Atom best practice)
          expect(id).toMatch(/^tag:/);
        }
      }
    }
  });

  test('updated dates are valid ISO 8601', async ({ page }) => {
    await page.goto(FEED_CONFIG.path);

    const content = await getFeedContent(page);

    const updatedMatches = content.match(/<updated>([^<]+)<\/updated>/g);
    expect(updatedMatches).toBeTruthy();

    for (const match of updatedMatches!) {
      const dateStr = match.replace(/<\/?updated>/g, '');

      // Should be valid date
      const date = new Date(dateStr);
      expect(date.toString()).not.toBe('Invalid Date');
    }
  });
});

test.describe('Feed - SEO and Discoverability', () => {
  test('feed uses HTTPS for all URLs', async ({ page }) => {
    await page.goto(FEED_CONFIG.path);

    const content = await getFeedContent(page);

    // Extract content URLs (from href, url attributes, and content elements)
    // Exclude namespace/schema URLs (xmlns, xsi:schemaLocation)
    const urlMatches = content.match(/(?:href|url)=["']?(https?:\/\/[^\s"'<>]+)/g);

    if (urlMatches) {
      for (const match of urlMatches) {
        // Extract the actual URL from the match (remove href=" or url=")
        const urlMatch = match.match(/https?:\/\/[^\s"'<>]+/);
        if (urlMatch) {
          const url = urlMatch[0];

          // Skip schema/namespace URLs (these are allowed to be http://)
          if (url.includes('w3.org') || url.includes('purl.org') || url.includes('search.yahoo.com/mrss')) {
            continue;
          }

          // All content URLs should use HTTPS
          expect(url).toMatch(/^https:\/\//);
          expect(url).not.toContain('http://johnhringiv.com');
        }
      }
    }
  });

  test('feed has icon and logo', async ({ page }) => {
    await page.goto(FEED_CONFIG.path);

    const content = await getFeedContent(page);

    // Atom feeds should have icon and/or logo
    const hasIcon = content.includes('<icon>');
    const hasLogo = content.includes('<logo>');

    expect(hasIcon || hasLogo).toBeTruthy();
  });

  test('feed has copyright/rights statement', async ({ page }) => {
    await page.goto(FEED_CONFIG.path);

    const content = await getFeedContent(page);

    // Should have rights statement
    expect(content).toContain('<rights>');
  });
});

}); // End Feed Tests wrapper
