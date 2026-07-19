import { test, expect, Browser } from '@playwright/test';
import * as path from 'path';
import { validateCSSUsage, formatValidationResult, collectSiteData, SiteCSSData } from '../../utils/css-validator';
import { ALL_PAGE_PATHS } from '../../fixtures/test-data';

/**
 * CSS Validation Tests
 *
 * These tests ensure that:
 * 1. All CSS classes used in HTML/PHP are defined in the served CSS
 * 2. No orphaned utility classes (like Bootstrap fs-2) are used
 * 3. Dead CSS is identified (defined but never used)
 *
 * Classes are detected from two sources:
 * - Static: regex scanning of PHP/JS source files (checked into git)
 * - Runtime: Playwright DOM collection after JS execution (catches classList.add,
 *   D3, and server-included generated content like shiki HTML)
 *
 * The CSS itself is fetched over HTTP from the container under test — never read
 * from the local working tree — so the suite validates exactly what ships and can
 * run on a clean CI checkout with no locally built www/generated/ files.
 */

const baseDir = path.resolve(__dirname, '../../../');

// Patterns for source files to scan for CSS class usage. Generated content
// (shiki HTML, mermaid SVGs) is not scanned from disk — it is rendered into the
// pages server-side and picked up by runtime DOM collection instead.
const sourcePatterns = [
  'www/**/*.php',
  'data/**/*.php',   // html_description / caption fields contain markup with classes
  'src/js/**/*.js',  // JS that applies classes dynamically
];

// All site pages to visit for runtime class collection.
// Single source of truth: tests/fixtures/test-data.ts (covers main pages + every blog post).
const sitePages = [...ALL_PAGE_PATHS];

// One site crawl per worker: both describe blocks share the collected DOM
// classes and fetched CSS instead of re-visiting every page.
let siteDataPromise: Promise<SiteCSSData> | undefined;

function getSiteData(browser: Browser): Promise<SiteCSSData> {
  siteDataPromise ??= (async () => {
    const page = await browser.newPage();
    try {
      return await collectSiteData(page, sitePages);
    } finally {
      await page.close();
    }
  })();
  return siteDataPromise;
}

test.describe('CSS Class Validation', () => {
  let siteData: SiteCSSData;

  test.beforeAll(async ({ browser }) => {
    siteData = await getSiteData(browser);
  });

  test('should have all used classes defined in CSS', async () => {
    const result = validateCSSUsage(siteData.cssContent, baseDir, sourcePatterns, siteData.runtimeClasses);

    if (result.usedButUndefined.length > 0) {
      console.log(formatValidationResult(result));

      // Fail with detailed message
      throw new Error(
        `Found ${result.usedButUndefined.length} CSS classes used in HTML/PHP but not defined in served CSS:\n` +
        result.usedButUndefined.map(c => `  - .${c}`).join('\n') +
        '\n\nThese may be orphaned Bootstrap classes or typos. Either:\n' +
        '  1. Define them in src/css/main.css\n' +
        '  2. Remove them from HTML/PHP files\n' +
        '  3. Add to ignored classes in css-validator.ts if they are dynamic'
      );
    }

    expect(result.usedButUndefined).toHaveLength(0);
  });

  test('should report dead CSS (defined but unused)', async () => {
    const result = validateCSSUsage(siteData.cssContent, baseDir, sourcePatterns, siteData.runtimeClasses);

    if (result.definedButUnused.length > 0) {
      // Emit warning in format that gets captured by test-release-candidate.sh
      console.log(`⚠️  Classes defined but never used (${result.definedButUnused.length}): ${result.definedButUnused.slice(0, 20).join(', ')}${result.definedButUnused.length > 20 ? ` ... and ${result.definedButUnused.length - 20} more` : ''}`);

      // Detailed output for developers
      console.log(formatValidationResult(result));
      console.log('\nℹ️  This is informational - not a failure');
      console.log('Consider removing unused classes to reduce bundle size.');
    }

    // This is informational only - we don't fail the test
    // But we log it so developers can clean up dead CSS
    expect(result.definedButUnused).toBeDefined();
  });

  test('should provide full CSS validation report', async () => {
    const result = validateCSSUsage(siteData.cssContent, baseDir, sourcePatterns, siteData.runtimeClasses);

    console.log('\n📊 CSS Validation Report');
    console.log('========================');
    console.log(formatValidationResult(result));
    console.log('\nSummary:');
    console.log(`  - Used but undefined: ${result.usedButUndefined.length}`);
    console.log(`  - Defined but unused: ${result.definedButUnused.length}`);

    // Only fail if there are used but undefined classes
    if (!result.valid && result.usedButUndefined.length > 0) {
      throw new Error('CSS validation failed - see report above');
    }
  });
});

test.describe('Specific Class Checks', () => {
  let siteData: SiteCSSData;

  test.beforeAll(async ({ browser }) => {
    siteData = await getSiteData(browser);
  });

  test('should not use orphaned Bootstrap fs-* classes', async () => {
    const result = validateCSSUsage(siteData.cssContent, baseDir, sourcePatterns, siteData.runtimeClasses);

    // Check for Bootstrap font-size utility classes that aren't defined
    const orphanedFsClasses = result.usedButUndefined.filter(c => /^fs-[0-9]$/.test(c));

    if (orphanedFsClasses.length > 0) {
      throw new Error(
        `Found orphaned Bootstrap fs-* classes: ${orphanedFsClasses.map(c => `.${c}`).join(', ')}\n` +
        'These are Bootstrap utility classes that are no longer loaded.\n' +
        'Either define them in src/css/main.css or remove them from PHP files.'
      );
    }

    expect(orphanedFsClasses).toHaveLength(0);
  });

  test('should not use orphaned Bootstrap spacing classes', async () => {
    const result = validateCSSUsage(siteData.cssContent, baseDir, sourcePatterns, siteData.runtimeClasses);

    // Check for common Bootstrap spacing classes (m-*, p-*, mt-*, etc.)
    // that might be used but not defined
    const bootstrapSpacingPattern = /^[mp][tblrxy]?-[0-9]$/;
    const orphanedSpacingClasses = result.usedButUndefined.filter(c =>
      bootstrapSpacingPattern.test(c)
    );

    if (orphanedSpacingClasses.length > 0) {
      console.log(
        `⚠️  Orphaned Bootstrap spacing classes (${orphanedSpacingClasses.length}): ${orphanedSpacingClasses.map(c => `.${c}`).join(', ')}`
      );
      console.log('These should be defined in src/css/main.css if needed.');
    }

    // This is a soft warning - log but don't fail
    // Many spacing utilities are actually defined and used
  });
});
