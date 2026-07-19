# Playwright E2E Test Suite

Comprehensive end-to-end testing for johnhringiv.com using Playwright.

## Testing Philosophy

This test suite follows a **comprehensive end-to-end testing approach**:

1. **Test the user experience, not implementation details** - Tests interact with pages as users would
2. **Automatic discovery over manual maintenance** - Tests auto-discover content (icons, images, AVIF files) to reduce maintenance burden
3. **Fail fast with clear errors** - Detailed error messages show exactly what's broken and how to fix it
4. **SEO and accessibility as first-class concerns** - Critical tests for canonical URLs, HTML validation, semantic markup
5. **Performance validation** - Tests ensure AVIF images exist, srcsets are generated, lazy loading works
6. **Parallelization for speed** - 60 concurrent test executions (20 workers × 3 browser projects)

## Quick Start

### Docker Testing (Before Deployment)

Build and test Docker image with HTML validation:

```bash
npm run test:docker
```

This:
- Starts HTML validator (Docker container, `ghcr.io/validator/validator` on :8888)
- Builds the Docker image
- Runs all tests against port 8082 (override with `PORT=8083 npm run test:docker` if taken)
- Cleans up

**Fails immediately if:**
- Docker is not installed
- Validator fails to start

The **same suite runs in CI** (`.github/workflows/ci.yml`) on every push/PR,
against the freshly built container plus the validator sidecar. No spec reads
built assets from `www/generated/` on disk — everything is fetched over HTTP
from the container — so a clean CI checkout is sufficient.

### Local Development Testing

Test against JetBrains server (port 8080):

```bash
# First-time setup: Install browsers
npx playwright install firefox webkit

# Install system dependencies (prompts for sudo when needed)
npx playwright install-deps

# Run all tests
npm run test:e2e

# Quiet mode (only show failures + summary)
npm run test:e2e:quiet

# Run smoke tests only (fast validation)
npm run test:smoke

# Run with UI mode (interactive debugging)
npm run test:e2e:ui

# View test report in browser
npm run test:report
```

## Test Structure

```
tests/
├── e2e/
│   ├── smoke/              # Fast smoke tests (<1 min)
│   │   └── critical-paths.spec.ts
│   ├── pages/              # Page-specific tests
│   │   ├── homepage.spec.ts
│   │   ├── blog-listing.spec.ts
│   │   ├── blog-posts.spec.ts
│   │   ├── research.spec.ts
│   │   └── press.spec.ts
│   └── features/           # Feature/component tests
│       ├── navigation.spec.ts
│       ├── seo.spec.ts
│       ├── canonical-urls.spec.ts (CRITICAL FOR SEO)
│       ├── image-generation.spec.ts
│       ├── html-validation.spec.ts
│       ├── interactive.spec.ts
│       ├── feed.spec.ts
│       └── responsive.spec.ts
├── fixtures/
│   └── test-data.ts        # Shared test data
└── utils/                  # Reusable validators
    ├── seo-validator.ts
    ├── canonical-url-validator.ts
    ├── html-validator.ts
    └── image-validator.ts
```

## Test Categories

### Smoke Tests (42 tests)
Fast validation that core functionality works. Run before every deployment.

**Key Tests:**
- All pages return 200 status
- No console errors
- CSS/JS/icon bundles load
- Navigation works (desktop + mobile)
- Hamburger menu functionality (mobile/tablet)
- Social links present with icons
- **Dynamic icon sprite validation** - automatically discovers icons from pages and verifies they exist in sprite
- Blog posts display
- Research and press entries load

### Critical Tests for SEO

#### Canonical URLs (`features/canonical-urls.spec.ts`)
**WHY CRITICAL:** Prevents duplicate content penalties from search engines.

**Tests:**
- Every page has canonical URL
- Clean URLs (no .php extension)
- Absolute URLs with production domain
- Canonical matches current page path
- Docker vs Production canonical URLs match (paths identical, only domain differs)
- Feed canonical URLs correct

#### HTML Validation (`features/html-validation.spec.ts`)
**WHY CRITICAL:** Search engines and screen readers require valid markup.

**Validator:** `scripts/test-simple.sh` (and CI) start it automatically as a
Docker container. To start it manually:
```bash
docker run -d --name validator -p 8888:8888 ghcr.io/validator/validator
```
Point tests at a different instance with `VALIDATOR_URL` (default
`http://localhost:8888`).

**Tests:**
- W3C HTML5 validation (no errors)
- **W3C validation with NO warnings** (strict mode)
- Semantic HTML (nav, main, footer, article)
- Heading hierarchy (no skipped levels)
- All images have alt text
- No deprecated elements
- Proper DOCTYPE and lang attribute

#### SEO Metadata (`features/seo.spec.ts`)
**Tests:**
- Meta description (55-160 chars)
- Open Graph tags (type, title, description, url, image)
- Twitter Card tags
- Favicon and feed links

### Image Generation Tests

**WHY CRITICAL:** Ensures optimal performance and no broken images.

**Tests:**
- Responsive srcset generation (multiple sizes, AVIF format)
- Cache busting (?v=MD5hash)
- All srcset URLs return 200 (no 404s)
- Open Graph image dimensions (1200x630)
- Image modal integration
- Lazy loading for below-fold images

**Image Sources:**
- Blog post images (from PageInfo og_image)
- Research publication images (from `data/research.php`)
- Press coverage images (from `data/press.php`, except `in_a_flash.jpg` which uses special handling)
- Homepage profile image

The build script (`scripts/generate_images_build.php`) automatically scans both PHP files and data files to discover all images and pre-generate AVIF versions during Docker build.

### Interactive Features

**Tests:**
- Image modal (click to open, click outside to close, hover preload)
- Code copy button (clipboard, visual feedback)
- Collapse/expand animation (.collapsing class)

### CSP Violation Detection (`features/csp-violations.spec.ts`)

**WHY CRITICAL:** Content Security Policy violations can indicate security issues like inline styles, unsafe scripts, or unauthorized resource loading.

**Setup (one-time):**
```bash
npx playwright install firefox webkit
npx playwright install-deps
```

**Tests (Firefox only - stricter CSP enforcement than Chrome):**
- All pages load without CSP violations
- Social icons don't cause violations (SVG inline styles)
- Interactive elements don't cause violations (modals, navbar)

**Why Firefox?** Firefox enforces CSP more strictly than Chrome, catching violations that Chrome might silently ignore (e.g., inline `style=""` attributes in SVGs).

### Feed/RSS Validation (`features/feed.spec.ts`)

**WHY CRITICAL:** Ensures content syndication works correctly for RSS readers and aggregators.

**Tests:**
- **Valid Atom 1.0 XML** (well-formed, no parse errors)
- **Atom spec compliance:**
  - Required feed elements (id, title, updated, author)
  - Self-link and alternate link correct
  - Entry IDs unique and use tag: URI scheme
  - Valid ISO 8601 dates
- **Content quality:**
  - Required fields present in all entries
  - Entries sorted by date descending
  - Media RSS thumbnails with valid URLs
- **SEO:**
  - All content URLs use HTTPS (excludes namespace/schema URLs like w3.org which are allowed to be http://)
  - No .php extensions in links
  - Icon, logo, and rights statement present

**Total:** 63 tests across all viewports (chromium, mobile-chrome, tablet)

### Sitemap Validation (`features/sitemap.spec.ts`)

**WHY CRITICAL:** Search engines use sitemap.xml to discover and index all pages.

**Tests:**
- **Valid XML structure:**
  - Well-formed XML (no parse errors)
  - Correct sitemap schema namespace
  - Proper urlset root element
- **URL completeness:**
  - All main pages included (home, blog, research, press)
  - Blog posts included
  - No duplicate URLs
- **URL quality:**
  - All URLs use production domain (https://johnhringiv.com)
  - No .php extensions
  - All URLs use HTTPS
- **Required fields:**
  - All URLs have `<loc>` element
  - Valid `<lastmod>` dates (ISO 8601)
  - Appropriate `<priority>` values (0.0-1.0)
- **Priority values:**
  - Homepage has highest priority (≥0.9)
  - Main sections have good priority (≥0.6)
  - Priorities make sense for content type

**Total:** 51 tests across all viewports (chromium, mobile-chrome, tablet)

### Responsive Design

**Viewports tested:**
- Mobile (375x667)
- Tablet (768x1024)
- Laptop (1366x768)
- Desktop (1920x1080)

**Tests:**
- No horizontal overflow
- Navbar behavior (hamburger on mobile)
- Images scale correctly
- Text is readable

## Docker Testing Workflow

### Automated

```bash
npm run test:docker
```

Builds image, runs tests on port 8082, cleans up automatically.

### Manual

```bash
# Build Docker image
docker build -t johnhringiv.com:latest .

# Start container on port 8082
docker run -d -p 8082:8080 --name test-container johnhringiv.com:latest

# Run tests
npm run test:e2e

# Cleanup
docker stop test-container && docker rm test-container
```

## Recent Changes

### Semantic HTML Tags Added
- `<main>` wraps page content (www/includes/top.php and footer.php)
- `<footer>` wraps copyright section (www/includes/footer.php)

**Benefits:**
- Better SEO (search engines understand structure)
- Improved accessibility (screen readers can navigate)
- W3C HTML5 compliance
- More reliable test selectors

### Dynamic Icon Sprite Validation
The icon sprite test now **automatically discovers** which icons are used on pages, then verifies they exist in the sprite. No manual maintenance required!

**How it works:**
1. Scans all pages for `<use xlink:href="...sprite.svg#icon-name">` elements
2. Extracts icon IDs
3. Verifies each icon exists in sprite.svg
4. Fails with clear error if any icons are missing

### Mobile-Specific Tests
- Hamburger menu expand/collapse functionality
- Social links presence and icon validation
- Mobile viewport-specific behavior

## Configuration

### Environment Variables
```bash
BASE_URL=http://localhost:8083       # Container under test (default http://localhost:8082)
VALIDATOR_URL=http://localhost:8888  # W3C Nu validator instance (default shown)
```

### Playwright Config (playwright.config.ts)
- Base URL: http://localhost:8082 (overridable via BASE_URL)
- **Workers: 20 locally, 4 in CI**
- Retries: 0 local (fail fast), 2 in CI
- Projects: chromium (primary), mobile-chrome, tablet, firefox (CSP testing), webkit (Safari compatibility)
- Trace: on first retry
- Screenshots/videos: only on failure
- **Fully parallel**: All tests run in parallel across workers and projects

### Test Performance & Parallelization

**How Parallelization Works:**
- Tests run across **3 browser projects** (chromium, mobile-chrome, tablet)
- Each project runs tests in parallel using **20 workers**
- Total parallelization: **60 concurrent test executions** (20 workers × 3 projects)

**Performance Benchmarks:**
- **Full test suite** (732 tests): ~1-2 minutes with 20 workers
- **Smoke tests only**: <30 seconds
- **Single project** (chromium only): ~40 seconds

**Tuning Performance:**
```bash
# Single project for fastest feedback
npm run test:e2e -- --project=chromium
```

## Best Practices

1. **Run smoke tests** before deploying - they're fast (<1 min) and catch major issues
2. **Compare Docker vs Production** before deployment using canonical URL tests
3. **Use headed mode** for debugging failing tests: `npm run test:e2e:headed`
4. **Check test reports** for detailed failure info: `npm run test:report`

## Test Data Management

All test data centralized in `tests/fixtures/test-data.ts`:
- Blog post slugs
- Expected canonical URLs
- Social media links
- Navigation items
- Critical asset paths

**Why centralized?**
- Single source of truth
- Easy to update when content changes
- Prevents test duplication
- Self-documenting

## Adding New Content

When you add new content to the site, update the test data to ensure comprehensive coverage.

### Adding a New Blog Post

**Steps:**

1. **Create the blog post PHP file** (`www/my-new-post.php`)
2. **Update test data** in `tests/fixtures/test-data.ts`:

```typescript
// Add slug to BLOG_POST_SLUGS
export const BLOG_POST_SLUGS = [
  'ncc-not-complete-but-capable',
  'when-five-plus-five-equals-eleven',
  'a_subtle_python_threading_bug',
  'secure-scalable-home-web-hosting',
  'my-new-post'  // ← Add here
] as const;

// Add canonical URL to EXPECTED_CANONICAL_URLS
export const EXPECTED_CANONICAL_URLS: Record<string, string> = {
  // ... existing entries ...
  '/my-new-post': 'https://johnhringiv.com/my-new-post'  // ← Add here
};

// Add metadata to BLOG_POST_METADATA
export const BLOG_POST_METADATA = {
  // ... existing entries ...
  'my-new-post': {  // ← Add here
    hasCodeBlocks: true,      // Set to true if post has code snippets
    hasMermaidDiagrams: false // Set to true if post has Mermaid diagrams
  }
} as const;
```

3. **Run tests** to verify:
```bash
npm run test:smoke  # Quick validation
npm run test:e2e    # Full test suite
```

**What gets tested automatically:**
- ✅ Page returns 200 status (smoke tests)
- ✅ Canonical URL is correct (canonical-urls.spec.ts)
- ✅ Meta tags present (seo.spec.ts)
- ✅ HTML is valid (html-validation.spec.ts)
- ✅ Open Graph image exists and is 1200x630 (image-generation.spec.ts)
- ✅ Code blocks have copy buttons (if hasCodeBlocks: true)
- ✅ Images have AVIF versions in srcset (image-generation.spec.ts)
- ✅ Appears in blog listing (blog-listing.spec.ts)
- ✅ Appears in sitemap (sitemap.spec.ts)
- ✅ Appears in feed (feed.spec.ts)

### Adding a New Main Page

**Steps:**

1. **Create the PHP file** (`www/my-page.php`)
2. **Update test data** in `tests/fixtures/test-data.ts`:

```typescript
// Add to ALL_PAGE_PATHS (before blog posts)
export const ALL_PAGE_PATHS = [
  '/',
  '/blog',
  '/research',
  '/press',
  '/my-page',  // ← Add here
  ...BLOG_POST_SLUGS.map(slug => `/${slug}`)
] as const;

// Add canonical URL
export const EXPECTED_CANONICAL_URLS: Record<string, string> = {
  // ... existing entries ...
  '/my-page': 'https://johnhringiv.com/my-page'  // ← Add here
};
```

3. **Add to navigation** (if applicable) in `tests/fixtures/test-data.ts`:

```typescript
export const NAV_ITEMS = [
  { text: 'Home', href: '/' },
  { text: 'Blog', href: '/blog' },
  { text: 'Research', href: '/research' },
  { text: 'Press', href: '/press' },
  { text: 'My Page', href: '/my-page' }  // ← Add here
] as const;
```

4. **Run tests** to verify

### Adding Images

**No test updates needed!** The image tests automatically discover:
- All images with `srcset` attributes
- All AVIF files referenced in srcsets
- All Open Graph images

Just ensure:
- You use `responsiveImage()` helper for content images
- You set `og_image` in PageInfo
- You run Docker build to generate AVIF versions

### Adding New Icons

**No test updates needed!** The icon sprite test automatically:
- Discovers all `<use xlink:href="...sprite.svg#icon-name">` elements
- Verifies each icon exists in sprite.svg

Just ensure you run `./scripts/generate-sprite.sh` or Docker build to regenerate the sprite.

### Adding Social Media Links

Update `tests/fixtures/test-data.ts`:

```typescript
export const SOCIAL_ICONS = [
  { name: 'LinkedIn', href: 'https://www.linkedin.com/in/johnhringiv' },
  { name: 'GitHub', href: 'https://github.com/johnhringiv?tab=repositories' },
  { name: 'Email', href: 'mailto:johnhringiv@gmail.com' },
  { name: 'Mastodon', href: 'https://mastodon.social/@johnhringiv' }  // ← Add here
] as const;
```

### Testing Checklist for New Content

When adding any new content, verify:

- [ ] Page added to appropriate test data arrays
- [ ] Canonical URL defined in EXPECTED_CANONICAL_URLS
- [ ] Smoke tests pass (`npm run test:smoke`)
- [ ] HTML validation passes (no errors or warnings)
- [ ] Open Graph image is 1200x630
- [ ] All images have AVIF versions (check Docker build logs)
- [ ] Page appears in sitemap.xml
- [ ] Page/content appears in feed (if applicable)
- [ ] Full test suite passes (`npm run test:e2e`)

## Test Warnings

Tests can emit warnings for non-fatal issues that should be reviewed but don't fail tests.

**Current warnings:**
- **Image dimensions** - og:image not at ideal 1200x630
- **Unused CSS classes** - Dead code that increases bundle size
- **Orphaned Bootstrap classes** - Used but not defined

**Adding warnings:**
Use `console.log` with ⚠️ emoji in test files:

```typescript
if (actualWidth !== 1200) {
  console.log(`⚠️  Page ${path} has og:image at ${actualWidth}x${actualHeight} (ideal: 1200x630)`);
}
```

Warnings appear in Playwright's native output.

## Troubleshooting

### Tests fail with "Port already in use"
Another service is using the test port. Run `PORT=8083 npm run test:docker`, or
for a manually started container set `BASE_URL=http://localhost:<port>`.

### Icon sprite test fails
The test automatically discovers icons from pages. If it fails, an icon is referenced on a page but missing from sprite.svg. Check the error message for which icon is missing.

## CI/CD Integration

The full suite runs in CI on every push/PR — see `.github/workflows/ci.yml`.
The `checks` job:

1. Checks out with full git history + LFS (the image build needs both)
2. Installs npm deps and all three Playwright browser engines (cached)
3. Starts the W3C Nu validator container (`ghcr.io/validator/validator` on :8888)
4. Builds the production Docker image and starts it on :8082
5. Runs `npx playwright test` — the complete suite, all browser projects,
   against the final container (no locally built `www/generated/` files needed)
6. Uploads the HTML report as a workflow artifact on failure

The publish job (GHCR `:latest`) only runs if `checks` passes, so nothing
reaches production without the full suite passing.

## Support

For issues or questions:
- Check test output: Tests provide detailed error messages
- View HTML report: `npm run test:report`
- Debug interactively: `npm run test:e2e:ui`
- Check screenshots: `test-results/` directory
