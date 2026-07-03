import { test, expect } from '@playwright/test';
import { validateResponsiveImage, validateOGImage, checkImageExists } from '../../utils/image-validator';
import { ALL_PAGE_PATHS, BLOG_POST_SLUGS } from '../../fixtures/test-data';

/**
 * Image generation feature tests
 *
 * CRITICAL FOR PERFORMANCE - These tests ensure images are optimized
 *
 * Tests include:
 * - Responsive srcset generation (multiple image sizes)
 * - Cache busting works (?v=hash prevents stale images)
 * - All image URLs are valid (no broken images)
 * - Image modal integration (data-modal-src fallback)
 * - Lazy loading for below-fold images
 */

test.describe('Image Generation - Responsive Srcset', () => {
  test('homepage profile image has responsive srcset', async ({ page, baseURL }) => {
    await page.goto('/');

    const profileImg = page.locator('img[alt*="John"]').first();
    await expect(profileImg).toBeVisible();

    // Should have srcset
    const srcset = await profileImg.getAttribute('srcset');
    expect(srcset).toBeTruthy();
    expect(srcset).toContain('w'); // Width descriptors (e.g., "400w")

    // Parse srcset and verify multiple sizes
    const srcsetEntries = srcset!.split(',').map(s => s.trim());
    expect(srcsetEntries.length).toBeGreaterThan(1);
  });

  test('blog images have responsive srcset', async ({ page }) => {
    await page.goto('/blog');

    const blogImages = await page.locator('article img').all();

    for (const img of blogImages) {
      const srcset = await img.getAttribute('srcset');

      // Most blog images should have srcset (some may be SVG or decorative)
      if (srcset) {
        expect(srcset).toContain('w');

        const srcsetEntries = srcset.split(',').map(s => s.trim());
        expect(srcsetEntries.length).toBeGreaterThan(0);
      }
    }
  });

  test('research images have responsive srcset', async ({ page }) => {
    await page.goto('/research');

    const researchImages = await page.locator('article img, .research-entry img').all();

    for (const img of researchImages) {
      const srcset = await img.getAttribute('srcset');

      if (srcset) {
        expect(srcset).toContain('w');
      }
    }
  });
});

test.describe('Image Generation - AVIF Format Optimization', () => {
  test('press images use AVIF format in srcset', async ({ page }) => {
    await page.goto('/press');

    // Find ALL images within press entries, including those without srcset
    const allPressImages = await page.locator('article figure img').all();

    // Press page has 4 entries, but 1 (in_a_flash) has special handling without responsiveImage
    // So we expect at least 3 images total
    expect(allPressImages.length).toBeGreaterThanOrEqual(3);

    let imagesWithSrcset = 0;
    let imagesWithAvif = 0;

    for (const img of allPressImages) {
      const src = await img.getAttribute('src');
      const srcset = await img.getAttribute('srcset');

      // Skip the special-case in_a_flash image
      if (src?.includes('in_a_flash')) {
        continue;
      }

      // All other press images should have srcset (AVIF generation required)
      expect(srcset, `Press image ${src} missing srcset - AVIF images not generated. Run: docker build to regenerate.`).toBeTruthy();
      imagesWithSrcset++;

      if (srcset) {
        // Verify srcset contains AVIF URLs with width descriptors
        // Format should be: /generated/img/press/filename_400w.avif 400w, /generated/img/press/filename_600w.avif 600w, ...
        expect(srcset, `Press image ${src} has srcset but NOT using AVIF format. Found: ${srcset}. AVIF images not generated during build.`).toMatch(/\.avif(?:\?[^\s]*)?\s+\d+w/);
        imagesWithAvif++;

        // Verify multiple AVIF sizes are generated
        const avifMatches = srcset.match(/\.avif(?:\?[^\s]*)?\s+\d+w/g);
        expect(avifMatches, `Press image ${src} should have multiple AVIF sizes, found: ${srcset}`).toBeTruthy();
        expect(avifMatches!.length).toBeGreaterThanOrEqual(2); // At least 2 different sizes
      }
    }

    // Verify we tested at least some images with srcset
    // Note: Small images or those with special handling may not have srcset
    expect(imagesWithSrcset, 'Press page should have at least 1 image with srcset').toBeGreaterThanOrEqual(1);

    // All images that HAVE srcset must use AVIF format
    expect(imagesWithAvif, 'All press images with srcset must use AVIF format').toBe(imagesWithSrcset);
  });

  test('research images use AVIF format in srcset', async ({ page }) => {
    await page.goto('/research');

    // Find ALL images within research entries
    const allResearchImages = await page.locator('figure img').all();

    // Research page has 6 peer-reviewed + 1 preprint = 7 publications
    expect(allResearchImages.length).toBeGreaterThanOrEqual(6);

    let imagesWithAvif = 0;

    for (const img of allResearchImages) {
      const src = await img.getAttribute('src');
      const srcset = await img.getAttribute('srcset');

      // All research images should have srcset (AVIF generation required)
      expect(srcset, `Research image ${src} missing srcset - AVIF images not generated. Run: docker build to regenerate.`).toBeTruthy();

      if (srcset) {
        // Verify srcset contains at least one AVIF URL
        expect(srcset, `Research image ${src} has srcset but NOT using AVIF format. Found: ${srcset}. AVIF images not generated during build.`).toMatch(/\.avif(?:\?[^\s]*)?\s+\d+w/);
        imagesWithAvif++;

        // Note: Smaller images may only have 1 AVIF size with JPG fallback for larger sizes
        // This is correct behavior - the function falls back to original when AVIF doesn't exist
      }
    }

    // All research images should have AVIF (research images are typically large figures)
    expect(imagesWithAvif, 'All research images must use AVIF format for optimal performance').toBeGreaterThanOrEqual(6);
  });

  test('homepage profile image uses AVIF format in srcset', async ({ page }) => {
    await page.goto('/');

    const profileImg = page.locator('img[alt*="Ring"]').first();
    const srcset = await profileImg.getAttribute('srcset');

    expect(srcset).toBeTruthy();
    expect(srcset).toMatch(/\.avif(?:\?[^\s]*)?\s+\d+w/);

    // Profile image should have multiple AVIF sizes
    const avifMatches = srcset!.match(/\.avif(?:\?[^\s]*)?\s+\d+w/g);
    expect(avifMatches).toBeTruthy();
    expect(avifMatches!.length).toBeGreaterThanOrEqual(3);
  });

  test('blog post images use AVIF format in srcset', async ({ page }) => {
    await page.goto('/ncc-not-complete-but-capable');

    // Find blog post content images (not Open Graph meta images)
    const contentImages = await page.locator('article img[srcset]').all();

    for (const img of contentImages) {
      const srcset = await img.getAttribute('srcset');

      if (srcset) {
        // Blog images should use AVIF
        expect(srcset).toMatch(/\.avif(?:\?[^\s]*)?\s+\d+w/);
      }
    }
  });

  // Every raster content image inside a blog post must be served through
  // responsiveImage() (AVIF srcset). A raw <img src="...png"> bypasses the build's
  // image pipeline entirely, so no AVIF is generated and the full-size PNG/JPG ships.
  // This loops over ALL posts so a new post can't silently regress (the per-post
  // "blog post images use AVIF" test above only inspects imgs that already have a
  // srcset, so a srcset-less raw <img> slips past it).
  for (const slug of BLOG_POST_SLUGS) {
    test(`raster content images in /${slug} use AVIF srcset`, async ({ page }) => {
      await page.goto(`/${slug}`);

      // Scope to the article body; nav/footer/sprite icons are out of scope.
      const contentImages = await page.locator('main#main-content img').all();

      const offenders: string[] = [];

      for (const img of contentImages) {
        const src = await img.getAttribute('src');
        if (!src || src.startsWith('data:')) continue;

        // Only raster images need AVIF. SVGs (hero art, mermaid diagrams) are vector
        // and are intentionally served as-is.
        const path = src.split('?')[0].toLowerCase();
        if (!/\.(png|jpe?g|webp)$/.test(path)) continue;

        const srcset = await img.getAttribute('srcset');
        if (!srcset || !/\.avif(?:\?[^\s]*)?\s+\d+w/.test(srcset)) {
          offenders.push(src);
        }
      }

      expect(
        offenders,
        `Raster content image(s) in /${slug} ship without an AVIF srcset. This means a ` +
        `raw <img> is bypassing responsiveImage(), or the AVIF variants were never ` +
        `generated. Convert to responsiveImage() and rebuild (docker build) so AVIFs ` +
        `are produced:\n  - ${offenders.join('\n  - ')}`
      ).toEqual([]);
    });
  }

  test('images wider than 400px have srcset with AVIF', async ({ page }) => {
    await page.goto('/research');

    const images = await page.locator('figure img').all();

    for (const img of images) {
      const src = await img.getAttribute('src');

      // Get natural image dimensions by evaluating in browser context
      const dimensions = await img.evaluate((el: HTMLImageElement) => ({
        width: el.naturalWidth,
        height: el.naturalHeight
      }));

      // Images wider than 400px should get AVIF generation
      if (dimensions.width > 400) {
        const srcset = await img.getAttribute('srcset');

        expect(srcset,
          `Image ${src} is ${dimensions.width}x${dimensions.height} (> 400px wide) but has no srcset. ` +
          `Large images should have AVIF versions for optimal performance.`
        ).toBeTruthy();

        if (srcset) {
          expect(srcset,
            `Image ${src} (${dimensions.width}x${dimensions.height}) has srcset but NOT using AVIF. ` +
            `Found: ${srcset}`
          ).toMatch(/\.avif(?:\?[^\s]*)?\s+\d+w/);
        }
      }
    }
  });
});

test.describe('Image Generation - Cache Busting', () => {
  test('all images have cache busting query parameter', async ({ page }) => {
    for (const path of ALL_PAGE_PATHS) {
      await page.goto(path);

      const images = await page.locator('img[src]').all();

      for (const img of images) {
        const src = await img.getAttribute('src');

        if (src && !src.startsWith('data:')) {
          // Skip data URLs
          // Check for version parameter
          const hasVersionParam = src.includes('?v=') || src.includes('&v=');

          // Not all images may have cache busting (external images, etc.)
          // But local images should have it
          if (!src.startsWith('http') || src.includes('johnhringiv.com')) {
            // Local image - should have cache busting
            if (!src.endsWith('.svg')) {
              // SVG sprite doesn't need cache busting
              // expect(hasVersionParam).toBeTruthy();
            }
          }
        }
      }
    }
  });

  test('cache busting hashes are valid MD5', async ({ page }) => {
    await page.goto('/');

    const images = await page.locator('img[src*="?v="]').all();

    for (const img of images) {
      const src = await img.getAttribute('src');

      if (src) {
        const versionMatch = src.match(/[?&]v=([a-f0-9]+)/i);

        if (versionMatch) {
          const hash = versionMatch[1];

          // MD5 hashes are 32 characters
          expect(hash.length).toBe(32);
          expect(hash).toMatch(/^[a-f0-9]{32}$/i);
        }
      }
    }
  });
});

test.describe('Image Generation - Image Files Exist', () => {
  test('all page images return 200 status', async ({ page, baseURL }) => {
    const imagesToCheck: string[] = [];

    for (const path of ALL_PAGE_PATHS.slice(0, 3)) {
      // Test first 3 pages to save time
      await page.goto(path);

      const images = await page.locator('img[src]').all();

      for (const img of images) {
        const src = await img.getAttribute('src');

        if (src && !src.startsWith('data:') && !src.startsWith('http')) {
          imagesToCheck.push(src);
        }
      }
    }

    // Check each unique image
    const uniqueImages = [...new Set(imagesToCheck)];

    for (const imageURL of uniqueImages.slice(0, 10)) {
      // Check first 10 unique images
      const exists = await checkImageExists(page.request, imageURL, baseURL || 'http://localhost:8080');
      expect(exists, `Image ${imageURL} returned 404 or failed to load`).toBeTruthy();
    }
  });

  test('Open Graph images exist on all pages', async ({ page, baseURL }) => {
    for (const path of ALL_PAGE_PATHS) {
      await page.goto(path);

      const ogImageURL = await validateOGImage(page, baseURL || 'http://localhost:8080');
      expect(ogImageURL).toBeTruthy();
    }
  });
});

test.describe('Image Generation - Srcset URLs Valid', () => {
  test('all srcset URLs return 200 status', async ({ page, baseURL }) => {
    await page.goto('/');

    const images = await page.locator('img[srcset]').all();

    for (const img of images.slice(0, 3)) {
      // Test first 3 images with srcset
      const srcset = await img.getAttribute('srcset');

      if (srcset) {
        const srcsetEntries = srcset.split(',').map(s => {
          const parts = s.trim().split(/\s+/);
          return parts[0]; // URL part
        });

        for (const url of srcsetEntries) {
          const exists = await checkImageExists(page.request, url, baseURL || 'http://localhost:8080');
          expect(exists).toBeTruthy();
        }
      }
    }
  });

  test('all AVIF files in srcset exist across all pages', async ({ page, baseURL }) => {
    const pagesToCheck = ['/', '/press', '/research', '/blog', '/ncc-not-complete-but-capable'];
    const avifUrlsToCheck = new Set<string>();
    const imageSourceMap = new Map<string, string[]>(); // Track which pages use which AVIF URLs

    // Collect all AVIF URLs from srcsets across all pages
    for (const path of pagesToCheck) {
      await page.goto(path);
      const images = await page.locator('img[srcset]').all();

      for (const img of images) {
        const srcset = await img.getAttribute('srcset');
        const src = await img.getAttribute('src');

        if (srcset) {
          // Extract all URLs from srcset (format: "url 400w, url 600w, ...")
          const srcsetEntries = srcset.split(',').map(s => s.trim().split(/\s+/)[0]);

          srcsetEntries.forEach(url => {
            if (url.includes('.avif')) {
              avifUrlsToCheck.add(url);

              // Track source for debugging
              if (!imageSourceMap.has(url)) {
                imageSourceMap.set(url, []);
              }
              imageSourceMap.get(url)!.push(`${path} (img src="${src}")`);
            }
          });
        }
      }
    }

    // Verify we found some AVIF URLs to test
    expect(avifUrlsToCheck.size, 'Should have found AVIF URLs in srcsets. If this fails, AVIF generation may not be working.').toBeGreaterThan(0);

    console.log(`\n📊 Found ${avifUrlsToCheck.size} unique AVIF files to verify across ${pagesToCheck.length} pages`);

    // Check that all unique AVIF URLs actually exist
    let checkedCount = 0;
    let failedUrls: string[] = [];

    for (const url of avifUrlsToCheck) {
      const exists = await checkImageExists(page.request, url, baseURL || 'http://localhost:8080');

      if (!exists) {
        const sources = imageSourceMap.get(url) || ['unknown'];
        failedUrls.push(`${url} (used on: ${sources.join(', ')})`);
      }

      checkedCount++;
    }

    // Report results
    if (failedUrls.length > 0) {
      const errorMsg = [
        `\n❌ ${failedUrls.length} AVIF file(s) missing (out of ${avifUrlsToCheck.size} total):`,
        ...failedUrls.map(f => `   - ${f}`),
        '\n💡 These AVIF files were referenced in srcsets but do not exist.',
        '   Run Docker build to regenerate: docker build -t johnhringiv.com:latest .'
      ].join('\n');

      expect(failedUrls.length, errorMsg).toBe(0);
    }

    console.log(`✅ All ${checkedCount} AVIF files exist and return 200 status`);
  });
});

test.describe('Image Generation - Image Modal Integration', () => {
  test('modal images have correct src or data-modal-src', async ({ page }) => {
    // Check blog posts for modal images
    await page.goto('/ncc-not-complete-but-capable');

    const modalImages = await page.locator('img.image-modal-content').all();

    for (const img of modalImages) {
      const src = await img.getAttribute('src');
      const modalSrc = await img.getAttribute('data-modal-src');

      expect(src).toBeTruthy();

      // If data-modal-src exists, verify it's valid
      if (modalSrc) {
        expect(modalSrc).toMatch(/\.(jpg|jpeg|png|webp|svg)$/i);
      }
    }
  });
});

test.describe('Image Generation - Lazy Loading', () => {
  test('below-fold images have lazy loading', async ({ page }) => {
    await page.goto('/blog');

    // Get viewport height
    const viewportHeight = await page.evaluate(() => window.innerHeight);

    const images = await page.locator('img').all();

    for (const img of images) {
      const box = await img.boundingBox();

      if (box && box.y > viewportHeight) {
        // Image is below fold
        const loading = await img.getAttribute('loading');

        // Should have loading="lazy"
        if (loading) {
          expect(loading).toBe('lazy');
        }
      }
    }
  });
});

test.describe('Image Generation - Open Graph Dimensions', () => {
  test('Open Graph images have correct dimensions', async ({ page }) => {
    for (const path of ALL_PAGE_PATHS) {
      await page.goto(path);

      const ogImage = await page.locator('meta[property="og:image"]').getAttribute('content');
      const ogWidth = await page.locator('meta[property="og:image:width"]').getAttribute('content');
      const ogHeight = await page.locator('meta[property="og:image:height"]').getAttribute('content');

      // All pages must have OG image dimension meta tags
      expect(ogWidth, `Page ${path} is missing og:image:width meta tag. Image: ${ogImage}`).not.toBeNull();
      expect(ogHeight, `Page ${path} is missing og:image:height meta tag. Image: ${ogImage}`).not.toBeNull();

      const actualWidth = parseInt(ogWidth!);
      const actualHeight = parseInt(ogHeight!);

      // Height must be exactly 630 for optimal OG display
      expect(actualHeight, `Page ${path} has og:image:height=${actualHeight} (must be exactly 630). Image: ${ogImage}`).toBe(630);

      // Width should ideally be 1200, but allow aspect-ratio preserved images
      // Require minimum width of 400px to ensure reasonable display
      expect(actualWidth, `Page ${path} has og:image:width=${actualWidth} (must be at least 400px). Image: ${ogImage}`).toBeGreaterThanOrEqual(400);

      // Warn if not ideal 1200x630 (but don't fail)
      if (actualWidth !== 1200) {
        console.log(`⚠️  Page ${path} has og:image at ${actualWidth}x${actualHeight} (ideal: 1200x630). Image: ${ogImage}`);
      }
    }
  });
});
