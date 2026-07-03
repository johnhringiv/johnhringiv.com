import { test, expect } from '@playwright/test';
import { VIEWPORTS } from '../../fixtures/test-data';

/**
 * Responsive design tests
 *
 * Tests for responsive behavior across different viewports:
 * - Mobile (375x667), Tablet (768x1024), Laptop (1366x768), Desktop (1920x1080)
 * - No horizontal overflow
 * - Navbar behavior changes appropriately
 * - Images scale correctly
 */

test.describe('Responsive - Mobile (375x667)', () => {
  test.beforeEach(async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.mobile);
  });

  test('homepage renders without horizontal scroll', async ({ page }) => {
    await page.goto('/');

    const bodyWidth = await page.evaluate(() => document.body.scrollWidth);
    const viewportWidth = page.viewportSize()?.width;

    expect(bodyWidth).toBeLessThanOrEqual(viewportWidth!);
  });

  test('blog page renders without horizontal scroll', async ({ page }) => {
    await page.goto('/blog');

    const bodyWidth = await page.evaluate(() => document.body.scrollWidth);
    const viewportWidth = page.viewportSize()?.width;

    expect(bodyWidth).toBeLessThanOrEqual(viewportWidth!);
  });

  test('navbar uses hamburger menu', async ({ page }) => {
    await page.goto('/');

    const hamburger = page.locator('button[data-bs-toggle="collapse"], .navbar-toggler');
    await expect(hamburger).toBeVisible();
  });

  test('images fit within viewport', async ({ page }) => {
    await page.goto('/');

    const images = await page.locator('img').all();
    const viewportWidth = page.viewportSize()?.width;

    for (const img of images) {
      const box = await img.boundingBox();

      if (box) {
        expect(box.width).toBeLessThanOrEqual(viewportWidth!);
      }
    }
  });

  test('text content is readable', async ({ page }) => {
    await page.goto('/');

    // Check regular paragraphs - homepage doesn't use .lead class
    const paragraph = page.locator('p').first();
    const fontSize = await paragraph.evaluate(el => {
      return window.getComputedStyle(el).fontSize;
    });

    // Font size should be at least 14px for readability
    const fontSizeNum = parseFloat(fontSize);
    expect(fontSizeNum).toBeGreaterThanOrEqual(14);
  });
});

test.describe('Responsive - Tablet (768x1024)', () => {
  test.beforeEach(async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.tablet);
  });

  test('homepage renders without horizontal scroll', async ({ page }) => {
    await page.goto('/');

    const bodyWidth = await page.evaluate(() => document.body.scrollWidth);
    const viewportWidth = page.viewportSize()?.width;

    expect(bodyWidth).toBeLessThanOrEqual(viewportWidth!);
  });

  test('blog listing uses appropriate layout', async ({ page }) => {
    await page.goto('/blog');

    const articles = await page.locator('article').all();
    expect(articles.length).toBeGreaterThan(0);

    // Articles should be visible and within viewport
    for (const article of articles) {
      await expect(article).toBeVisible();
    }
  });

  test('images scale appropriately', async ({ page }) => {
    await page.goto('/blog');

    const images = await page.locator('img').all();
    const viewportWidth = page.viewportSize()?.width;

    for (const img of images) {
      const box = await img.boundingBox();

      if (box) {
        expect(box.width).toBeLessThanOrEqual(viewportWidth!);
      }
    }
  });
});

test.describe('Responsive - Laptop (1366x768)', () => {
  test.beforeEach(async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.laptop);
  });

  test('homepage renders without horizontal scroll', async ({ page }) => {
    await page.goto('/');

    const bodyWidth = await page.evaluate(() => document.body.scrollWidth);
    const viewportWidth = page.viewportSize()?.width;

    expect(bodyWidth).toBeLessThanOrEqual(viewportWidth!);
  });

  test('navigation uses desktop layout', async ({ page }) => {
    await page.goto('/');

    const nav = page.locator('nav');
    await expect(nav).toBeVisible();

    // Check if nav links are directly visible (no hamburger)
    const navLinks = await nav.locator('a').count();
    expect(navLinks).toBeGreaterThan(0);
  });

  test('two-column layouts render correctly', async ({ page }) => {
    await page.goto('/');

    // Check for any two-column layout elements
    const columns = await page.locator('.col-md-6, [class*="col-"]').count();

    if (columns > 0) {
      // Columns should be visible
      const firstColumn = page.locator('.col-md-6, [class*="col-"]').first();
      await expect(firstColumn).toBeVisible();
    }
  });
});

test.describe('Responsive - Desktop (1920x1080)', () => {
  test.beforeEach(async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.desktop);
  });

  test('homepage renders without horizontal scroll', async ({ page }) => {
    await page.goto('/');

    const bodyWidth = await page.evaluate(() => document.body.scrollWidth);
    const viewportWidth = page.viewportSize()?.width;

    expect(bodyWidth).toBeLessThanOrEqual(viewportWidth!);
  });

  test('navigation is fully visible', async ({ page }) => {
    await page.goto('/');

    const navLinks = await page.locator('nav a').all();

    for (const link of navLinks) {
      await expect(link).toBeVisible();
    }
  });

  test('images use appropriate sizes', async ({ page }) => {
    await page.goto('/blog');

    const images = await page.locator('img').all();

    for (const img of images) {
      const box = await img.boundingBox();

      if (box) {
        // Images should be reasonably sized, not full viewport width
        const viewportWidth = page.viewportSize()?.width;
        expect(box.width).toBeLessThan(viewportWidth!);
      }
    }
  });
});

test.describe('Responsive - Viewport Meta Tag', () => {
  test('all pages have responsive viewport meta tag', async ({ page }) => {
    const pages = ['/', '/blog', '/research', '/press'];

    for (const path of pages) {
      await page.goto(path);

      const viewport = await page.locator('meta[name="viewport"]').getAttribute('content');
      expect(viewport).toContain('width=device-width');
      expect(viewport).toContain('initial-scale=1');
    }
  });
});
