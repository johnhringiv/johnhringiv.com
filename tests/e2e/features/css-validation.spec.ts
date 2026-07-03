import { test, expect } from '@playwright/test';
import * as path from 'path';
import * as fs from 'fs';
import { globSync } from 'glob';
import { validateCSSUsage, formatValidationResult, collectRuntimeClasses } from '../../utils/css-validator';
import { ALL_PAGE_PATHS } from '../../fixtures/test-data';

/**
 * CSS Validation Tests
 *
 * These tests ensure that:
 * 1. All CSS classes used in HTML/PHP are defined in the generated CSS
 * 2. No orphaned utility classes (like Bootstrap fs-2) are used
 * 3. Dead CSS is identified (defined but never used)
 *
 * Classes are detected from two sources:
 * - Static: regex scanning of PHP/HTML/JS source files
 * - Runtime: Playwright DOM collection after JS execution (catches classList.add, D3, etc.)
 */

/** Read and concatenate all generated CSS files (bundle.css + any extra stylesheets) */
function getAllGeneratedCSS(baseDir: string): string {
  const cssFiles = globSync('www/generated/*.css', { cwd: baseDir });
  return cssFiles
    .map(f => fs.readFileSync(path.join(baseDir, f), 'utf-8'))
    .join('\n');
}

const baseDir = path.resolve(__dirname, '../../../');

// Patterns for source files to scan for CSS class usage
const sourcePatterns = [
  'www/**/*.php',
  'www/includes/**/*.php',
  'www/data/**/*.php',
  'www/generated/highlighted-shiki/**/*.html',  // Generated syntax-highlighted code
  'src/js/**/*.js',                             // JS that applies classes dynamically
];

// All site pages to visit for runtime class collection.
// Single source of truth: tests/fixtures/test-data.ts (covers main pages + every blog post).
const sitePages = [...ALL_PAGE_PATHS];

test.describe('CSS Class Validation', () => {
  let runtimeClasses: Set<string>;

  test.beforeAll(async ({ browser }) => {
    const page = await browser.newPage();
    runtimeClasses = await collectRuntimeClasses(page, sitePages);
    await page.close();
  });

  test('should have all used classes defined in CSS', async () => {
    const cssContent = getAllGeneratedCSS(baseDir);

    const result = validateCSSUsage(cssContent, baseDir, sourcePatterns, runtimeClasses);

    if (result.usedButUndefined.length > 0) {
      console.log(formatValidationResult(result));

      // Fail with detailed message
      throw new Error(
        `Found ${result.usedButUndefined.length} CSS classes used in HTML/PHP but not defined in generated CSS:\n` +
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
    const cssContent = getAllGeneratedCSS(baseDir);

    const result = validateCSSUsage(cssContent, baseDir, sourcePatterns, runtimeClasses);

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
    const cssContent = getAllGeneratedCSS(baseDir);

    const result = validateCSSUsage(cssContent, baseDir, sourcePatterns, runtimeClasses);

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
  let runtimeClasses: Set<string>;

  test.beforeAll(async ({ browser }) => {
    const page = await browser.newPage();
    runtimeClasses = await collectRuntimeClasses(page, sitePages);
    await page.close();
  });

  test('should not use orphaned Bootstrap fs-* classes', async () => {
    const cssContent = getAllGeneratedCSS(baseDir);

    const result = validateCSSUsage(cssContent, baseDir, sourcePatterns, runtimeClasses);

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
    const cssContent = getAllGeneratedCSS(baseDir);

    const result = validateCSSUsage(cssContent, baseDir, sourcePatterns, runtimeClasses);

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
