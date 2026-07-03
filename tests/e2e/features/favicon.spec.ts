import { test, expect } from '@playwright/test';
import { checkImageExists } from '../../utils/image-validator';

/**
 * Favicon & web-app-manifest asset tests.
 *
 * The SEO suite's validateFavicon only asserts that an icon <link> tag exists
 * with a valid extension — it never fetches the files. These tests fetch every
 * referenced icon/manifest asset and assert it resolves, guarding the trimmed
 * favicon set and the "SVGs off Git LFS" migration against silent 404s.
 */
test.describe('Favicon and Manifest assets', () => {
  test('all favicon / apple-touch / manifest <link> hrefs resolve (200)', async ({ page, baseURL }) => {
    await page.goto('/');
    const base = baseURL || 'http://localhost:8080';

    const hrefs = await page
      .locator('link[rel="icon"], link[rel="apple-touch-icon"], link[rel="manifest"]')
      .evaluateAll(els => els.map(e => (e as HTMLLinkElement).getAttribute('href') || ''));

    expect(hrefs.length, 'expected favicon/manifest <link> tags in <head>').toBeGreaterThan(0);

    const broken: string[] = [];
    for (const href of hrefs) {
      if (!href) { broken.push('(empty href)'); continue; }
      if (!(await checkImageExists(page.request, href, base))) broken.push(href);
    }
    expect(broken, `head asset link(s) returned non-200: ${broken.join(', ')}`).toEqual([]);
  });

  test('a vector (SVG) favicon is present and served as SVG', async ({ page, baseURL }) => {
    await page.goto('/');
    const base = baseURL || 'http://localhost:8080';

    const svgHref = await page
      .locator('link[rel="icon"][type="image/svg+xml"]')
      .getAttribute('href');
    expect(svgHref, 'expected a <link rel="icon" type="image/svg+xml">').toBeTruthy();

    const resp = await page.request.get(`${base}${svgHref}`);
    expect(resp.status()).toBe(200);
    expect(resp.headers()['content-type'] || '').toContain('svg');
  });

  test('web manifest is valid JSON and all its icons resolve (200)', async ({ page, baseURL }) => {
    await page.goto('/');
    const base = baseURL || 'http://localhost:8080';

    const manifestHref = await page.locator('link[rel="manifest"]').getAttribute('href');
    expect(manifestHref, 'expected a <link rel="manifest">').toBeTruthy();

    const resp = await page.request.get(`${base}${manifestHref}`);
    expect(resp.status(), 'manifest should return 200').toBe(200);

    const manifest: { icons?: { src: string }[] } = JSON.parse(await resp.text());

    expect(Array.isArray(manifest.icons) && manifest.icons!.length > 0, 'manifest should list icons').toBeTruthy();

    const broken: string[] = [];
    for (const icon of manifest.icons!) {
      if (!(await checkImageExists(page.request, icon.src, base))) broken.push(icon.src);
    }
    expect(broken, `manifest icon URL(s) returned non-200: ${broken.join(', ')}`).toEqual([]);
  });
});
