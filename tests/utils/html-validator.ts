import { Page, expect } from '@playwright/test';
import validator from 'html-validator';

/**
 * HTML validation result
 */
export interface HTMLValidationResult {
  valid: boolean;
  errors: Array<{
    type: string;
    message: string;
    extract: string;
    line: number;
    column: number;
  }>;
  warnings: Array<{
    type: string;
    message: string;
    extract: string;
    line: number;
    column: number;
  }>;
}

/**
 * Validates HTML markup using W3C HTML validator
 */
export async function validateHTML(page: Page): Promise<HTMLValidationResult> {
  const html = await page.content();

  // Use local validator.nu at http://localhost:8888
  const result = await validator({
    data: html,
    format: 'json',
    validator: 'http://localhost:8888'
  }) as any;

  // Filter out false positives from the W3C CSS validator not supporting oklch()
  const isOklchFalsePositive = (m: any) =>
    m.message && m.message.includes('oklch(');

  const errors = result.messages.filter((m: any) => m.type === 'error' && !isOklchFalsePositive(m));
  const warnings = result.messages.filter((m: any) => m.type === 'info' && !isOklchFalsePositive(m));

  return {
    valid: errors.length === 0,
    errors: errors.map((e: any) => ({
      type: e.type,
      message: e.message,
      extract: e.extract || '',
      line: e.lastLine || 0,
      column: e.lastColumn || 0
    })),
    warnings: warnings.map((w: any) => ({
      type: w.type,
      message: w.message,
      extract: w.extract || '',
      line: w.lastLine || 0,
      column: w.lastColumn || 0
    }))
  };
}

/**
 * Validates HTML and expects it to be valid (no errors)
 */
export async function expectValidHTML(page: Page) {
  const result = await validateHTML(page);

  if (!result.valid) {
    const path = new URL(page.url()).pathname;
    const errorMessages = result.errors.map(e => {
      // Truncate long messages
      const msg = e.message.length > 80 ? e.message.substring(0, 80) + '...' : e.message;
      return `Line ${e.line}: ${msg}`;
    }).join('; ');

    throw new Error(`${path} - ${errorMessages}`);
  }

  return result;
}

/**
 * Validates HTML and expects it to be valid with NO errors and NO warnings
 */
export async function expectValidHTMLNoWarnings(page: Page) {
  const result = await validateHTML(page);

  const allIssues = [...result.errors, ...result.warnings];

  if (allIssues.length > 0) {
    const path = new URL(page.url()).pathname;
    const issueMessages = allIssues.map(issue => {
      // Truncate long messages
      const msg = issue.message.length > 80 ? issue.message.substring(0, 80) + '...' : issue.message;
      return `Line ${issue.line}: ${msg}`;
    }).join('; ');

    throw new Error(`${path} - ${issueMessages}`);
  }

  return result;
}

/**
 * Validates semantic HTML structure
 */
export async function validateSemanticHTML(page: Page) {
  // Check for required semantic elements
  const nav = await page.locator('nav').count();
  const main = await page.locator('main').count();

  expect(nav).toBeGreaterThan(0);
  expect(main).toBeGreaterThan(0);

  // Main should be present exactly once
  expect(main).toBe(1);
}

/**
 * Validates heading hierarchy (no skipped levels)
 */
export async function validateHeadingHierarchy(page: Page) {
  // Get all headings
  const headings = await page.locator('h1, h2, h3, h4, h5, h6').all();

  if (headings.length === 0) {
    return; // No headings to validate
  }

  const levels: number[] = [];
  for (const heading of headings) {
    const tagName = await heading.evaluate(el => el.tagName.toLowerCase());
    const level = parseInt(tagName.substring(1));
    levels.push(level);
  }

  // There should be exactly one h1
  const h1Count = levels.filter(l => l === 1).length;
  expect(h1Count).toBe(1);

  // Check for skipped levels
  for (let i = 1; i < levels.length; i++) {
    const currentLevel = levels[i];
    const prevLevel = levels[i - 1];

    // Heading can be same level, one level deeper, or jump back up
    // But should not skip levels when going deeper
    if (currentLevel > prevLevel) {
      const diff = currentLevel - prevLevel;
      expect(diff).toBeLessThanOrEqual(1);
    }
  }
}

/**
 * Validates that all images have alt text
 */
export async function validateImageAltText(page: Page) {
  // Get all img elements
  const images = await page.locator('img').all();

  for (const img of images) {
    const alt = await img.getAttribute('alt');

    // Alt attribute must exist (can be empty for decorative images)
    expect(alt).not.toBeNull();
  }
}

/**
 * Validates that all links have meaningful text
 */
export async function validateLinkText(page: Page) {
  // Get all links
  const links = await page.locator('a').all();

  for (const link of links) {
    const text = await link.textContent();
    const ariaLabel = await link.getAttribute('aria-label');

    // Link should have text content or aria-label
    const hasText = text && text.trim().length > 0;
    const hasAriaLabel = ariaLabel && ariaLabel.trim().length > 0;

    expect(hasText || hasAriaLabel).toBeTruthy();

    // Avoid generic link text (if text is present)
    if (hasText && text) {
      const genericTexts = ['click here', 'read more', 'more', 'here', 'link'];
      const lowerText = text.toLowerCase().trim();

      // Allow generic text if there's additional context via aria-label
      if (genericTexts.includes(lowerText) && !hasAriaLabel) {
        throw new Error(`Link has generic text "${text}" without aria-label`);
      }
    }
  }
}

/**
 * Validates proper DOCTYPE declaration
 */
export async function validateDoctype(page: Page) {
  const doctype = await page.evaluate(() => {
    if (document.doctype) {
      return {
        name: document.doctype.name,
        publicId: document.doctype.publicId,
        systemId: document.doctype.systemId
      };
    }
    return null;
  });

  expect(doctype).toBeTruthy();
  expect(doctype?.name).toBe('html');
}

/**
 * Validates that there are no deprecated HTML elements
 */
export async function validateNoDeprecatedElements(page: Page) {
  const deprecatedElements = ['font', 'center', 'marquee', 'blink', 'strike', 'big', 'tt'];

  for (const element of deprecatedElements) {
    const count = await page.locator(element).count();
    expect(count).toBe(0);
  }
}

/**
 * Comprehensive HTML validation including structure, semantics, and accessibility
 */
export async function validateHTMLComprehensive(page: Page): Promise<HTMLValidationResult> {
  // W3C validation
  const result = await validateHTML(page);

  // Only proceed with other checks if basic HTML is valid
  if (result.valid) {
    await validateDoctype(page);
    await validateSemanticHTML(page);
    await validateHeadingHierarchy(page);
    await validateImageAltText(page);
    await validateNoDeprecatedElements(page);
  }

  return result;
}
