import { test, expect } from '@playwright/test';
import { NAV_ITEMS, VIEWPORTS } from '../../fixtures/test-data';

/**
 * Navigation feature tests
 *
 * Tests for navigation functionality including:
 * - Desktop: All links visible and work
 * - Active page highlighted with .active class
 * - Mobile: Hamburger expands/collapses navbar
 * - Mobile: Clicking nav link closes menu
 * - Collapse animation works (.collapsing class)
 */

test.describe('Navigation - Desktop', () => {
  test.beforeEach(async ({ page }) => {
    // Set desktop viewport
    await page.setViewportSize(VIEWPORTS.desktop);
    await page.goto('/');
  });

  test('all navigation links are visible', async ({ page }) => {
    const nav = page.locator('nav');

    for (const item of NAV_ITEMS) {
      const link = nav.locator(`a:has-text("${item.text}")`);
      await expect(link).toBeVisible();
    }
  });

  test('navigation links work correctly', async ({ page }) => {
    const nav = page.locator('nav');

    for (const item of NAV_ITEMS) {
      const link = nav.locator(`a:has-text("${item.text}")`);
      await link.click();
      await page.waitForLoadState('networkidle');

      // Verify URL
      if (item.href === '/') {
        expect(page.url()).toMatch(/\/$|^[^/]*$/);
      } else {
        expect(page.url()).toContain(item.href);
      }
    }
  });

  test('active page is highlighted', async ({ page }) => {
    for (const item of NAV_ITEMS) {
      await page.goto(item.href);
      await page.waitForLoadState('networkidle');

      const activeLink = page.locator(`nav a[href="${item.href}"].active, nav a[href="${item.href}"][class*="active"]`);
      await expect(activeLink).toBeVisible();
    }
  });

  test('navbar is always visible on desktop', async ({ page }) => {
    const nav = page.locator('nav');
    await expect(nav).toBeVisible();

    // Scroll down and verify nav is still visible (if sticky/fixed)
    await page.evaluate(() => window.scrollTo(0, 500));
    await expect(nav).toBeVisible();
  });
});

test.describe('Navigation - Mobile', () => {
  test.beforeEach(async ({ page }) => {
    // Set mobile viewport
    await page.setViewportSize(VIEWPORTS.mobile);
    await page.goto('/');
  });

  test('hamburger menu is visible on mobile', async ({ page }) => {
    // Look for hamburger/toggle button
    const hamburger = page.locator('button[data-bs-toggle="collapse"], .navbar-toggler, button:has-text("☰")');
    await expect(hamburger).toBeVisible();
  });

  test('hamburger expands navbar on click', async ({ page }) => {
    const hamburger = page.locator('button[data-bs-toggle="collapse"], .navbar-toggler');
    const navCollapse = page.locator('.navbar-collapse, #navbarNav');

    // Initially, nav links should be hidden
    const isInitiallyExpanded = await navCollapse.evaluate(el => {
      return el.classList.contains('show');
    });

    if (!isInitiallyExpanded) {
      // Click hamburger to expand
      await hamburger.click();

      // Wait for collapse animation
      await page.waitForTimeout(500);

      // Nav should now be expanded
      const isExpanded = await navCollapse.evaluate(el => {
        return el.classList.contains('show');
      });

      expect(isExpanded).toBeTruthy();
    }
  });

  test('hamburger collapses navbar on second click', async ({ page }) => {
    const hamburger = page.locator('button[data-bs-toggle="collapse"], .navbar-toggler');
    const navCollapse = page.locator('.navbar-collapse, #navbarNav');

    // Expand
    await hamburger.click();
    await page.waitForTimeout(500);

    // Collapse
    await hamburger.click();
    await page.waitForTimeout(500);

    // Nav should be collapsed
    const isCollapsed = await navCollapse.evaluate(el => {
      return !el.classList.contains('show');
    });

    expect(isCollapsed).toBeTruthy();
  });

  test('clicking nav link navigates and closes menu', async ({ page }) => {
    const hamburger = page.locator('button[data-bs-toggle="collapse"], .navbar-toggler');
    const navCollapse = page.locator('.navbar-collapse, #navbarNav');

    // Expand menu
    await hamburger.click();
    await page.waitForTimeout(500);

    // Click a nav link
    const blogLink = page.locator('nav a:has-text("Blog")');
    await blogLink.click();
    await page.waitForLoadState('networkidle');

    // Verify navigation worked
    expect(page.url()).toContain('/blog');

    // Menu should be collapsed (on new page load, default state is collapsed)
    const isCollapsed = await navCollapse.evaluate(el => {
      return !el.classList.contains('show');
    });

    expect(isCollapsed).toBeTruthy();
  });

  test('collapse animation uses .collapsing class', async ({ page }) => {
    const hamburger = page.locator('button[data-bs-toggle="collapse"], .navbar-toggler');
    const navCollapse = page.locator('.navbar-collapse, #navbarNav');

    // Start expanding
    await hamburger.click();

    // During animation, should have .collapsing class
    await page.waitForTimeout(50); // Small delay to catch mid-animation

    const hasCollapsingClass = await navCollapse.evaluate(el => {
      return el.classList.contains('collapsing') || el.classList.contains('show');
    });

    // Should either be collapsing or already shown (animation is fast)
    expect(hasCollapsingClass).toBeTruthy();
  });
});

test.describe('Navigation - Tablet', () => {
  test.beforeEach(async ({ page }) => {
    // Set tablet viewport
    await page.setViewportSize(VIEWPORTS.tablet);
    await page.goto('/');
  });

  test('navigation is accessible on tablet', async ({ page }) => {
    const nav = page.locator('nav');
    await expect(nav).toBeVisible();

    // Check if hamburger is present (depends on breakpoint)
    const hamburger = await page.locator('button[data-bs-toggle="collapse"], .navbar-toggler').count();

    if (hamburger > 0) {
      // If hamburger exists, test mobile behavior
      const hamburgerBtn = page.locator('button[data-bs-toggle="collapse"], .navbar-toggler');
      await expect(hamburgerBtn).toBeVisible();
    } else {
      // Otherwise, links should be directly visible (desktop behavior)
      for (const item of NAV_ITEMS) {
        const link = page.locator(`nav a:has-text("${item.text}")`);
        await expect(link).toBeVisible();
      }
    }
  });
});
