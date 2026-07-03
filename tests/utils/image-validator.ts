import { Page, expect, Locator, APIRequestContext } from '@playwright/test';

/**
 * Parsed srcset entry
 */
export interface SrcsetEntry {
  url: string;
  descriptor: string;
  width?: number;
}

/**
 * Image validation result
 */
export interface ImageValidationResult {
  src: string;
  srcset: SrcsetEntry[];
  sizes: string | null;
  alt: string | null;
  hasCacheBusting: boolean;
  cacheHash: string | null;
  allUrlsValid: boolean;
  invalidUrls: string[];
}

/**
 * Parses a srcset attribute into individual entries
 */
export function parseSrcset(srcset: string): SrcsetEntry[] {
  if (!srcset || srcset.trim() === '') {
    return [];
  }

  return srcset.split(',').map(entry => {
    const parts = entry.trim().split(/\s+/);
    const url = parts[0];
    const descriptor = parts[1] || '';

    // Extract width from descriptor (e.g., "400w" -> 400)
    const widthMatch = descriptor.match(/^(\d+)w$/);
    const width = widthMatch ? parseInt(widthMatch[1]) : undefined;

    return { url, descriptor, width };
  });
}

/**
 * Validates that an image has responsive srcset attribute
 */
export async function validateResponsiveSrcset(img: Locator) {
  const srcset = await img.getAttribute('srcset');

  expect(srcset).toBeTruthy();
  expect(srcset).not.toBe('');

  // Parse srcset
  const entries = parseSrcset(srcset!);

  // Should have multiple sizes
  expect(entries.length).toBeGreaterThan(1);

  // All entries should have width descriptors
  for (const entry of entries) {
    expect(entry.descriptor).toMatch(/^\d+w$/);
    expect(entry.width).toBeGreaterThan(0);
  }

  return entries;
}

/**
 * Validates that an image has a sizes attribute
 */
export async function validateSizesAttribute(img: Locator) {
  const sizes = await img.getAttribute('sizes');

  expect(sizes).toBeTruthy();
  expect(sizes).not.toBe('');

  return sizes;
}

/**
 * Validates cache busting query parameter in a URL
 */
export function validateCacheBusting(url: string): { hasCacheBusting: boolean; hash: string | null } {
  const urlObj = new URL(url, 'http://localhost'); // Base URL needed for relative URLs
  const vParam = urlObj.searchParams.get('v');

  if (!vParam) {
    return { hasCacheBusting: false, hash: null };
  }

  // MD5 hash should be 32 characters
  const isValidHash = /^[a-f0-9]{32}$/i.test(vParam);

  return {
    hasCacheBusting: isValidHash,
    hash: isValidHash ? vParam : null
  };
}

/**
 * Checks if an image URL returns 200 status
 */
export async function checkImageExists(request: APIRequestContext, imageURL: string, baseURL: string): Promise<boolean> {
  try {
    // Handle relative URLs
    const fullURL = imageURL.startsWith('http') ? imageURL : `${baseURL}${imageURL}`;

    const response = await request.get(fullURL);
    return response.status() === 200;
  } catch (error) {
    return false;
  }
}

/**
 * Validates all URLs in a srcset attribute
 */
export async function validateSrcsetURLs(
  request: APIRequestContext,
  srcset: SrcsetEntry[],
  baseURL: string
): Promise<{ allValid: boolean; invalidUrls: string[] }> {
  const invalidUrls: string[] = [];

  for (const entry of srcset) {
    const exists = await checkImageExists(request, entry.url, baseURL);
    if (!exists) {
      invalidUrls.push(entry.url);
    }
  }

  return {
    allValid: invalidUrls.length === 0,
    invalidUrls
  };
}

/**
 * Validates a complete responsive image
 */
export async function validateResponsiveImage(
  page: Page,
  selector: string,
  baseURL: string
): Promise<ImageValidationResult> {
  const img = page.locator(selector);
  await expect(img).toBeVisible();

  // Get basic attributes
  const src = await img.getAttribute('src');
  const srcsetAttr = await img.getAttribute('srcset');
  const sizes = await img.getAttribute('sizes');
  const alt = await img.getAttribute('alt');

  expect(src).toBeTruthy();

  // Parse srcset
  const srcset = srcsetAttr ? parseSrcset(srcsetAttr) : [];

  // Validate cache busting on src
  const cacheBusting = validateCacheBusting(src!);

  // Validate all URLs exist (src + srcset)
  const urlsToCheck = [src!, ...srcset.map(e => e.url)];
  const invalidUrls: string[] = [];

  for (const url of urlsToCheck) {
    const exists = await checkImageExists(page.request, url, baseURL);
    if (!exists) {
      invalidUrls.push(url);
    }
  }

  return {
    src: src!,
    srcset,
    sizes,
    alt,
    hasCacheBusting: cacheBusting.hasCacheBusting,
    cacheHash: cacheBusting.hash,
    allUrlsValid: invalidUrls.length === 0,
    invalidUrls
  };
}

/**
 * Validates image modal integration
 */
export async function validateImageModalIntegration(img: Locator) {
  // Check if image has image-modal-content class
  const hasModalClass = await img.evaluate(el =>
    el.classList.contains('image-modal-content')
  );

  if (!hasModalClass) {
    return null; // Not a modal image
  }

  // Get src and data-modal-src
  const src = await img.getAttribute('src');
  const modalSrc = await img.getAttribute('data-modal-src');

  expect(src).toBeTruthy();

  // If data-modal-src exists, verify it's a valid URL
  if (modalSrc) {
    expect(modalSrc).toMatch(/\.(jpg|jpeg|png|webp|svg)$/i);
  }

  return {
    src: src!,
    modalSrc: modalSrc || src!, // Fallback to src if no data-modal-src
    hasCustomModalSrc: !!modalSrc
  };
}

/**
 * Validates lazy loading attribute
 */
export async function validateLazyLoading(img: Locator, shouldBeLazy: boolean = true) {
  const loading = await img.getAttribute('loading');

  if (shouldBeLazy) {
    expect(loading).toBe('lazy');
  } else {
    // Critical images should be eagerly loaded or have no loading attribute
    expect(loading === null || loading === 'eager').toBeTruthy();
  }
}

/**
 * Validates all images on a page
 */
export async function validateAllImagesOnPage(page: Page, baseURL: string) {
  const images = await page.locator('img').all();

  const results: Array<{
    selector: string;
    valid: boolean;
    hasAlt: boolean;
    hasSrcset: boolean;
    errors: string[];
  }> = [];

  for (let i = 0; i < images.length; i++) {
    const img = images[i];
    const errors: string[] = [];

    // Get basic info
    const src = await img.getAttribute('src');
    const alt = await img.getAttribute('alt');
    const srcset = await img.getAttribute('srcset');

    // Validate alt text exists
    if (alt === null) {
      errors.push('Missing alt attribute');
    }

    // Validate src exists
    if (!src) {
      errors.push('Missing src attribute');
    } else {
      // Check if image exists
      const exists = await checkImageExists(page.request, src, baseURL);
      if (!exists) {
        errors.push(`Image not found: ${src}`);
      }
    }

    results.push({
      selector: `img:nth-of-type(${i + 1})`,
      valid: errors.length === 0,
      hasAlt: alt !== null,
      hasSrcset: srcset !== null && srcset !== '',
      errors
    });
  }

  return results;
}

/**
 * Validates Open Graph image
 */
export async function validateOGImage(page: Page, baseURL: string) {
  const ogImage = await page.locator('meta[property="og:image"]').getAttribute('content');

  expect(ogImage).toBeTruthy();
  expect(ogImage).toMatch(/^https?:\/\//);

  // For testing, replace production domain with test baseURL
  // OG images use full URLs like https://johnhringiv.com/generated/...
  // But tests run against http://localhost:8082
  const testURL = ogImage!.replace(/^https?:\/\/[^/]+/, baseURL);
  const response = await page.request.get(testURL);

  expect(response.status(), `og:image URL ${ogImage} (tested as ${testURL}) returned ${response.status()}`).toBe(200);

  const contentType = response.headers()['content-type'];
  expect(contentType, `og:image URL ${ogImage} has content-type "${contentType}" (expected image/*)`).toMatch(/^image\//);

  return ogImage;
}
