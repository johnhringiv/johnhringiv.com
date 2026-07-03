import { test, expect } from '@playwright/test';
import { BLOG_POST_SLUGS, BLOG_POST_METADATA } from '../../fixtures/test-data';

/**
 * Interactive Chart Tests
 *
 * Tests for blog posts with data-driven interactive content:
 * - Extra CSS/JS assets load successfully
 * - PHP→JS data pipeline works (window globals populated)
 * - Chart containers render content (not empty divs)
 * - JS-populated tables have data rows
 */

test.describe('Interactive Charts', () => {
  for (const slug of BLOG_POST_SLUGS) {
    const metadata = BLOG_POST_METADATA[slug as keyof typeof BLOG_POST_METADATA];
    if (!('interactiveCharts' in metadata)) continue;

    const charts = (metadata as typeof metadata & {
      interactiveCharts: {
        extraCss: readonly string[];
        extraJs: readonly string[];
        windowGlobals: readonly string[];
        chartIds: readonly string[];
        dataTableIds: readonly string[];
      };
    }).interactiveCharts;

    test.describe(`${slug}`, () => {

      // Asset loading tests don't need page navigation
      for (const css of charts.extraCss) {
        test(`extra CSS ${css} loads`, async ({ request }) => {
          const resp = await request.get(css);
          expect(resp.ok()).toBeTruthy();
          const content = await resp.text();
          expect(content.length).toBeGreaterThan(0);
        });
      }

      for (const js of charts.extraJs) {
        test(`extra JS ${js} loads`, async ({ request }) => {
          const resp = await request.get(js);
          expect(resp.ok()).toBeTruthy();
          const content = await resp.text();
          expect(content.length).toBeGreaterThan(0);
        });
      }

      test('chart data is loaded from database', async ({ page }) => {
        await page.goto(`/${slug}`);

        for (const name of charts.windowGlobals) {
          const value = await page.evaluate(
            (n) => (window as Record<string, unknown>)[n],
            name
          );
          expect(value, `window.${name} should not be null`).not.toBeNull();
        }
      });

      test('chart containers render content', async ({ page }) => {
        await page.goto(`/${slug}`);

        for (const id of charts.chartIds) {
          const container = page.locator(`#${id}`);
          await expect(container).toBeVisible();

          // Chart JS renders SVG/div children; use evaluate since
          // Playwright locators don't reliably match SVG children
          const childCount = await container.evaluate(el => el.children.length);
          expect(childCount, `#${id} should have rendered content`).toBeGreaterThan(0);
        }
      });

      test('data tables are populated by JS', async ({ page }) => {
        await page.goto(`/${slug}`);

        for (const id of charts.dataTableIds) {
          const firstRow = page.locator(`#${id} tbody tr`).first();
          await expect(firstRow).toBeAttached({ timeout: 5000 });

          // Should have more than one row (multiple data entries)
          const rowCount = await page.locator(`#${id} tbody tr`).count();
          expect(rowCount, `#${id} should have multiple data rows`).toBeGreaterThan(1);
        }
      });
    });
  }
});
