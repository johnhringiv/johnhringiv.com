import { Page, expect } from '@playwright/test';

/**
 * Validates that a page has a canonical URL
 */
export async function validateCanonicalExists(page: Page) {
  const canonical = await page.locator('link[rel="canonical"]');
  // Link tags in <head> are never "visible" - check that it exists instead
  await expect(canonical).toHaveCount(1);
  return canonical;
}

/**
 * Gets the canonical URL from a page
 */
export async function getCanonicalURL(page: Page): Promise<string> {
  const canonical = await validateCanonicalExists(page);
  const href = await canonical.getAttribute('href');
  expect(href).toBeTruthy();
  return href!;
}

/**
 * Validates that the canonical URL is absolute (includes protocol and domain)
 */
export async function validateCanonicalIsAbsolute(page: Page) {
  const canonicalURL = await getCanonicalURL(page);
  expect(canonicalURL).toMatch(/^https?:\/\//);
  return canonicalURL;
}

/**
 * Validates that the canonical URL does NOT have a .php extension
 */
export async function validateCanonicalNoPhpExtension(page: Page) {
  const canonicalURL = await getCanonicalURL(page);
  expect(canonicalURL).not.toContain('.php');
  return canonicalURL;
}

/**
 * Validates that the canonical URL matches the expected path
 *
 * IMPORTANT: Canonical URLs should ALWAYS point to production domain for SEO,
 * even when testing on localhost or Docker containers.
 *
 * @param page - Playwright page object
 * @param expectedPath - Expected path (e.g., '/blog', '/ncc-not-complete-but-capable')
 * @param productionDomain - Production domain for canonical URLs (default: 'https://johnhringiv.com')
 */
export async function validateCanonicalURL(page: Page, expectedPath: string, productionDomain: string = 'https://johnhringiv.com') {
  const canonicalURL = await getCanonicalURL(page);

  // Validate it's absolute
  expect(canonicalURL).toMatch(/^https?:\/\//);

  // Validate no .php extension
  expect(canonicalURL).not.toContain('.php');

  // Validate path matches expected
  const url = new URL(canonicalURL);
  const actualPath = url.pathname;

  // Normalize paths (remove trailing slash unless it's the root)
  const normalizedExpected = expectedPath === '/' ? '/' : expectedPath.replace(/\/$/, '');
  const normalizedActual = actualPath === '/' ? '/' : actualPath.replace(/\/$/, '');

  expect(normalizedActual).toBe(normalizedExpected);

  // Validate canonical uses production domain (critical for SEO)
  expect(url.origin).toBe(new URL(productionDomain).origin);

  return canonicalURL;
}

/**
 * Validates that the canonical URL uses the production domain
 * (Critical for SEO - canonical URLs should always point to production)
 */
export async function validateCanonicalUsesProductionDomain(page: Page, productionDomain: string = 'https://johnhringiv.com') {
  const canonicalURL = await getCanonicalURL(page);
  const url = new URL(canonicalURL);

  expect(url.origin).toBe(productionDomain);
  return canonicalURL;
}

/**
 * Compares canonical URLs between Docker and production environments
 * Paths should match exactly; only the domain should differ
 *
 * @param dockerPage - Page loaded from Docker container
 * @param productionPage - Page loaded from production site
 */
export async function compareCanonicalURLs(dockerPage: Page, productionPage: Page) {
  const dockerCanonical = await getCanonicalURL(dockerPage);
  const prodCanonical = await getCanonicalURL(productionPage);

  const dockerURL = new URL(dockerCanonical);
  const prodURL = new URL(prodCanonical);

  // Paths must match exactly (this is critical for SEO)
  expect(dockerURL.pathname).toBe(prodURL.pathname);

  // Query strings should also match (if any)
  expect(dockerURL.search).toBe(prodURL.search);

  // Hash fragments should also match (if any)
  expect(dockerURL.hash).toBe(prodURL.hash);

  return {
    dockerPath: dockerURL.pathname,
    prodPath: prodURL.pathname,
    match: dockerURL.pathname === prodURL.pathname
  };
}

/**
 * Validates canonical URL for all pages in a list
 *
 * @param page - Playwright page object
 * @param paths - Array of paths to test
 * @param baseURL - Base URL for the test environment
 * @param productionDomain - Production domain for canonical validation
 */
export async function validateCanonicalURLsForPaths(
  page: Page,
  paths: readonly string[],
  baseURL: string,
  productionDomain: string = 'https://johnhringiv.com'
) {
  const results: Array<{ path: string; canonical: string; valid: boolean }> = [];

  for (const path of paths) {
    await page.goto(path);
    await page.waitForLoadState('networkidle');

    try {
      const canonical = await validateCanonicalURL(page, path, productionDomain);
      results.push({ path, canonical, valid: true });
    } catch (error) {
      results.push({ path, canonical: '', valid: false });
      throw error; // Re-throw to fail the test
    }
  }

  return results;
}

/**
 * Validates that accessing a page with .php extension redirects to clean URL
 * and the canonical URL is the clean version
 */
export async function validatePhpRedirectToCleanURL(page: Page, cleanPath: string) {
  const phpPath = `${cleanPath}.php`;

  // Navigate to .php version
  await page.goto(phpPath);
  await page.waitForLoadState('networkidle');

  // Canonical should still be the clean URL
  const canonical = await getCanonicalURL(page);
  expect(canonical).not.toContain('.php');
  expect(canonical).toContain(cleanPath);

  return canonical;
}
