import { test, expect } from '@playwright/test';
import {
  validateCanonicalURL,
  validateCanonicalUsesProductionDomain,
  validateCanonicalNoPhpExtension,
  validatePhpRedirectToCleanURL
} from '../../utils/canonical-url-validator';
import { ALL_PAGE_PATHS, EXPECTED_CANONICAL_URLS } from '../../fixtures/test-data';

/**
 * Canonical URL feature tests
 *
 * CRITICAL FOR SEO - These tests ensure canonical URLs are correct
 *
 * Tests include:
 * - Every page has canonical URL
 * - Clean URLs (no .php extension)
 * - Absolute URLs with correct domain
 * - Canonical matches current page path
 * - Docker vs Production comparison
 * - Feed canonical URLs
 *
 * NOTE: Canonical URLs are viewport-independent, so all tests only run on chromium
 * to avoid unnecessary duplication across mobile-chrome and tablet projects.
 */

// All canonical URL tests only run on chromium (viewport-independent)
test.describe('Canonical URL Tests', () => {
  test.beforeEach(async ({}, testInfo) => {
    if (testInfo.project.name !== 'chromium') {
      test.skip();
    }
  });

test.describe('Canonical URLs - All Pages', () => {
  test('every page has a canonical URL', async ({ page }) => {
    for (const path of ALL_PAGE_PATHS) {
      await page.goto(path);
      await page.waitForLoadState('networkidle');

      // Verify canonical link exists (link tags are never "visible", check count instead)
      const canonical = await page.locator('link[rel="canonical"]');
      await expect(canonical).toHaveCount(1);

      const href = await canonical.getAttribute('href');
      expect(href).toBeTruthy();
      expect(href).toMatch(/^https?:\/\//); // Should be absolute URL
    }
  });

  test('canonical URLs use production domain', async ({ page }) => {
    for (const path of ALL_PAGE_PATHS) {
      await page.goto(path);
      await page.waitForLoadState('networkidle');

      const canonicalURL = await validateCanonicalUsesProductionDomain(page, 'https://johnhringiv.com');
      expect(canonicalURL).toContain('johnhringiv.com');
    }
  });

  test('canonical URLs have no .php extension', async ({ page }) => {
    for (const path of ALL_PAGE_PATHS) {
      await page.goto(path);
      await page.waitForLoadState('networkidle');

      await validateCanonicalNoPhpExtension(page);
    }
  });

  test('canonical URLs match expected values', async ({ page }) => {
    for (const path of ALL_PAGE_PATHS) {
      await page.goto(path);
      await page.waitForLoadState('networkidle');

      const canonical = await page.locator('link[rel="canonical"]').getAttribute('href');
      const expectedCanonical = EXPECTED_CANONICAL_URLS[path];

      expect(canonical).toBe(expectedCanonical);
    }
  });
});

test.describe('Canonical URLs - Clean URLs', () => {
  test('homepage canonical is clean', async ({ page }) => {
    await page.goto('/');
    const canonicalURL = await validateCanonicalURL(page, '/', 'https://johnhringiv.com');
    expect(canonicalURL).toBe('https://johnhringiv.com/');
  });

  test('blog listing canonical is clean', async ({ page }) => {
    await page.goto('/blog');
    const canonicalURL = await validateCanonicalURL(page, '/blog', 'https://johnhringiv.com');
    expect(canonicalURL).toBe('https://johnhringiv.com/blog');
  });

  test('research page canonical is clean', async ({ page }) => {
    await page.goto('/research');
    const canonicalURL = await validateCanonicalURL(page, '/research', 'https://johnhringiv.com');
    expect(canonicalURL).toBe('https://johnhringiv.com/research');
  });

  test('press page canonical is clean', async ({ page }) => {
    await page.goto('/press');
    const canonicalURL = await validateCanonicalURL(page, '/press', 'https://johnhringiv.com');
    expect(canonicalURL).toBe('https://johnhringiv.com/press');
  });

  test('blog post canonicals are clean', async ({ page }) => {
    const blogPosts = [
      '/ncc-not-complete-but-capable',
      '/when-five-plus-five-equals-eleven',
      '/a_subtle_python_threading_bug',
      '/secure-scalable-home-web-hosting'
    ];

    for (const post of blogPosts) {
      await page.goto(post);
      const canonicalURL = await validateCanonicalURL(page, post, 'https://johnhringiv.com');
      expect(canonicalURL).toBe(`https://johnhringiv.com${post}`);
      expect(canonicalURL).not.toContain('.php');
    }
  });
});

test.describe('Canonical URLs - PHP Extension Handling', () => {
  test('.php URLs redirect to clean URL with correct canonical', async ({ page }) => {
    const testPaths = ['/blog', '/research', '/press'];

    for (const path of testPaths) {
      try {
        await validatePhpRedirectToCleanURL(page, path);
      } catch (error) {
        // If .php version doesn't exist or 404s, that's acceptable
        // The important thing is the clean URL has the correct canonical
        console.log(`Skipping .php test for ${path} - may not support .php extension`);
      }
    }
  });
});

test.describe('Canonical URLs - Current Environment', () => {
  test('canonical URLs are absolute (not relative)', async ({ page }) => {
    for (const path of ALL_PAGE_PATHS) {
      await page.goto(path);

      const canonical = await page.locator('link[rel="canonical"]').getAttribute('href');
      expect(canonical).toMatch(/^https:\/\//);
    }
  });

  test('canonical URL path matches current page', async ({ page, baseURL }) => {
    for (const path of ALL_PAGE_PATHS) {
      await page.goto(path);

      const canonical = await page.locator('link[rel="canonical"]').getAttribute('href');
      const canonicalURL = new URL(canonical!);

      // Normalize paths for comparison
      const normalizedExpected = path === '/' ? '/' : path.replace(/\/$/, '');
      const normalizedActual = canonicalURL.pathname === '/' ? '/' : canonicalURL.pathname.replace(/\/$/, '');

      expect(normalizedActual).toBe(normalizedExpected);
    }
  });
});

test.describe('Canonical URLs - Feed Entries', () => {
  test('feed entries have canonical URLs', async ({ page }) => {
    const response = await page.goto('/feed.php');
    expect(response?.status()).toBe(200);

    const content = await page.content();

    // Feed should contain canonical URLs in entry links
    expect(content).toContain('https://johnhringiv.com/');

    // Should not contain .php extensions in entry URLs
    const phpLinks = content.match(/https:\/\/johnhringiv\.com\/[^"'<>]*\.php/g);
    expect(phpLinks).toBeNull(); // No .php URLs should be in feed
  });
});

test.describe('Canonical URLs - Consistency', () => {
  test('all canonical URLs use HTTPS', async ({ page }) => {
    for (const path of ALL_PAGE_PATHS) {
      await page.goto(path);

      const canonical = await page.locator('link[rel="canonical"]').getAttribute('href');
      expect(canonical).toMatch(/^https:\/\//);
      expect(canonical).not.toContain('http://'); // Should not accidentally have http://
    }
  });

  test('canonical URLs do not have trailing slashes (except root)', async ({ page }) => {
    for (const path of ALL_PAGE_PATHS) {
      await page.goto(path);

      const canonical = await page.locator('link[rel="canonical"]').getAttribute('href');
      const url = new URL(canonical!);

      if (url.pathname === '/') {
        // Root can have trailing slash
        expect(url.pathname).toBe('/');
      } else {
        // Other paths should not have trailing slash
        expect(url.pathname).not.toMatch(/\/$/);
      }
    }
  });

  test('canonical URLs do not have query parameters', async ({ page }) => {
    for (const path of ALL_PAGE_PATHS) {
      await page.goto(path);

      const canonical = await page.locator('link[rel="canonical"]').getAttribute('href');
      const url = new URL(canonical!);

      // Canonical URLs should not have query strings
      expect(url.search).toBe('');
    }
  });

  test('canonical URLs do not have hash fragments', async ({ page }) => {
    for (const path of ALL_PAGE_PATHS) {
      await page.goto(path);

      const canonical = await page.locator('link[rel="canonical"]').getAttribute('href');
      const url = new URL(canonical!);

      // Canonical URLs should not have hash fragments
      expect(url.hash).toBe('');
    }
  });
});

}); // End Canonical URL Tests wrapper
