import { test, expect } from '@playwright/test';
import {
  expectValidHTML,
  expectValidHTMLNoWarnings,
  validateSemanticHTML,
  validateHeadingHierarchy,
  validateImageAltText,
  validateDoctype,
  validateNoDeprecatedElements
} from '../../utils/html-validator';
import { ALL_PAGE_PATHS } from '../../fixtures/test-data';

/**
 * HTML validation feature tests
 *
 * CRITICAL FOR STANDARDS COMPLIANCE - These tests ensure valid HTML
 *
 * Tests include:
 * - Valid HTML5 structure (W3C validator)
 * - Semantic HTML (nav, main, article, section)
 * - Heading hierarchy (no skipped levels)
 * - All images have alt text
 * - No deprecated elements
 *
 * NOTE: HTML validation is browser-independent, so all tests only run on chromium
 * to avoid unnecessary duplication across mobile-chrome and tablet projects.
 */

// All HTML validation tests only run on chromium (browser-independent)
test.describe('HTML Validation', () => {
  test.beforeEach(async ({}, testInfo) => {
    if (testInfo.project.name !== 'chromium') {
      test.skip();
    }
  });

test.describe('HTML Validation - W3C Compliance', () => {
  // Test ALL pages with local validator.nu (no rate limits!)
  // To run local validator: java -Xss1024k -cp ~/vnu.jar nu.validator.servlet.Main 8888
  const PAGES_TO_VALIDATE = ALL_PAGE_PATHS;

  for (const path of PAGES_TO_VALIDATE) {
    test(`${path} passes W3C HTML validation (no errors)`, async ({ page }) => {
      await page.goto(path);
      await page.waitForLoadState('networkidle');

      // This will throw if validation fails
      await expectValidHTML(page);
    });
  }
});

test.describe('HTML Validation - No Warnings', () => {
  // Strict validation: no errors AND no warnings (test all pages)
  const PAGES_TO_VALIDATE_STRICT = ALL_PAGE_PATHS;

  for (const path of PAGES_TO_VALIDATE_STRICT) {
    test(`${path} passes W3C validation with NO warnings`, async ({ page }) => {
      await page.goto(path);
      await page.waitForLoadState('networkidle');

      // This will throw if there are any errors OR warnings
      await expectValidHTMLNoWarnings(page);
    });
  }
});

test.describe('HTML Validation - Semantic Structure', () => {
  test('all pages have proper semantic elements', async ({ page }) => {
    for (const path of ALL_PAGE_PATHS) {
      await page.goto(path);

      await validateSemanticHTML(page);
    }
  });

  test('all pages have exactly one main element', async ({ page }) => {
    for (const path of ALL_PAGE_PATHS) {
      await page.goto(path);

      const mainCount = await page.locator('main').count();
      expect(mainCount).toBe(1);
    }
  });

  test('all pages have nav element', async ({ page }) => {
    for (const path of ALL_PAGE_PATHS) {
      await page.goto(path);

      const navCount = await page.locator('nav').count();
      expect(navCount).toBeGreaterThan(0);
    }
  });

  test('blog posts use article element', async ({ page }) => {
    await page.goto('/blog');

    const articleCount = await page.locator('article').count();
    expect(articleCount).toBeGreaterThan(0);
  });
});

test.describe('HTML Validation - Heading Hierarchy', () => {
  // W3C validator checks for skipped heading levels (covered by "No Warnings" test)
  // But W3C allows multiple H1s, so we enforce exactly one H1 here
  test('all pages have exactly one h1', async ({ page }) => {
    for (const path of ALL_PAGE_PATHS) {
      await page.goto(path);

      const h1Count = await page.locator('h1').count();
      expect(h1Count).toBe(1);
    }
  });
});

// Image Alt Text validation is covered by W3C validator (errors for missing alt)

// DOCTYPE validation is covered by W3C validator

// Deprecated elements validation is covered by W3C validator (errors)

// Lang attribute validation is covered by W3C validator (info/warning)

test.describe('HTML Validation - Meta Charset', () => {
  test('all pages have UTF-8 charset', async ({ page }) => {
    for (const path of ALL_PAGE_PATHS) {
      await page.goto(path);

      const charset = await page.locator('meta[charset]').getAttribute('charset');
      expect(charset?.toLowerCase()).toBe('utf-8');
    }
  });
});

test.describe('HTML Validation - Viewport Meta', () => {
  test('all pages have viewport meta tag', async ({ page }) => {
    for (const path of ALL_PAGE_PATHS) {
      await page.goto(path);

      const viewport = await page.locator('meta[name="viewport"]').getAttribute('content');
      expect(viewport).toBeTruthy();
      expect(viewport).toContain('width=device-width');
    }
  });
});

}); // Close parent HTML Validation describe block
