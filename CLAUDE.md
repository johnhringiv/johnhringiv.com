# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Contribution Workflow (IMPORTANT)
- **All changes go on a branch and are squash-merged into `main`** — never commit directly to `main`.
- **Never `git push` without explicit approval.** Prepare commits locally and ask before pushing or opening a PR.
- `main` is continuously deployed (see [Deployment Model](#deployment-model-cicd)), so anything merged ships to production within one poll cycle — this is why review-before-push matters.

## Project Overview
Personal website for John H. Ring IV built with PHP, vanilla CSS (modern features, no Bootstrap), and minimal JavaScript. The site runs in a Docker container with Nginx and PHP-FPM using Alpine Linux.

## Development Environment
- **Operating System**: WSL (Windows Subsystem for Linux)
- **IDE**: JetBrains development server running on port 8080
- **Docker Testing**: Release candidate images tested on port 8082
  - Compare against production (https://johnhringiv.com) to check for breakage
- **Local Production Environment**: http://192.168.55.246:8080/
  - Full production stack with HAProxy (compression enabled)
  - Use this to test production configuration without Cloudflare
- **Production**: https://johnhringiv.com (behind Cloudflare)

### Chrome DevTools MCP (Browser Testing)
The Chrome DevTools MCP allows Claude to interact with the browser for visual testing, taking snapshots, and verifying the site renders correctly.

**Setup (one-time):**
```bash
# Add the MCP to Claude Code (user scope) with isolation for security
claude mcp add chrome-devtools --scope user -- npx chrome-devtools-mcp@latest --headless=true --isolated=true

# Install Google Chrome in WSL (required - MCP looks for /opt/google/chrome/chrome)
wget https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb
sudo dpkg -i google-chrome-stable_current_amd64.deb
sudo apt-get install -f  # Fix any missing dependencies
```

**About `--isolated` flag:**
- Creates a temporary user-data-dir that is automatically cleaned up after the browser is closed
- Recommended for security - prevents cross-contamination between sessions
- Each session starts fresh without saved credentials or browsing history

**Claude Instructions:**
- Use the Chrome DevTools MCP tools to visually verify Docker builds and test site functionality
- If the MCP fails with "Could not find Google Chrome executable", inform the user they need to run the Chrome installation commands above
- Prefer `take_snapshot` over `take_screenshot` for faster text-based verification
- Use `navigate_page`, `click`, `fill` for interactive testing

## Key Development Commands

### Local Development Setup (WSL/Linux)
```bash
# Install Node.js (if not present)
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/master/install.sh | bash
nvm install node

# Install dependencies
npm ci

# Generate icon sprite for development
./scripts/generate_sprite.sh

# Watch CSS and JS changes, rebuild bundles automatically
npm run watch
```

### Build Commands
```bash
# Development build (CSS + JS bundles, with source maps)
npm run build:dev

# Production build (CSS + JS bundles, minified/prefixed)
npm run build:prod

# Docker production build
docker build -t johnhringiv.com:latest .
docker run -p 8082:8080 johnhringiv.com:latest
```

**Bundles created in `www/generated/`:**
- `bundle.css` - Built from `src/css/main.css` (vanilla CSS with modern features)
- `bundle.js` - Bundled from `src/js/main.js` (imports collapse, zoom_detection, image_modal, code_copy)

### Docker Testing

Simple script to build and test Docker images before deployment:

```bash
npm run test:docker
# or: ./scripts/test-simple.sh
```

**What it does:**
1. Starts HTML validator (`ghcr.io/validator/validator` Docker container on port 8888; reused if already running)
2. Builds Docker image
3. Starts container on port 8082
4. Runs the Playwright E2E suite across every page (HTML validation, SEO/metadata, image generation, code highlighting, interactive features, feed + sitemap, responsive design, CSS validation — full list under **Test coverage** below)
5. Shows results
6. Cleans up (container + validator)

**Requirements:**
- Docker (also used for the validator and the app image) — no Java needed

**Test coverage:**
- HTML validation (W3C, no errors/warnings)
- SEO metadata (canonical URLs, Open Graph, meta descriptions)
- Image generation (AVIF srcsets, dimensions, cache busting)
- Code highlighting (copy buttons, syntax themes)
- Interactive features (modals, collapse, navigation)
- Feed validation (Atom 1.0 spec compliance)
- Sitemap validation (XML structure, URL quality)
- Responsive design (mobile, tablet, desktop)
- CSS validation (used classes defined, unused classes reported)
- JavaScript error detection

**Test output filtering:**
```bash
# Quiet mode (only show failures and summary)
npm run test:e2e:quiet

# Full output
npm run test:e2e

# View detailed report in browser
npm run test:report
```

**Test warnings:**
Tests emit warnings for non-fatal issues using `console.log("⚠️  message")`:
- Image dimensions not ideal (694x630 vs 1200x630)
- Unused CSS classes (dead code)
- Orphaned Bootstrap classes

See `tests/README.md` for detailed test documentation.

### Code Highlighting System
```bash
# Generate syntax-highlighted HTML for blog posts
npm run generate:shiki
# Or with theme: npm run generate:shiki -- vitesse-dark

# Currently configured themes: monokai, github-dark, dracula, nord, vitesse-dark,
#   github-dark-default, github-dark-dimmed, one-dark-pro, gruvbox-dark-*
```

### Mermaid Diagram Generation
```bash
# First-time setup: Install Puppeteer dependencies (WSL/Ubuntu)
sudo apt-get install -y libasound2t64 libatk1.0-0 libatk-bridge2.0-0 libcups2 \
  libdrm2 libgbm1 libgtk-3-0 libnspr4 libnss3 libxcomposite1 libxdamage1 \
  libxfixes3 libxkbcommon0 libxrandr2

# Install Chrome headless shell for Puppeteer
npx puppeteer browsers install chrome-headless-shell

# Generate SVG diagrams from .mmd files
npm run generate:mermaid
# Or with theme: npm run generate:mermaid -- dark

# Supported themes: default, forest, dark, neutral, base
```

### Open Graph Image Generation
Open Graph images are automatically generated from SVG sources during the build process.

**Workflow:**
1. Create SVG file at any path (e.g., `/www/img/blog/open_graph/my-post.svg`)
2. Set `og_image: "/img/blog/open_graph/my-post.svg"` in PageInfo
3. Run `php scripts/generate_images_build.php` to generate PNGs and optimized versions
4. PHP automatically rewrites `.svg` → `.png` in og:image meta tags at runtime

**SVG Requirements:**
- viewBox="0 0 1200 630" for correct 1.91:1 aspect ratio
- All fonts converted to paths or using web-safe fonts
- PNG is generated with Inkscape at `--export-width=1200` (maintains aspect ratio)

**Build Process:**
- During Docker build, `generate_images_build.php` detects `.svg` og_images
- Converts SVG → PNG using Inkscape (e.g., `my-post.svg` → `my-post.png`)
- Creates VIPS-optimized version in `/generated/` for serving
- Checks file timestamps to skip regeneration if PNG is up-to-date

## Architecture & Key Patterns

### File Structure & Workflow

**Source files (not in Docker image):**
- `/src/css/main.css` - Vanilla CSS source with modern features (@layer, OKLCH, native nesting)
- `/src/js/` - JS source files (main.js imports all modules)
- `/dist/` - Build scratch directory

**Data source files (not in Docker image):**
- `/data/blog_posts.php` - Blog post metadata (PHP array, native HTML strings)
- `/data/research.php` - Research publications (PHP array)
- `/data/press.php` - Press coverage (PHP array)
- `/data/cissp_results.json` - CISSP benchmark data (machine-generated JSON)

**Generated files (in Docker image via `/www/generated/`):**
- `site.db` - SQLite database built from data source files
- `bundle.css` - Minified CSS (via LightningCSS)
- `bundle.js` - Bundled JS (collapse + zoom_detection + image_modal + code_copy via esbuild)
- `sprite.svg` - Icon sprite
- `highlighted-shiki/<blog-name>/` - Syntax-highlighted code HTML
- `mermaid/<blog-name>/` - Diagram SVGs

**Other www files:**
- `/www/*.php` - Main site pages
- `/www/includes/` - PHP includes (classes.php, top.php, footer.php)
- `/www/img/` - Images
- `/www/feed.php` - Atom 1.0 feed generator

### PHP Page Structure
All PHP pages follow this pattern:
```php
<?php
require_once "includes/classes.php";
// Blog posts load metadata from the database:
$page_info = PageInfo::fromDB('my-post-slug');
// Non-blog pages construct PageInfo directly:
$page_info = new PageInfo(
    title: "Page Title",
    description: "SEO description",
    og_image: "/img/path/image.png",
    og_type: "website"
);
include "includes/top.php";  // Header, nav, meta tags, loads bundle.css + extra_css
?>
<!-- Page content -->
<?php include "includes/footer.php"; // Loads extra_js + bundle.js ?>
```

CSS and JS bundles are automatically loaded. Blog posts with `extra_css`/`extra_js` in their JSON entry get those assets loaded automatically by top.php/footer.php.

### PageInfo Class
The `PageInfo` class in `includes/classes.php` handles SEO and Open Graph metadata:
- **Required**: `title`, `description`, `og_image`, `og_type`
- **For articles** (og_type='article'): Also requires `published_time`, `modified_time`
- **Optional**: `subtitle`, `tags`, `section`, `canonical_url`, `html_description`, `blog_image`
- **Properties**: `extra_css`, `extra_js` — arrays of additional asset paths loaded by top.php/footer.php
- **Methods**: `renderFullHeader()`, `renderBlogEntry()`, `renderMetaTags()`, `wasModified()`
- **Factory methods**: `PageInfo::fromDB($slug)` for blog posts, `PageInfo::fromRow($row)` from a DB result
- Non-blog pages (research, press, index) still use `new PageInfo(...)` directly

### Data Layer: PHP Arrays → SQLite Pipeline

All structured site data follows a single pattern: **PHP array files in `data/`** → **`scripts/build_db.php`** → **SQLite database at `www/generated/site.db`**.

**Data source files** (`data/`):
- `blog_posts.php` — Blog post metadata (title, subtitle, description, dates, tags, extra assets)
- `research.php` — Research publications (authors, venue, links, category)
- `press.php` — Press coverage entries (publication, date, images)
- `cissp_results.json` — CISSP benchmark results (machine-generated JSON, not hand-edited)

PHP arrays are used instead of JSON because several fields contain HTML (`html_description`, `caption`, `description_html`). PHP handles HTML strings natively without escaping, making the data files easier to read and edit.

**Build script** (`scripts/build_db.php`):
- Includes all PHP data files (`include $file`), reads CISSP JSON, populates 6 tables: `blog_posts`, `research`, `press`, `runs`, `domain_accuracy`, `question_results`
- Validates at build time: slug↔PHP file correspondence, required fields, date sanity, OG image existence
- Run automatically during Docker build; run manually with `php scripts/build_db.php`

**Accessing the database** (`SiteDB` singleton in `classes.php`):
```php
$db = SiteDB::get();  // Returns read-only SQLite3 instance
$results = $db->query('SELECT * FROM blog_posts ORDER BY sort_order');
```

**Adding a new blog post**:
1. Create `www/<slug>.php` with `$page_info = PageInfo::fromDB('<slug>');`
2. Add entry to `data/blog_posts.php` (slug must match PHP filename)
3. Run `php scripts/build_db.php` to rebuild the database
4. Register the post with the e2e suite in `tests/fixtures/test-data.ts` (the single source of truth — otherwise the post goes untested): add the slug to `BLOG_POST_SLUGS`, the canonical URL to `EXPECTED_CANONICAL_URLS`, and a `BLOG_POST_METADATA` entry (`hasCodeBlocks`/`hasMermaidDiagrams`, plus `interactiveCharts` if it ships JS charts). Specs derive their page lists from this fixture. See `tests/README.md` → "Adding a New Blog Post" for field details.

**Adding research/press entries**: Add to the respective PHP data file and rebuild.

**Key design decisions**:
- Blog PHP files contain only content — all metadata lives in data files/DB
- `renderResearchEntry()` and `renderPressEntry()` take DB row arrays directly
- PHP arrays (authors, tags, links) are stored as JSON strings in SQLite and decoded at query time
- `blog.php`, `index.php`, and `feed.php` query the DB instead of including PHP files

### Sitemap Generation
`scripts/sitemap.php` generates `www/sitemap.xml` with lastmod dates from multiple sources:
- **Blog posts**: `modified_time` from `blog_posts.php` (authoritative)
- **Blog listing, index, feed**: newest blog post's `modified_time`
- **Research, press**: git history on their data source files (`data/research.php`, `data/press.php`)
- **Other pages**: git history on the PHP file

### Feed System
`/www/feed.php` generates an Atom 1.0 feed by querying all 3 tables (blog_posts, research, press) from the database:
- Sorted by date descending across all content types
- Includes Media RSS thumbnails from og_image

### Asset Versioning
The `versioned_asset()` / `versioned_url()` helpers in `includes/top.php` add MD5-based cache busting to CSS/JS, the favicon set, and the manifest:
- Automatically append `?v=<md5hash>` to the asset URL
- The hash is the MD5 of **that file**, so changing the file invalidates browser caches

**Responsive images (`responsiveImage()` in `includes/image-resizer.php`) version by the SOURCE file's hash, not the generated output — this is deliberate, do not "simplify" it to hash the AVIF.** The pre-generated AVIFs are served `immutable, max-age=1y` under stable filenames (e.g. `name_800w.avif`), so without a version query a redesigned image keeps serving the stale cached copy. The version appended to `src` and every `srcset` URL is `md5_file()` of the original source image because:
- AVIF bytes differ by encoder — **local builds use ImageMagick, the Docker build uses VIPS** — so hashing the output would churn the version on every deploy even when nothing changed.
- The source hash is stable across encoders/rebuilds and changes only when the base image actually changes, so caches bust automatically on a redesign with **no Cloudflare purge needed** (the new URL bypasses the immutable cache).

### Code Highlighting for Blog Posts
1. Place raw code in `/code_snippets/<blog-name>/<filename>.<ext>`
2. Run `npm run generate:shiki` to create highlighted HTML
3. Include in PHP: `<?php include "generated/highlighted-shiki/<blog-name>/<filename>.html"; ?>`
4. Add `// shiki: nolinenum` comment in code to disable line numbers
5. Copy button and styling automatically available via bundles
6. New snippets and languages work automatically - no CSS changes needed

### Mermaid Diagrams for Blog Posts
1. Place `.mmd` files in `/code_snippets/<blog-name>/`
2. Run `npm run generate:mermaid` to create SVG diagrams
3. Include in PHP: `<?php include "generated/mermaid/<blog-name>/<filename>.svg"; ?>`

### Image Modal System
- Images with `image-modal-content` class become clickable for modal display
- Modal displays `img.src` (the full-size image) by default
- Optional `data-modal-src` attribute can specify a different image for the modal
- Preloading on hover improves modal performance
- No additional classes needed - `img.src` is already the full-resolution image

### Icon System

There are **two** ways an icon reaches a page:

**1. Sprite-based (`bi_inline()`)** — the default for UI/brand glyphs:
- Development: Full icon sprite generated with `./scripts/generate_sprite.sh` → `www/generated/sprite.svg`
- Production: Only used icons included via `generate-used-icons.php` (run with `./scripts/generate_sprite.sh prod`)
- Access via `bi_inline($name)` in PHP templates → emits `<svg class="bi"><use xlink:href="/generated/sprite.svg?v=<md5>#$name">`
- `generate-used-icons.php` discovers which icons ship to prod from **three** sources: `bi_inline('name')` calls, `'icon' => 'name'` patterns in source files, **and** the `research` table's link `icon` fields (queried from `site.db`). Grepping PHP alone will not give the full set.
- `generate_sprite.sh` strips all inline `style="…"` attributes from the final sprite via `sed` (Firefox CSP blocks inline styles). Icon colors must therefore come from `fill`/`stroke` attributes, not `style`.

**2. Image-based (`<img class="icon-size">`)** — for research/press link logos:
- Research/press link entries (`data/research.php`, `data/press.php`) render via `renderResearchEntry()`/`renderPressEntry()` in `classes.php`.
- Each link may provide **either** `'icon' => '<sprite-name>'` **or** `'image' => '<path>'`. **`image` takes precedence** — when present, the link renders as `<img class="icon-size">` and the sprite is never consulted (the `icon` key becomes dead data). Example: the `plos` research link uses a PNG `image`, so its `'icon' => 'plos'` is ignored and no `plos` symbol need exist in the sprite.

**Safari caveat — gradients/clip-paths in sprite icons (important when adding colorful logos):** WebKit/Safari does **not** resolve internal `url(#…)` references (gradients, clipPaths, masks, filters) when a symbol is instantiated through an **external** `<use xlink:href="sprite.svg#id">`. Solid `fill` values clone fine; `url(#…)` references silently fail — and the failed shape paints **transparent, not black** (e.g. Instagram rendered with no color, Reddit lost its gradient face). Chrome/Firefox resolve these fine, so the breakage only shows in Safari. Monochrome `currentColor` icons are unaffected.

**The fix this repo uses — "layered" icons with a baked solid fallback.** Because the failed shape paints transparent, you put a **solid-fill copy of each shape underneath the gradient-filled shape** in the same SVG:
- Chrome/Firefox paint the gradient on top → real logo.
- Safari paints the gradient as nothing → the solid base shows through → flat fallback logo.

One icon, no JS, no UA sniffing, no per-page payload — stays in the external sprite. Costs ~2× markup for that symbol. The affected logos (`reddit-icon`, `instagram-icon`, `lm-studio-icon`, `python`) live as layered overrides in `custom_icons/`; `c` was a no-op clipPath (clipped to the full canvas) and just had the clip removed. **When adding any new gradient/clip logo to the sprite, give it a layered fallback the same way** (solid base layer, then gradient layer, then any always-solid glyph like white camera/text on top). Verify in real Safari — a few older/mobile WebKit builds render the failed gradient as a black box instead of transparent, in which case fall back to a fully-flat (solid-fill) icon.

### CSS Architecture
**Source:** `/src/css/main.css` - Single vanilla CSS file using modern features

**Bootstrap is NOT used.** The CSS uses Bootstrap-compatible class naming conventions so the markup reads familiarly, but Bootstrap is not a dependency and is never loaded. Only a curated subset of Bootstrap-compatible classes are actually defined:
- Layout: `container`, `container-lg`, `row`, `col-*`, `col-md-*`, `col-lg-*`
- Spacing: `mt-5`, `mb-4`, `ms-4`, `me-2`, `p-3`, `pt-2`, `pb-2`, `pb-3`, `ps-2`, `ps-3`, `py-2`, `mx-auto`
- Typography: `fw-bold`, `fw-bolder`, `fs-4`, `fs-5`, `fs-6`, `lead`, `text-muted`
- Display/flex: `d-flex`, `rounded`, `rounded-3`, `shadow-sm`, `bg-primary`, `badge`
- Navbar and button classes (custom implementations)

**Notably absent** — these Bootstrap classes are NOT defined and do nothing: `table`, `table-sm`, `table-responsive`, `caption-top`, `d-none`, `d-block`, most `text-*` color utilities, most spacing variants. Tables are styled via element selectors (`table`, `thead`, `th`, `td`), not Bootstrap classes.

**Modern CSS Features Used:**
- `@layer` for cascade/specificity control (reset, base, layout, components, site, shiki, utilities)
- OKLCH color model for perceptually uniform colors
- Native CSS nesting (no preprocessor needed)
- `:has()` selector for parent-based styling
- `clamp()` for fluid sizing
- CSS custom properties for theming

**Build Pipeline:**
- Development: `cp src/css/main.css www/generated/bundle.css` (simple copy)
- Production: `lightningcss --minify --bundle` for minification with native feature support

**Color System (OKLCH):**
| Purpose | OKLCH |
|---------|-------|
| Primary (green) | `oklch(60% 0.17 155)` |
| Body text | `oklch(35% 0.04 75)` |
| Page background | `oklch(97% 0.025 90)` |
| Links (rust) | `oklch(48% 0.14 50)` |
| Code background | `oklch(18% 0.02 60)` |

**Layer Order (specificity):**
1. `reset` - Box model, normalize
2. `base` - Typography, links, lists
3. `layout` - Container, grid, columns
4. `components` - Navbar, cards, buttons
5. `site` - Site-specific styles
6. `shiki` - Code highlighting
7. `utilities` - Spacing, display (wins without !important)

### JavaScript Architecture
- `/src/js/main.js` - Entry point that imports all modules
- `/src/js/collapse.js` - Animated collapse (custom implementation, no Bootstrap JS)
- `/src/js/zoom_detection.js`, `image_modal.js`, `code_copy.js` - Feature modules
- Bundled with esbuild into `www/generated/bundle.js`
- `npm run watch` rebuilds bundles on any source file change

## Important Implementation Details

### Syntax Highlighting Configuration

**How It Works:**
Uses Shiki v3 with `shiki-class-transformer` to generate lightweight HTML. Instead of inline
styles on every token (`style="color:#4d9375"`), tokens get short class names (`class="s5"`).

**Pipeline:**
1. Shiki parses code and assigns TextMate scopes to tokens (e.g., `keyword.control`)
2. Theme (vitesse-dark) maps scopes to colors (e.g., `keyword.control` → `#4d9375`)
3. `shiki-class-transformer` intercepts and replaces colors with class names using a pre-built map
4. `src/css/shiki.css` provides the actual color definitions (bundled into bundle.css)

**Key Files:**
- `scripts/generate_shiki_highlights.mjs` - ESM script that runs Shiki with transformers
- `src/css/main.css` (shiki layer) - Theme colors (s0-s28) + container/line-number/copy-button styles
- Theme map loaded from `shiki-class-transformer/themes/vitesse-dark.json`

**Styling Details:**
- Dark papyrus background (#2a2520) on all code blocks
- Base text color (#dbd7caee) inherited by line numbers
- Line numbers at 50% opacity for subtle appearance
- Base64 encoding for copy functionality to preserve formatting

**Adding New Themes:**
To use a different theme (e.g., `dracula`):
1. Add theme to `THEMES` object in `generate_shiki_highlights.mjs`
2. Add theme map loader to `THEME_MAPS` object: `'dracula': () => require('shiki-class-transformer/themes/dracula.json')`
3. Append theme colors to `src/css/main.css` shiki layer (fetch from `shiki-class-transformer/themes/dracula.css`)
4. Note: Each theme has its own color palette (s0, s1, etc. map to different colors)

**Available Theme Maps** (from shiki-class-transformer):
aurora-x, ayu-dark, catppuccin-*, dracula, everforest-*, github-dark*, houston,
kanagawa-*, laserwave, material-theme-*, min-*, monokai, night-owl, nord,
one-dark-pro, poimandres, rose-pine-*, slack-*, solarized-*, synthwave-84,
tokyo-night, vesper, vitesse-*

### Docker Optimization
- Uses tini for proper signal handling
- Runs as non-root user (nobody)
- Multi-stage build reduces final image from 90MB to 40MB

### Security Patterns
- All user input sanitized with `htmlentities()` or `htmlspecialchars()`
- PHP configured with security headers in `/config/php.ini`
- Nginx configured for security in `/config/nginx.conf`

### Deployment Model (CI/CD)
**Continuous deploy from `main`** — no manual promote/`:prod` gate. Full runbook: [`deploy/README.md`](deploy/README.md).
- **CI** (`.github/workflows/ci.yml`): on every push/PR, builds the image (which validates `build_db.php`, image generation, shiki) and runs a Chromium smoke pass against the container. The full multi-browser `npm run test:docker` suite is a **local pre-push gate** — it can't run in CI because specs like `css-validation` read built assets from `www/generated/` off disk (only exists inside the image). CI fetches **Git LFS** content (`lfs: true`) since images + `data/cissp_results.json` are LFS-tracked; without it the build fails parsing the cissp pointer.
- On push to `main` (or a `v*` tag), CI publishes to GHCR: `:latest` (what the host deploys), `:sha-<commit>`, and `:v5` for a `v*` git tag (plain release number, no semver — `type=ref,event=tag`).
- **Host** (Synology NAS, behind pfSense/HAProxy + Cloudflare) runs `deploy/poll-deploy.sh` on a root cron every 5 min: pulls `:latest`, and on a digest change runs `deploy/deploy.sh` — a rolling **two-container** update (backup→primary, each gated on health **and** the version baked into the image) so HAProxy keeps the site up.
- `/nginx-health` returns `healthy <git-describe>`; the version is baked into `nginx.conf` at build time (`Dockerfile` sed). HAProxy's OPTIONS http-check keys off the 200 status, not the body.
- **Rollback**: host-side pin (`sudo IMAGE=…:sha-<commit> sh deploy.sh`) as an immediate stopgap, then `git revert` + push for the durable fix (the poller watches `:latest`, so a pin alone gets re-pulled).
- Deploy scripts have **no config file / no secrets**; defaults (image, container names, ports 8080/8086) are hardcoded and env-var-overridable. On DSM the Docker socket is root-only, so scripts run with `sudo` / the cron task runs as `root`.

## Manual Docker Comparison (Optional)

For manual verification, start a container and compare against production:

```bash
# Start container manually
docker build -t johnhringiv.com:latest .
docker run -d -p 8082:8080 --name test johnhringiv.com:latest

# Use Chrome DevTools MCP for visual verification
# Navigate to http://localhost:8082 and take a snapshot

# Or compare with curl
curl -s http://localhost:8082/ | grep 'canonical'
curl -s https://johnhringiv.com/ | grep 'canonical'

# Cleanup
docker stop test && docker rm test
```

**File size notes:**
- Browser DevTools shows **compressed** (gzip) sizes from production
- Local `curl | wc -c` or `ls -la` shows **uncompressed** sizes
- Gzip typically reduces CSS/JS by 70-80%

## Style Guide Compliance

The site has a comprehensive style guide at `docs/STYLE_GUIDE.md`. When creating or modifying content:

1. **Always reference the style guide** for:
   - Heading patterns (H1-H4 with appropriate classes and anchor links)
   - Color palette (green accents, warm brown text, parchment backgrounds)
   - Layout patterns (two-column splits, containers, spacing)
   - Code block styling (dark papyrus background, copy buttons)
   - List and callout patterns

2. **Before completing content changes**, verify against the style guide:
   - Headings use correct classes (`fw-bolder mb-4 mt-5` for H2, etc.)
   - Major headings have self-referential anchor links
   - Lead paragraphs use `class="lead"`
   - Definition callouts use `lead shadow-sm py-2 ps-2 rounded-3`
   - Images in columns use `image-modal-content` for modal support

3. **Common patterns to check**:
   ```html
   <!-- H2 with anchor link -->
   <h2 id="section-id" class="fw-bolder mb-4 mt-5">
       <a href="#section-id" class="text-reset text-decoration-none">Heading</a>
   </h2>

   <!-- Definition callout -->
   <p class="lead shadow-sm py-2 ps-2 rounded-3">
       <b class="fs-5">Term:</b> Definition text.
   </p>
   ```

## Proactive Reminders for User

When working with PHP pages, remind the user if they're missing:

1. **PageInfo object** - Required for proper SEO and Open Graph tags
   - Check if `$page_info = new PageInfo(...)` exists
   - Ensure it includes title, description at minimum
   - Suggest adding og_image for better social media sharing

2. **Open Graph images** - Especially important for:
   - Blog posts (should have custom preview images)
   - Main pages (homepage, research, etc.)
   - Any page likely to be shared on social media

3. **Canonical URLs** - Critical for SEO
   - Should be present on every page
   - Must match the production URL structure

4. **Style guide compliance** - When adding or modifying content:
   - Verify headings follow the documented patterns
   - Check that layouts use consistent column splits
   - Ensure code blocks and callouts match established styles

Example reminder: "This page is missing a PageInfo object which is needed for SEO. Also consider adding an og_image for better social media previews."

Example style reminder: "This H2 is missing the anchor link pattern from the style guide. Should be `<h2 id='...' class='fw-bolder mb-4 mt-5'><a href='#...' class='text-reset text-decoration-none'>...</a></h2>`"