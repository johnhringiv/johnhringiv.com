import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';
import { globSync } from 'glob';

test.describe('Internal URLs - Relative Paths', () => {
  test('all internal URLs should use relative paths', async () => {
    const phpFiles = globSync('www/**/*.php', {
      ignore: ['**/vendor/**', '**/node_modules/**']
    });

    const violations: string[] = [];
    const DOMAIN_PATTERN = /href=["']https?:\/\/(www\.)?johnhringiv\.com\//gi;

    for (const file of phpFiles) {
      const content = fs.readFileSync(file, 'utf-8');
      const matches = content.match(DOMAIN_PATTERN);

      if (matches) {
        // Exception: feed.php needs absolute URLs for Atom spec
        if (file.includes('feed.php')) continue;

        violations.push(`${file}: Found ${matches.length} absolute internal URLs`);
        matches.forEach(match => violations.push(`  - ${match}`));
      }
    }

    expect(violations).toEqual([]);
  });

  test('external URLs should use https protocol', async () => {
    // Verify external links use secure protocol
    const phpFiles = globSync('www/**/*.php');
    const violations: string[] = [];
    const HTTP_PATTERN = /href=["']http:\/\/(?!localhost|127\.0\.0\.1)/gi;

    for (const file of phpFiles) {
      const content = fs.readFileSync(file, 'utf-8');
      const matches = content.match(HTTP_PATTERN);

      if (matches) {
        violations.push(`${file}: Found insecure HTTP links`);
        matches.forEach(match => violations.push(`  - ${match}`));
      }
    }

    expect(violations).toEqual([]);
  });
});
