import * as fs from 'fs';
import * as path from 'path';
import { glob } from 'glob';
import * as csstree from 'css-tree';
import type { Page } from '@playwright/test';

/**
 * CSS validation result
 */
export interface CSSValidationResult {
  valid: boolean;
  usedButUndefined: string[];
  definedButUnused: string[];
}

/**
 * Extract all CSS class definitions from CSS content using css-tree AST parser.
 * This properly skips comments, strings, and other non-selector contexts.
 */
export function extractDefinedClasses(css: string): Set<string> {
  const definedClasses = new Set<string>();

  const ast = csstree.parse(css);
  csstree.walk(ast, {
    visit: 'ClassSelector',
    enter(node) {
      definedClasses.add(node.name);
    },
  });

  return definedClasses;
}

/**
 * Extract all CSS classes used in PHP/HTML files
 */
export function extractUsedClasses(baseDir: string, patterns: string[]): Set<string> {
  const usedClasses = new Set<string>();

  // Find all matching files
  const files: string[] = [];
  for (const pattern of patterns) {
    const matches = glob.sync(pattern, { cwd: baseDir, absolute: true });
    files.push(...matches);
  }

  for (const file of files) {
    const content = fs.readFileSync(file, 'utf-8');

    // Patterns that assign CSS class names:
    //   HTML/PHP: class="class1 class2"
    //   JS:       className = 'class1 class2'
    //   D3/JS:    .attr('class', 'class1 class2')
    const classPatterns = [
      /class=["']([^"']+)["']/g,
      /className\s*=\s*["']([^"']+)["']/g,
      /\.attr\(\s*["']class["']\s*,\s*["']([^"']+)["']\s*\)/g,
    ];

    for (const classAttrPattern of classPatterns) {
      let match;
      while ((match = classAttrPattern.exec(content)) !== null) {
        const classString = match[1];

        // Split by whitespace to get individual classes
        const classes = classString.split(/\s+/).filter(c => c.length > 0);

        for (const className of classes) {
          // Skip PHP variables, template placeholders, and invalid class names
          // Valid CSS class names must start with letter or underscore
          const isValidClassName = /^[a-zA-Z_][a-zA-Z0-9_-]*$/.test(className);
          const containsPhp = className.includes('<?') || className.includes('$') || className.includes('{');
          // Reject tokens ending in a hyphen: these are truncation artifacts from
          // dynamically-concatenated class names (e.g. PHP `class="foo foo-' . $variant . '"`
          // captures the literal prefix `foo-`). The real class is applied at runtime and
          // is picked up by collectRuntimeClasses instead.
          const isTruncatedPrefix = className.endsWith('-');

          if (isValidClassName && !containsPhp && !isTruncatedPrefix) {
            usedClasses.add(className);
          }
        }
      }
    }
  }

  return usedClasses;
}

/**
 * Collect all CSS classes present in the live DOM by visiting pages with Playwright.
 * This catches classes applied at runtime by JavaScript (classList.add, D3 renders, etc.)
 * that static regex scanning would miss.
 */
export async function collectRuntimeClasses(
  page: Page,
  paths: string[]
): Promise<Set<string>> {
  const classes = new Set<string>();

  const collectFromDOM = () => {
    const result: string[] = [];
    document.querySelectorAll('[class]').forEach(el => {
      el.classList.forEach(c => result.push(c));
    });
    return result;
  };

  for (const pagePath of paths) {
    await page.goto(pagePath, { waitUntil: 'networkidle' });

    // Collect all classes present in the DOM after JS execution
    const pageClasses = await page.evaluate(collectFromDOM);
    pageClasses.forEach(c => classes.add(c));

    // CISSP page: click a model row to trigger cissp-struck toggle
    if (pagePath.includes('small-brains-big-test')) {
      const modelRow = page.locator('#cissp-table-models tbody tr').first();
      if (await modelRow.isVisible()) {
        await modelRow.click();
        await page.waitForTimeout(300);
        const postClickClasses = await page.evaluate(collectFromDOM);
        postClickClasses.forEach(c => classes.add(c));
        // Click again to un-strike (captures both states)
        await modelRow.click();
        await page.waitForTimeout(300);
      }
    }
  }

  return classes;
}

/**
 * Get list of classes to ignore (utility classes, Bootstrap remnants, etc.)
 */
export function getIgnoredClasses(): Set<string> {
  return new Set([
    // Shiki theme color classes (s0-s28, generated dynamically by theme)
    // These are applied as class names but defined as CSS variables
    ...Array.from({ length: 30 }, (_, i) => `s${i}`),

    // Shiki structural classes (generated in highlighted code)
    'line',  // Wrapper for each line in code blocks
    'shiki', // Base shiki marker class (no styles needed)
    'vitesse-dark', // Theme identifier (no styles needed)

    // Bootstrap icon classes (dynamically referenced)
    'bi',

    // Code copy feature class (added only on user click, not detectable at page load)
    'copy-success',

    // SVG-internal classes (appear in SVG files, not CSS concerns)
    'org',
    'w3',

    // Classes applied only during user interaction (not present at page load)
    'show',       // Used in navbar-toggler &.show (mobile menu open)
    'collapsing', // Used in navbar-collapse.collapsing (animation)

    // D3/SVG classes with no CSS definitions — used for JS .selectAll targeting only
    'bar-label',
    'bubble-label',
    'cell-text',
    'col-header',
    'col-label',
    'comb-label',
    'row-label',

    // D3-internal classes (applied automatically by D3 axis/chart rendering)
    'domain',  // SVG axis domain line
    'tick',    // SVG axis tick marks

    // JS container class with no CSS rules
    'cissp-quant-panel',  // Unstyled wrapper div in cissp-charts.js

    // Note: classes previously ignored here are now detected by runtime DOM collection:
    // has-scrollbar, active, abstract, mw-65
  ]);
}

/**
 * Validate CSS usage across the codebase.
 * @param additionalUsedClasses - Optional set of classes found at runtime (e.g. from Playwright DOM collection)
 */
export function validateCSSUsage(
  cssContent: string,
  baseDir: string,
  phpPatterns: string[],
  additionalUsedClasses?: Set<string>
): CSSValidationResult {
  const definedClasses = extractDefinedClasses(cssContent);
  const usedClasses = extractUsedClasses(baseDir, phpPatterns);

  // Merge in runtime-detected classes
  if (additionalUsedClasses) {
    for (const c of additionalUsedClasses) {
      usedClasses.add(c);
    }
  }

  const ignoredClasses = getIgnoredClasses();

  // Find classes used in HTML/PHP but not defined in CSS
  const usedButUndefined = Array.from(usedClasses)
    .filter(c => !definedClasses.has(c) && !ignoredClasses.has(c))
    .sort();

  // Find classes defined in CSS but never used
  const definedButUnused = Array.from(definedClasses)
    .filter(c => !usedClasses.has(c) && !ignoredClasses.has(c))
    .sort();

  return {
    valid: usedButUndefined.length === 0 && definedButUnused.length === 0,
    usedButUndefined,
    definedButUnused,
  };
}

/**
 * Format validation result as a human-readable string
 */
export function formatValidationResult(result: CSSValidationResult): string {
  const lines: string[] = [];

  if (result.usedButUndefined.length > 0) {
    lines.push(`\n🔴 Classes used but not defined in CSS (${result.usedButUndefined.length}):`);
    for (const className of result.usedButUndefined) {
      lines.push(`  - .${className}`);
    }
  }

  if (result.definedButUnused.length > 0) {
    lines.push(`\n⚠️  Classes defined but never used (${result.definedButUnused.length}):`);
    for (const className of result.definedButUnused.slice(0, 20)) {
      lines.push(`  - .${className}`);
    }
    if (result.definedButUnused.length > 20) {
      lines.push(`  ... and ${result.definedButUnused.length - 20} more`);
    }
  }

  if (result.valid) {
    lines.push('\n✅ All CSS classes are properly defined and used');
  }

  return lines.join('\n');
}
