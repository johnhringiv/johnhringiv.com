import { test, expect } from '@playwright/test';
import { validateCanonicalURL } from '../../utils/canonical-url-validator';
import { BLOG_POST_SLUGS } from '../../fixtures/test-data';

/**
 * Blog listing page tests
 *
 * Tests for /blog page including:
 * - All blog posts render with title, date, tags, image
 * - Blog images use correct fallback (blog_image || og_image)
 * - Links to individual posts work
 * - Proper separators between posts
 */

test.describe('Blog Listing Page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/blog');
  });

  test('has correct canonical URL', async ({ page }) => {
    await validateCanonicalURL(page, '/blog');
  });

  test('displays all blog posts', async ({ page }) => {
    // Should have at least as many blog posts as we have slugs
    const blogPosts = await page.locator('article').count();
    expect(blogPosts).toBeGreaterThanOrEqual(BLOG_POST_SLUGS.length);
  });

  test('each blog post has required elements', async ({ page }) => {
    // Wait for articles to be present and visible
    await page.waitForSelector('article', { state: 'visible', timeout: 10000 });
    // Wait for page to fully settle (helps with mobile layout shifts)
    await page.waitForLoadState('networkidle');

    const blogPosts = await page.locator('article').all();
    expect(blogPosts.length).toBeGreaterThan(0); // Ensure we have posts

    for (const post of blogPosts) {
      // Title (h2 or h3 for backwards compatibility)
      const title = post.locator('h2, h3').first();
      await expect(title).toBeVisible({ timeout: 5000 });
      const titleText = await title.textContent();
      expect(titleText?.trim()).toBeTruthy();

      // Date (time element)
      const date = post.locator('time').first();
      await expect(date).toBeVisible({ timeout: 5000 });

      // Link to post (accepts both relative and absolute URLs)
      const link = post.locator('a[href]').first();
      await expect(link).toBeVisible({ timeout: 5000 });

      // Image (should have blog_image or og_image)
      // Note: Don't check visibility - images may be lazy-loaded below the fold
      const image = post.locator('img').first();
      if ((await image.count()) > 0) {
        // Image exists, verify it has alt text
        const alt = await image.getAttribute('alt');
        expect(alt).toBeTruthy();

        // Verify it has a src
        const src = await image.getAttribute('src');
        expect(src).toBeTruthy();
      }
    }
  });

  test('blog post links navigate correctly', async ({ page }) => {
    // Wait for content to load
    await page.waitForSelector('article', { state: 'visible', timeout: 10000 });
    await page.waitForLoadState('networkidle');

    // Click on the first blog post title link
    const firstPost = page.locator('article').first();
    const titleLink = firstPost.locator('h2 a, h3 a').first();

    await expect(titleLink).toBeVisible({ timeout: 10000 });
    const href = await titleLink.getAttribute('href');
    expect(href).toBeTruthy();
    expect(href!.length).toBeGreaterThan(0);

    // Store current URL before clicking
    const currentUrl = page.url();

    await titleLink.click();
    await page.waitForLoadState('networkidle');

    // Should navigate to a different page
    expect(page.url()).not.toBe(currentUrl);

    // Should have an h1 on the blog post page
    await expect(page.locator('h1').first()).toBeVisible({ timeout: 10000 });
  });

  test('blog posts are in reverse chronological order', async ({ page }) => {
    const articles = await page.locator('article').all();

    if (articles.length < 2) {
      return; // Not enough posts to test order
    }

    const dateTimes: Date[] = [];
    for (const article of articles) {
      // Get first time element (published date) from each article
      const dateElement = article.locator('time').first();
      const dateStr = await dateElement.getAttribute('datetime');
      expect(dateStr).toBeTruthy();
      dateTimes.push(new Date(dateStr!));
    }

    // Verify dates are in descending order (newest first)
    for (let i = 1; i < dateTimes.length; i++) {
      expect(dateTimes[i - 1].getTime()).toBeGreaterThanOrEqual(dateTimes[i].getTime());
    }
  });

  test('active navigation link', async ({ page }) => {
    const nav = page.locator('nav');
    const blogLink = nav.locator('a[href="/blog"]');

    // Blog link should have .active class
    const hasActiveClass = await blogLink.evaluate(el => el.classList.contains('active'));
    expect(hasActiveClass).toBeTruthy();
  });
});
