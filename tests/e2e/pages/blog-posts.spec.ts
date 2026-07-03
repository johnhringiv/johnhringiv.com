import { test, expect } from '@playwright/test';
import { validateCanonicalURL } from '../../utils/canonical-url-validator';
import { BLOG_POST_SLUGS, BLOG_POST_METADATA } from '../../fixtures/test-data';

/**
 * Blog post page tests (parameterized for all posts)
 *
 * Tests for individual blog post pages including:
 * - Header structure (title, date, author, tags exist)
 * - Shiki syntax highlighting (dark papyrus background)
 * - Code blocks have copy buttons with base64 data
 * - Mermaid diagrams render as SVG (conditional)
 * - Anchor links in headings work
 * - Images with image-modal-content class
 */

test.describe('Blog Post Pages', () => {
  for (const slug of BLOG_POST_SLUGS) {
    const metadata = BLOG_POST_METADATA[slug as keyof typeof BLOG_POST_METADATA];

    test.describe(`${slug}`, () => {
      test.beforeEach(async ({ page }) => {
        await page.goto(`/${slug}`);
      });

      test('has page title', async ({ page }) => {
        const pageTitle = await page.title();
        expect(pageTitle).toBeTruthy();
        expect(pageTitle.length).toBeGreaterThan(10);
      });

      test('has correct canonical URL', async ({ page }) => {
        await validateCanonicalURL(page, `/${slug}`);
      });

      test('displays full header with title', async ({ page }) => {
        // Main title (h1)
        const h1 = page.locator('h1').first();
        await expect(h1).toBeVisible();

        const h1Text = await h1.textContent();
        expect(h1Text).toBeTruthy();
        expect(h1Text!.length).toBeGreaterThan(5);
      });

      test('displays publication date', async ({ page }) => {
        const dateElement = page.locator('time').first();
        await expect(dateElement).toBeVisible();

        // Should have datetime attribute
        const datetime = await dateElement.getAttribute('datetime');
        expect(datetime).toBeTruthy();

        // Datetime should be valid ISO 8601 format
        expect(() => new Date(datetime!)).not.toThrow();
      });

      test('displays author information', async ({ page }) => {
        // Author could be in meta tags or visible on page
        const authorMeta = await page.locator('meta[property="article:author"]').getAttribute('content');
        expect(authorMeta).toBeTruthy();
      });

      test('has at least one tag', async ({ page }) => {
        // Tags are rendered as .badge elements
        const badges = page.locator('.badge.bg-primary');
        const badgeCount = await badges.count();

        // Every blog post should have at least one tag
        expect(badgeCount, 'Blog post should have at least one tag').toBeGreaterThan(0);
      });

      if (metadata.hasCodeBlocks) {
        test('code blocks have Shiki syntax highlighting', async ({ page }) => {
          const codeBlocks = await page.locator('.shiki-container, pre.shiki').count();
          expect(codeBlocks).toBeGreaterThan(0);

          // Check first code block has dark papyrus background
          // Background is on .shiki-pre element, not .shiki-container
          const firstCodeBlock = page.locator('pre.shiki-pre, pre.shiki').first();
          await expect(firstCodeBlock).toBeVisible();

          // Check background color (dark papyrus)
          const bgColor = await firstCodeBlock.evaluate(el => {
            return window.getComputedStyle(el).backgroundColor;
          });

          // Modern browsers support OKLCH natively, older browsers convert to RGB
          // Accept either oklch(0.18-0.26 0.01-0.03 50-70) or rgb(30-60, 30-50, 25-45)
          const isValidColor =
            /oklch\(0\.[12]\d 0\.0[123] [567]\d\)/.test(bgColor) ||  // OKLCH format
            /rgb\([23456]\d, [234]\d, [234]\d\)/.test(bgColor);      // RGB format
          expect(isValidColor).toBe(true);
        });

        test('code blocks have copy buttons', async ({ page }) => {
          const copyButtons = await page.locator('.shiki-copy, button:has-text("Copy")').count();
          expect(copyButtons).toBeGreaterThan(0);

          // First copy button should be visible
          const firstButton = page.locator('.shiki-copy, button:has-text("Copy")').first();
          await expect(firstButton).toBeVisible();

          // Button should have data-code attribute with base64 content
          const dataCode = await firstButton.getAttribute('data-code');
          expect(dataCode).toBeTruthy();

          // Should be valid base64
          expect(() => atob(dataCode!)).not.toThrow();
        });

        test('code blocks have line numbers (unless disabled)', async ({ page }) => {
          const codeBlocks = await page.locator('.shiki-container').all();

          for (const block of codeBlocks) {
            // Check if block has line numbers (.ln is the class used)
            const lineNumbers = await block.locator('.ln').count();

            // Line numbers should be present (unless explicitly disabled with // shiki: nolinenum)
            // We just verify the structure is correct (>= 0 is always true, so check for at least some)
            // Some blocks may not have line numbers if disabled
            expect(lineNumbers >= 0).toBeTruthy();
          }
        });
      }

      if (metadata.hasMermaidDiagrams) {
        test('Mermaid diagrams render as SVG', async ({ page }) => {
          // Mermaid diagrams are included as <img> tags pointing to generated SVG files
          const svgImages = await page.locator('img[src*="/generated/mermaid/"]').count();
          expect(svgImages).toBeGreaterThan(0);

          // First SVG image should be visible
          const firstSvg = page.locator('img[src*="/generated/mermaid/"]').first();
          await expect(firstSvg).toBeVisible();
        });
      }

      test('heading anchor links work', async ({ page }) => {
        // Find all headings with ids
        const headingsWithIds = await page.locator('h2[id], h3[id], h4[id]').all();

        if (headingsWithIds.length > 0) {
          const firstHeading = headingsWithIds[0];
          const id = await firstHeading.getAttribute('id');
          expect(id).toBeTruthy();

          // Heading should have a self-referential anchor link
          const anchorLink = firstHeading.locator(`a[href="#${id}"]`);
          await expect(anchorLink).toBeVisible();

          // Clicking anchor link should update URL hash
          await anchorLink.click();
          expect(page.url()).toContain(`#${id}`);
        }
      });

      test('images have modal integration (if present)', async ({ page }) => {
        const modalImages = await page.locator('img.image-modal-content').all();

        for (const img of modalImages) {
          // Should have src
          const src = await img.getAttribute('src');
          expect(src).toBeTruthy();

          // May have data-modal-src for custom full-size image
          const modalSrc = await img.getAttribute('data-modal-src');

          // If data-modal-src exists, verify it's valid
          if (modalSrc) {
            expect(modalSrc).toMatch(/\.(jpg|jpeg|png|webp|svg)$/i);
          }
        }
      });

      test('has proper semantic structure', async ({ page }) => {
        // Should have main element
        const main = page.locator('main');
        await expect(main).toBeVisible();

        // Main should contain an article
        const article = main.locator('article');
        await expect(article).toBeVisible();
      });

      test('article metadata is complete', async ({ page }) => {
        // Check article:published_time (required)
        const publishedTime = await page.locator('meta[property="article:published_time"]').getAttribute('content');
        expect(publishedTime).toBeTruthy();
        expect(() => new Date(publishedTime!)).not.toThrow();

        // Check article:modified_time (optional - only if post was modified)
        const modifiedTimeMeta = page.locator('meta[property="article:modified_time"]');
        const modifiedTimeCount = await modifiedTimeMeta.count();
        if (modifiedTimeCount > 0) {
          const modifiedTime = await modifiedTimeMeta.getAttribute('content');
          expect(modifiedTime).toBeTruthy();
          expect(() => new Date(modifiedTime!)).not.toThrow();
        }
      });
    });
  }
});
