import { Page, expect } from '@playwright/test';
import { META_DESCRIPTION_LENGTH, OG_IMAGE_DIMENSIONS } from '../fixtures/test-data';

/**
 * Expected SEO metadata for a page
 */
export interface ExpectedSEO {
  title: string;
  description: string;
  ogType?: 'website' | 'article';
  ogImage?: string;
  publishedTime?: string;
  modifiedTime?: string;
  author?: string;
  section?: string;
  tags?: string[];
}

/**
 * Validates basic SEO meta tags (title, description)
 */
export async function validateBasicSEO(page: Page, expected: ExpectedSEO) {
  // Validate page title
  await expect(page).toHaveTitle(expected.title);

  // Validate meta description
  const metaDescription = await page.locator('meta[name="description"]').getAttribute('content');
  expect(metaDescription).toBeTruthy();
  expect(metaDescription).toBe(expected.description);

  // Verify description length meets SEO best practices
  const descLength = metaDescription?.length || 0;
  expect(descLength).toBeGreaterThanOrEqual(META_DESCRIPTION_LENGTH.min);
  expect(descLength).toBeLessThanOrEqual(META_DESCRIPTION_LENGTH.max);
}

/**
 * Validates Open Graph meta tags
 */
export async function validateOpenGraph(page: Page, expected: ExpectedSEO) {
  // Required OG tags
  const ogType = await page.locator('meta[property="og:type"]').getAttribute('content');
  const ogTitle = await page.locator('meta[property="og:title"]').getAttribute('content');
  const ogDescription = await page.locator('meta[property="og:description"]').getAttribute('content');
  const ogUrl = await page.locator('meta[property="og:url"]').getAttribute('content');
  const ogImage = await page.locator('meta[property="og:image"]').getAttribute('content');

  // Validate OG type
  expect(ogType).toBe(expected.ogType || 'website');

  // Validate OG title matches page title
  expect(ogTitle).toBe(expected.title);

  // Validate OG description matches meta description
  expect(ogDescription).toBe(expected.description);

  // Validate OG URL exists and is absolute
  expect(ogUrl).toBeTruthy();
  expect(ogUrl).toMatch(/^https?:\/\//);

  // Validate OG image exists and is absolute URL
  expect(ogImage).toBeTruthy();
  expect(ogImage).toMatch(/^https?:\/\//);
  if (expected.ogImage) {
    expect(ogImage).toContain(expected.ogImage);
  }

  // Validate OG image dimensions
  const ogImageWidth = await page.locator('meta[property="og:image:width"]').getAttribute('content');
  const ogImageHeight = await page.locator('meta[property="og:image:height"]').getAttribute('content');

  if (ogImageWidth && ogImageHeight) {
    expect(parseInt(ogImageWidth)).toBe(OG_IMAGE_DIMENSIONS.width);
    expect(parseInt(ogImageHeight)).toBe(OG_IMAGE_DIMENSIONS.height);
  }
}

/**
 * Validates article-specific Open Graph tags
 */
export async function validateArticleOpenGraph(page: Page, expected: ExpectedSEO) {
  // First validate standard OG tags
  await validateOpenGraph(page, expected);

  // Validate article-specific tags
  const publishedTime = await page.locator('meta[property="article:published_time"]').getAttribute('content');
  const modifiedTime = await page.locator('meta[property="article:modified_time"]').getAttribute('content');
  const author = await page.locator('meta[property="article:author"]').getAttribute('content');

  // Published time is required for articles
  expect(publishedTime).toBeTruthy();
  if (expected.publishedTime) {
    expect(publishedTime).toBe(expected.publishedTime);
  }

  // Modified time should be present
  expect(modifiedTime).toBeTruthy();
  if (expected.modifiedTime) {
    expect(modifiedTime).toBe(expected.modifiedTime);
  }

  // Author should be present
  if (expected.author) {
    expect(author).toBe(expected.author);
  }

  // Section (optional)
  if (expected.section) {
    const section = await page.locator('meta[property="article:section"]').getAttribute('content');
    expect(section).toBe(expected.section);
  }

  // Tags (optional)
  if (expected.tags && expected.tags.length > 0) {
    for (const tag of expected.tags) {
      const tagElement = await page.locator(`meta[property="article:tag"][content="${tag}"]`).count();
      expect(tagElement).toBeGreaterThan(0);
    }
  }
}

/**
 * Validates Twitter Card meta tags
 */
export async function validateTwitterCard(page: Page) {
  const twitterCard = await page.locator('meta[name="twitter:card"]').getAttribute('content');
  const twitterTitle = await page.locator('meta[name="twitter:title"]').getAttribute('content');
  const twitterDescription = await page.locator('meta[name="twitter:description"]').getAttribute('content');
  const twitterImage = await page.locator('meta[name="twitter:image"]').getAttribute('content');

  // Validate Twitter Card type
  expect(twitterCard).toBe('summary_large_image');

  // Validate Twitter tags are present
  expect(twitterTitle).toBeTruthy();
  expect(twitterDescription).toBeTruthy();
  expect(twitterImage).toBeTruthy();
  expect(twitterImage).toMatch(/^https?:\/\//);
}

/**
 * Validates all SEO tags for a standard page
 */
export async function validatePageSEO(page: Page, expected: ExpectedSEO) {
  await validateBasicSEO(page, expected);
  await validateOpenGraph(page, expected);
  await validateTwitterCard(page);
}

/**
 * Validates all SEO tags for a blog post/article page
 */
export async function validateArticleSEO(page: Page, expected: ExpectedSEO) {
  await validateBasicSEO(page, expected);
  await validateArticleOpenGraph(page, expected);
  await validateTwitterCard(page);
}

/**
 * Validates favicon link (handles multiple favicons for different sizes)
 */
export async function validateFavicon(page: Page) {
  const faviconCount = await page.locator('link[rel="icon"]').count();
  expect(faviconCount).toBeGreaterThan(0);

  // Check first favicon has valid href (allow a ?v=<hash> cache-busting query)
  const favicon = await page.locator('link[rel="icon"]').first().getAttribute('href');
  expect(favicon).toBeTruthy();
  expect(favicon).toMatch(/\.(?:ico|png|svg)(?:\?.*)?$/);
}

/**
 * Validates Atom feed link
 */
export async function validateFeedLink(page: Page) {
  const feedLink = await page.locator('link[rel="alternate"][type="application/atom+xml"]').getAttribute('href');
  expect(feedLink).toBeTruthy();
  expect(feedLink).toContain('feed');
}
