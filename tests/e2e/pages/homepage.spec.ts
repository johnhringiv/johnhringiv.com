import { test, expect } from '@playwright/test';
import { validateCanonicalURL } from '../../utils/canonical-url-validator';
import { SOCIAL_ICONS, CRITICAL_ASSETS } from '../../fixtures/test-data';

/**
 * Homepage-specific tests
 *
 * Tests for the main landing page (/) including:
 * - Profile image with responsive srcset
 * - Lead paragraph content
 * - CV PDF link
 * - Social media icons
 */

test.describe('Homepage', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
  });

  test('has correct canonical URL', async ({ page }) => {
    await validateCanonicalURL(page, '/');
  });

  test('displays profile image with responsive srcset', async ({ page }) => {
    // Find profile image (should have alt text with "John")
    const profileImage = page.locator('img[alt*="John"]').first();
    await expect(profileImage).toBeVisible();

    // Should have srcset for responsive images
    const srcset = await profileImage.getAttribute('srcset');
    expect(srcset).toBeTruthy();
    expect(srcset).toContain('w'); // Should have width descriptors (e.g., "400w")

    // Should have src attribute
    const src = await profileImage.getAttribute('src');
    expect(src).toBeTruthy();
    expect(src).toMatch(/\.(jpg|jpeg|png|webp)(?:\?.*)?$/i);
  });

  test('has CV download link', async ({ page, request }) => {
    // Find any link with text containing CV, resume, or curriculum
    const cvLink = page.locator('a:has-text("CV"), a:has-text("Resume"), a:has-text("Curriculum")').first();
    await expect(cvLink).toBeVisible();

    // Verify the link actually works (returns 200)
    const href = await cvLink.getAttribute('href');
    expect(href).toBeTruthy();

    // Check that the PDF exists and is accessible (use request API to avoid download trigger)
    const baseURL = page.url();
    const pdfUrl = new URL(href!, baseURL).toString();
    const response = await request.get(pdfUrl);
    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('pdf');
  });

  test('displays social media icons', async ({ page }) => {
    // Wait for page to settle
    await page.waitForLoadState('networkidle');

    for (const social of SOCIAL_ICONS) {
      const link = page.locator(`a[href="${social.href}"]`);

      // Verify link exists (may not be visible on mobile if in collapsed footer)
      expect(await link.count()).toBeGreaterThan(0);

      // Verify icon is present (either img or svg)
      const hasIcon = await link.locator('img, svg').count();
      expect(hasIcon).toBeGreaterThan(0);
    }
  });

  test('navigation is visible and functional', async ({ page }) => {
    const nav = page.locator('nav');
    await expect(nav).toBeVisible();

    // Home nav link should have .active class (not navbar-brand logo)
    const homeLink = nav.locator('.nav-link[href="/"]');
    const hasActiveClass = await homeLink.evaluate(el => el.classList.contains('active'));
    expect(hasActiveClass).toBeTruthy();
  });

});
