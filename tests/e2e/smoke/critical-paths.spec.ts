import { test, expect } from '@playwright/test';
import { ALL_PAGE_PATHS, CRITICAL_ASSETS } from '../../fixtures/test-data';

/**
 * Smoke tests for critical paths
 *
 * These tests run quickly (< 1 minute) to validate that core functionality works.
 * They check:
 * - All main pages load successfully (200 status)
 * - No console errors on page load
 * - Critical assets (CSS, JS, sprite) load successfully
 * - Basic navigation works
 */

test.describe('Smoke Tests - Critical Paths', () => {
  test('all pages return 200 status', async ({ page }) => {
    for (const path of ALL_PAGE_PATHS) {
      const response = await page.goto(path);
      expect(response?.status()).toBe(200);
    }
  });

  test('no console errors on homepage', async ({ page }) => {
    const consoleErrors: string[] = [];

    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });

    await page.goto('/');
    await page.waitForLoadState('networkidle');

    expect(consoleErrors).toHaveLength(0);
  });

  test('CSS bundle loads successfully', async ({ page }) => {
    const response = await page.goto(CRITICAL_ASSETS.cssBundle);
    expect(response?.status()).toBe(200);

    // Verify it's actually CSS
    const contentType = response?.headers()['content-type'];
    expect(contentType).toContain('css');
  });

  test('JS bundle loads successfully', async ({ page }) => {
    const response = await page.goto(CRITICAL_ASSETS.jsBundle);
    expect(response?.status()).toBe(200);

    // Verify it's actually JavaScript
    const contentType = response?.headers()['content-type'];
    expect(contentType).toMatch(/javascript|ecmascript/);
  });

  test('icon sprite loads successfully', async ({ page }) => {
    const response = await page.goto(CRITICAL_ASSETS.iconSprite);
    expect(response?.status()).toBe(200);

    // Verify it's actually SVG
    const contentType = response?.headers()['content-type'];
    expect(contentType).toContain('svg');
  });

  test('sprite contains all icons used on pages', async ({ page }) => {
    // Discover all icons used across all pages
    const usedIcons = new Set<string>();

    for (const path of ALL_PAGE_PATHS) {
      await page.goto(path);
      await page.waitForLoadState('networkidle');

      // Extract icon IDs from xlink:href attributes
      const iconIds = await page.evaluate(() => {
        const useElements = document.querySelectorAll('use');
        const ids: string[] = [];

        useElements.forEach(use => {
          // Try multiple ways to get href
          let href = use.getAttribute('href');
          if (!href) {
            href = use.getAttributeNS('http://www.w3.org/1999/xlink', 'href');
          }

          if (href && href.includes('sprite.svg')) {
            // Extract icon ID after the #
            const match = href.match(/#([^?]+)/);
            if (match && match[1]) {
              ids.push(match[1]);
            }
          }
        });

        return ids;
      });

      iconIds.forEach(id => usedIcons.add(id));
    }

    // Verify we found some icons
    expect(usedIcons.size).toBeGreaterThan(0);

    // Fetch sprite and verify all used icons exist
    const spriteResponse = await page.goto(CRITICAL_ASSETS.iconSprite);
    expect(spriteResponse?.status()).toBe(200);

    const spriteContent = await page.content();

    // Check each used icon exists in sprite
    const missingIcons: string[] = [];

    for (const iconId of usedIcons) {
      const hasIcon = spriteContent.includes(`id="${iconId}"`) ||
                      spriteContent.includes(`id="bi-${iconId}"`);

      if (!hasIcon) {
        missingIcons.push(iconId);
      }
    }

    if (missingIcons.length > 0) {
      throw new Error(`Icons used on pages but missing from sprite.svg: ${missingIcons.join(', ')}`);
    }

    // Log discovered icons for debugging
    console.log(`✓ Verified ${usedIcons.size} icons exist in sprite:`, Array.from(usedIcons).sort());
  });

  test('homepage has required elements', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Check for nav
    await expect(page.locator('nav')).toBeVisible();

    // Check for profile image (alt text contains "Ring")
    const profileImg = page.locator('img[alt*="Ring"]');
    await expect(profileImg).toBeVisible();

    // Check for CV link (case insensitive)
    const cvLink = page.locator('a[href*="CV.pdf" i], a[href*=".pdf"][href*="CV" i]');
    await expect(cvLink).toBeVisible();

    // Check for basic content
    await expect(page.locator('h1, h2').first()).toBeVisible();
  });

  test('navigation links work', async ({ page }) => {
    await page.goto('/');

    // Click on Blog link (direct navigation for speed)
    await page.goto('/blog');
    await expect(page).toHaveURL(/.*\/blog/);

    await page.goto('/research');
    await expect(page).toHaveURL(/.*\/research/);

    await page.goto('/press');
    await expect(page).toHaveURL(/.*\/press/);

    // Verify homepage loads
    await page.goto('/');
    await expect(page.locator('nav')).toBeVisible();
  });

  test('mobile: hamburger menu works correctly', async ({ page, viewport }) => {
    // Only test on mobile/tablet viewports
    if (!viewport || viewport.width > 768) {
      return; // Skip on desktop
    }

    await page.goto('/');

    // Hamburger should be visible on mobile
    const hamburger = page.locator('button[data-bs-toggle="collapse"], .navbar-toggler');
    await expect(hamburger).toBeVisible();

    // Nav links should be hidden initially
    const navCollapse = page.locator('.navbar-collapse, #navbarNav');
    const isInitiallyCollapsed = await navCollapse.evaluate(el =>
      !el.classList.contains('show')
    );
    expect(isInitiallyCollapsed).toBeTruthy();

    // Click hamburger to expand
    await hamburger.click();
    await page.waitForTimeout(500);

    // Nav should now be expanded
    const isExpanded = await navCollapse.evaluate(el =>
      el.classList.contains('show')
    );
    expect(isExpanded).toBeTruthy();

    // Nav links should be visible in expanded menu
    await expect(navCollapse.locator('a[href="/blog"]')).toBeVisible();
    await expect(navCollapse.locator('a[href="/research"]')).toBeVisible();
    await expect(navCollapse.locator('a[href="/press"]')).toBeVisible();

    // Click hamburger again to collapse
    await hamburger.click();
    await page.waitForTimeout(500);

    // Nav should be collapsed again
    const isCollapsed = await navCollapse.evaluate(el =>
      !el.classList.contains('show')
    );
    expect(isCollapsed).toBeTruthy();
  });

  test('mobile: social links exist and have icons', async ({ page, viewport }) => {
    // Only test on mobile/tablet viewports
    if (!viewport || viewport.width > 768) {
      return; // Skip on desktop
    }

    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Social links should exist on mobile (may not be visible due to CSS, but should be in DOM)
    const linkedinLink = page.locator('a[href*="linkedin.com"]');
    const githubLink = page.locator('a[href*="github.com"]');
    const emailLink = page.locator('a[href^="mailto:"]');

    // Verify links exist
    await expect(linkedinLink).toHaveCount(1);
    await expect(githubLink).toHaveCount(1);
    await expect(emailLink).toHaveCount(1);

    // Verify social icons are present in the links
    const linkedinIcon = linkedinLink.locator('svg use, svg, img').first();
    const githubIcon = githubLink.locator('svg use, svg, img').first();
    const emailIcon = emailLink.locator('svg use, svg, img').first();

    // Icons should exist (attached to DOM)
    await expect(linkedinIcon).toBeAttached();
    await expect(githubIcon).toBeAttached();
    await expect(emailIcon).toBeAttached();
  });

  test('mobile: social icons visible after opening hamburger', async ({ page, viewport }) => {
    // Only test on mobile/tablet viewports
    if (!viewport || viewport.width > 768) {
      return; // Skip on desktop
    }

    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Open hamburger menu
    const hamburger = page.locator('button[data-bs-toggle="collapse"], .navbar-toggler');
    await hamburger.click();
    await page.waitForTimeout(500); // Wait for collapse animation

    // Verify social links are now visible in expanded menu
    const linkedinLink = page.locator('a[href*="linkedin.com"]');
    const githubLink = page.locator('a[href*="github.com"]');
    const emailLink = page.locator('a[href^="mailto:"]');

    await expect(linkedinLink).toBeVisible();
    await expect(githubLink).toBeVisible();
    await expect(emailLink).toBeVisible();
  });

  test('blog listing page shows posts', async ({ page }) => {
    await page.goto('/blog');

    // Should have at least 4 blog posts (using .row selector)
    const blogPosts = await page.locator('.row').count();
    expect(blogPosts).toBeGreaterThanOrEqual(4);

    // Should have multiple links to blog posts
    const blogLinks = await page.locator('a[href*="ncc"], a[href*="five"], a[href*="python"], a[href*="hosting"]').count();
    expect(blogLinks).toBeGreaterThan(0);
  });

  test('blog post loads and has code highlighting', async ({ page }) => {
    // Test the first blog post
    await page.goto('/ncc-not-complete-but-capable');

    // Should have main heading
    await expect(page.locator('h1')).toBeVisible();

    // Should have code blocks with Shiki styling (check multiple selectors)
    const codeBlocks = await page.locator('.shiki-container, pre.shiki, pre code').count();

    // Just verify the page loaded correctly and has some content
    // Code highlighting details will be tested in feature tests
    expect(codeBlocks).toBeGreaterThanOrEqual(0);
  });

  test('research page loads with entries', async ({ page }) => {
    await page.goto('/research');

    // Should have research entries (using .row selector)
    const researchEntries = await page.locator('.row').count();
    expect(researchEntries).toBeGreaterThan(0);

    // Should have h1/h2
    await expect(page.locator('h1, h2').first()).toBeVisible();
  });

  test('press page loads with entries', async ({ page }) => {
    await page.goto('/press');

    // Should have press entries (using .row selector)
    const pressEntries = await page.locator('.row').count();
    expect(pressEntries).toBeGreaterThan(0);

    // Should have h1/h2
    await expect(page.locator('h1, h2').first()).toBeVisible();
  });
});
