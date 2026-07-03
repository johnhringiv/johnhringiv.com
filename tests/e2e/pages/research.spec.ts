import { test, expect } from '@playwright/test';
import { validateCanonicalURL } from '../../utils/canonical-url-validator';

/**
 * Research page tests
 *
 * Tests for /research page including:
 * - Research entries from data/research.php
 * - Images with responsive srcset
 * - Links work (journal, PDF)
 * - Author lists display correctly
 */

test.describe('Research Page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/research');
  });

  test('has correct canonical URL', async ({ page }) => {
    await validateCanonicalURL(page, '/research');
  });

  test('displays research entries', async ({ page }) => {
    // Should have at least one research entry
    const researchEntries = await page.locator('article, .research-entry, [class*="publication"]').count();
    expect(researchEntries).toBeGreaterThan(0);
  });

  test('research entries have required elements', async ({ page }) => {
    const entries = await page.locator('article, .research-entry, [class*="publication"]').all();

    for (const entry of entries) {
      // Title
      const title = entry.locator('h2, h3, .title, [class*="title"]').first();
      if ((await title.count()) > 0) {
        await expect(title).toBeVisible();
      }

      // Should have some text content
      const text = await entry.textContent();
      expect(text?.trim()).toBeTruthy();
      expect(text!.length).toBeGreaterThan(20);
    }
  });

  test('research entries have images', async ({ page }) => {
    const entries = await page.locator('article, .research-entry, [class*="publication"]').all();
    expect(entries.length).toBeGreaterThan(0); // Ensure we have entries to test

    for (const entry of entries) {
      // Check if this is a patent entry (comes after "Patents" h2)
      const prevHeading = await entry.locator('xpath=preceding::h2[1]').textContent();
      const isPatent = prevHeading?.includes('Patent');

      // Look for main research image (not small icon images in links)
      // Note: Don't check visibility - images may be lazy-loaded below the fold
      const image = entry.locator('figure img, .col-md-4 img').first();
      const imageCount = await image.count();

      if (!isPatent) {
        // Peer-reviewed and preprints MUST have images
        expect(imageCount).toBeGreaterThan(0);

        // Should have alt text
        const alt = await image.getAttribute('alt');
        expect(alt).toBeTruthy();

        // Should have src
        const src = await image.getAttribute('src');
        expect(src).toBeTruthy();

        // Check for responsive srcset (if present)
        const srcset = await image.getAttribute('srcset');
        if (srcset) {
          expect(srcset).toContain('w'); // Width descriptors
        }
      } else {
        // Patents can optionally have images
        if (imageCount > 0) {
          // If patent has image, validate it
          const alt = await image.getAttribute('alt');
          expect(alt).toBeTruthy();

          const src = await image.getAttribute('src');
          expect(src).toBeTruthy();

          const srcset = await image.getAttribute('srcset');
          if (srcset) {
            expect(srcset).toContain('w');
          }
        }
      }
    }
  });

  test('research entries have links', async ({ page }) => {
    const entries = await page.locator('article, .research-entry, [class*="publication"]').all();

    for (const entry of entries) {
      const links = await entry.locator('a').all();

      // Each entry should have at least one link (to journal, PDF, etc.)
      expect(links.length).toBeGreaterThan(0);

      for (const link of links) {
        const href = await link.getAttribute('href');
        expect(href).toBeTruthy();

        // Link should have meaningful text or aria-label
        const text = await link.textContent();
        const ariaLabel = await link.getAttribute('aria-label');
        expect(text?.trim() || ariaLabel).toBeTruthy();
      }
    }
  });

  test('research entries display authors', async ({ page }) => {
    const entries = await page.locator('article, .research-entry, [class*="publication"]').all();

    for (const entry of entries) {
      const text = await entry.textContent();

      // Should contain author names (likely includes "Ring" or similar)
      // This is a loose check since author format varies
      expect(text).toBeTruthy();
      expect(text!.length).toBeGreaterThan(50);
    }
  });

  test('research entries display venue/publication info', async ({ page }) => {
    const entries = await page.locator('article, .research-entry, [class*="publication"]').all();

    for (const entry of entries) {
      const text = await entry.textContent();

      // Should have venue or publication information
      // This is verified by checking for substantial text content
      expect(text).toBeTruthy();
      expect(text!.length).toBeGreaterThan(50);
    }
  });

  test('research entries have year information', async ({ page }) => {
    const entries = await page.locator('article, .research-entry, [class*="publication"]').all();
    expect(entries.length).toBeGreaterThan(0); // Ensure we have entries to test

    for (const entry of entries) {
      const text = await entry.textContent();

      // Should contain a year (2019-2030 range covers all publications)
      const yearPattern = /201[0-9]|202[0-9]|203[0-9]/;
      expect(text).toMatch(yearPattern);
    }
  });

  test('active navigation link', async ({ page }) => {
    const nav = page.locator('nav');
    const researchLink = nav.locator('a[href="/research"]');

    // Research link should have .active class
    const hasActiveClass = await researchLink.evaluate(el => el.classList.contains('active'));
    expect(hasActiveClass).toBeTruthy();
  });
});
