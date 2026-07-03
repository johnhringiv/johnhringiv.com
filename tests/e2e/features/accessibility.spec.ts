import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * Accessibility Testing with axe-core
 *
 * This test suite scans all pages for WCAG 2.1 Level A and AA violations.
 *
 * Note: Some violations are intentionally allowed (e.g., color-contrast on nav links)
 * due to design preferences. These are logged as warnings but don't fail tests.
 */

const PAGES_TO_TEST = [
  { path: '/', name: 'Homepage' },
  { path: '/research', name: 'Research' },
  { path: '/blog', name: 'Blog Listing' },
  { path: '/press', name: 'Press' },
  { path: '/ncc-not-complete-but-capable', name: 'Blog Post (NCC)' },
  { path: '/secure-scalable-home-web-hosting', name: 'Blog Post (Hosting)' },
  { path: '/when-five-plus-five-equals-eleven', name: 'Blog Post (5+5=11)' },
  { path: '/a_subtle_python_threading_bug', name: 'Blog Post (Python Threading)' },
];

// Violations to allow (will be logged as warnings instead of failures)
const ALLOWED_VIOLATIONS = [
  'color-contrast', // Nav links and badges have intentional low contrast for aesthetic
];

test.describe('Accessibility (axe-core)', () => {
  for (const { path, name } of PAGES_TO_TEST) {
    test(`${name} should not have critical accessibility violations`, async ({ page }) => {
      await page.goto(path);

      const accessibilityScanResults = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
        .analyze();

      // Separate violations into critical and allowed
      const criticalViolations = accessibilityScanResults.violations.filter(
        (violation) => !ALLOWED_VIOLATIONS.includes(violation.id)
      );

      const allowedViolations = accessibilityScanResults.violations.filter(
        (violation) => ALLOWED_VIOLATIONS.includes(violation.id)
      );

      // Log allowed violations as warnings
      if (allowedViolations.length > 0) {
        console.log(`\n⚠️  ${name} - Allowed violations (design choices):`);
        allowedViolations.forEach((violation) => {
          console.log(`   - ${violation.id}: ${violation.description}`);
          console.log(`     Impact: ${violation.impact}`);
          console.log(`     Affected elements: ${violation.nodes.length}`);
        });
      }

      // Fail on critical violations
      if (criticalViolations.length > 0) {
        console.log(`\n❌ ${name} - Critical accessibility violations:`);
        criticalViolations.forEach((violation) => {
          console.log(`\n   ${violation.id}: ${violation.description}`);
          console.log(`   Impact: ${violation.impact}`);
          console.log(`   Help: ${violation.helpUrl}`);
          console.log(`   Affected elements (${violation.nodes.length}):`);
          violation.nodes.forEach((node, i) => {
            console.log(`     ${i + 1}. ${node.html}`);
            console.log(`        ${node.failureSummary}`);
          });
        });
      }

      expect(criticalViolations).toHaveLength(0);
    });
  }

  test('Full page scan with detailed reporting', async ({ page }) => {
    await page.goto('/');

    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
      .analyze();

    // Report statistics
    console.log('\n📊 Accessibility Scan Summary (Homepage):');
    console.log(`   ✅ Passes: ${results.passes.length}`);
    console.log(`   ⚠️  Violations: ${results.violations.length}`);
    console.log(`   ℹ️  Incomplete: ${results.incomplete.length}`);

    // Critical violations only
    const critical = results.violations.filter(
      (v) => !ALLOWED_VIOLATIONS.includes(v.id)
    );
    expect(critical).toHaveLength(0);
  });

  test('Specific checks - Links have accessible names', async ({ page }) => {
    await page.goto('/');

    const results = await new AxeBuilder({ page })
      .include('a') // Only check anchor tags
      .withRules(['link-name']) // Only check link-name rule
      .analyze();

    expect(results.violations).toHaveLength(0);
  });

  test('Specific checks - Images have alt text', async ({ page }) => {
    await page.goto('/');

    const results = await new AxeBuilder({ page })
      .include('img')
      .withRules(['image-alt'])
      .analyze();

    expect(results.violations).toHaveLength(0);
  });

  test.skip('Specific checks - Form elements have labels', async ({ page }) => {
    // Site currently has no forms - skip this test
  });

  test('Specific checks - Heading hierarchy', async ({ page }) => {
    await page.goto('/');

    const results = await new AxeBuilder({ page })
      .withRules(['heading-order'])
      .analyze();

    expect(results.violations).toHaveLength(0);
  });

  test('Specific checks - Landmark regions', async ({ page }) => {
    await page.goto('/');

    const results = await new AxeBuilder({ page })
      .withRules(['region'])
      .analyze();

    // Filter out allowed violations
    const critical = results.violations.filter(
      (v) => !ALLOWED_VIOLATIONS.includes(v.id)
    );
    expect(critical).toHaveLength(0);
  });

  test('Mobile accessibility', async ({ page }) => {
    // Set mobile viewport
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/');

    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa'])
      .analyze();

    const critical = results.violations.filter(
      (v) => !ALLOWED_VIOLATIONS.includes(v.id)
    );

    if (critical.length > 0) {
      console.log('\n📱 Mobile-specific violations:');
      critical.forEach((v) => {
        console.log(`   - ${v.id}: ${v.description}`);
      });
    }

    expect(critical).toHaveLength(0);
  });
});

test.describe('Keyboard Navigation', () => {
  test('Interactive elements can receive focus', async ({ page, browserName }, testInfo) => {
    // Skip on mobile browsers (no keyboard)
    if (testInfo.project.name === 'mobile-chrome' || testInfo.project.name === 'tablet') {
      test.skip();
      return;
    }

    await page.goto('/');

    // Test that navigation links can be focused
    const navLinks = await page.locator('a.nav-link').all();
    expect(navLinks.length).toBeGreaterThan(0);

    for (const link of navLinks) {
      await link.focus();
      const isFocused = await link.evaluate((el) => el === document.activeElement);
      expect(isFocused).toBe(true);
    }
  });

  test('No tabindex violations', async ({ page }) => {
    await page.goto('/');

    const results = await new AxeBuilder({ page })
      .withRules(['tabindex'])
      .analyze();

    expect(results.violations).toHaveLength(0);
  });
});

test.describe('Screen Reader Support', () => {
  test('ARIA roles and attributes are valid', async ({ page }) => {
    await page.goto('/');

    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'best-practice'])
      .withRules([
        'aria-valid-attr',
        'aria-valid-attr-value',
        'aria-allowed-attr',
        'aria-required-attr',
      ])
      .analyze();

    expect(results.violations).toHaveLength(0);
  });

  test('Page has valid lang attribute', async ({ page }) => {
    await page.goto('/');

    const results = await new AxeBuilder({ page })
      .withRules(['html-has-lang', 'html-lang-valid'])
      .analyze();

    expect(results.violations).toHaveLength(0);
  });
});
