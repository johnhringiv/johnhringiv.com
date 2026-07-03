# Contributing to johnhringiv.com

Local development setup and the build pipelines for the site. Architecture,
conventions, and design decisions live in [CLAUDE.md](../CLAUDE.md); content and
visual conventions are in [STYLE_GUIDE.md](STYLE_GUIDE.md).

Made to be developed on WSL or Ubuntu/Linux — both use the same setup (Node via
nvm, PHP via apt).

## Development Environment Setup

### Prerequisites

- **PHP 8.5** — the production runtime
- **PHP 8.3 + libvips** — only for the image build (see [Image toolchain](#image-toolchain-php-83--vips))
- **Node.js & npm**
- **Docker** — to build and test the production image

### Ubuntu/Debian (including WSL)

Production runs PHP 8.5, which isn't in the standard Ubuntu repos (Noble 24.04
ships 8.3), so add the [ondrej/php](https://launchpad.net/~ondrej/+archive/ubuntu/php)
PPA:

```sh
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y php8.5 php8.5-sqlite3 php8.5-xdebug
```

### Node.js via nvm (recommended)

```sh
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/master/install.sh | bash
nvm install node
```

### Image toolchain (PHP 8.3 + VIPS)

The image build script (`scripts/generate_images_build.php` — responsive AVIF
srcsets plus OG image conversion) requires the PHP **VIPS** extension, and it
must be built against **PHP 8.3, not 8.5**: the Docker builder stage runs this
step under 8.3 (the only version Alpine packages VIPS for), and the extension is
ABI-specific, so a build for one PHP version won't load in another.

```sh
# Install PHP 8.3 (alongside 8.5), libvips, and the 8.3 dev headers
sudo apt install -y php8.3 php8.3-sqlite3 libvips-dev php8.3-dev

# Build and enable the VIPS extension for 8.3
sudo pecl install vips
echo "extension=vips" | sudo tee /etc/php/8.3/cli/conf.d/20-vips.ini
```

Run the image build with the 8.3 binary explicitly (your default `php` is 8.5):

```sh
php8.3 scripts/generate_images_build.php
```

## Quick Start

```sh
git clone https://github.com/johnhringiv/johnhringiv.com.git
cd johnhringiv.com

npm ci
./scripts/generate_sprite.sh   # generate the icon sprite
npm run watch                  # rebuild CSS/JS bundles on source change
```

Develop against the JetBrains/PHP dev server on port **8080**. Build and test a
release-candidate Docker image with `npm run test:docker` (see [Testing](#testing)).

All generated output lands in `www/generated/` — it's gitignored and rebuilt
during the Docker build.

## Build Pipelines

### CSS

Source is `src/css/main.css` — vanilla CSS with modern features (`@layer`, OKLCH
colors, native nesting). `npm run watch` rebuilds on change.

- **Development build:** simple copy to `www/generated/bundle.css`
- **Production build (`npm run build:prod`):** minified/flattened via LightningCSS

### JavaScript

Source in `src/js/` — no Bootstrap JS, only lightweight custom scripts. Bundled
with esbuild: `collapse.js + zoom_detection.js + image_modal.js + code_copy.js`
→ `www/generated/bundle.js`. `npm run build:prod` minifies both bundles.

### Icons

```sh
./scripts/generate_sprite.sh
```

Creates `www/generated/sprite.svg` with all icons. In production only the used
icons are included. Icons from [Bootstrap Icons](https://icons.getbootstrap.com/),
Academicons, [svglogos.dev](https://svglogos.dev/#), and
[DevLogos](https://www.devlogos.com/logos).

### Code highlighting (Shiki)

Raw code for blog posts lives in `code_snippets/<blog-name>/`. Generate
syntax-highlighted HTML with:

```sh
npm run generate:shiki
```

Uses `shiki-class-transformer` to emit lightweight HTML with CSS classes instead
of inline styles (theme colors live in the shiki layer of `src/css/main.css`).
Output goes to `www/generated/highlighted-shiki/<blog-name>/` and is `include`d
directly in PHP blog posts.

### Mermaid diagrams

`.mmd` files in `code_snippets/<blog-name>/` are converted to SVG:

```sh
# First-time setup: Puppeteer dependencies (WSL/Ubuntu)
sudo apt-get install -y libasound2t64 libatk1.0-0 libatk-bridge2.0-0 libcups2 \
  libdrm2 libgbm1 libgtk-3-0 libnspr4 libnss3 libxcomposite1 libxdamage1 \
  libxfixes3 libxkbcommon0 libxrandr2
npx puppeteer browsers install chrome-headless-shell

npm run generate:mermaid
```

Output goes to `www/generated/mermaid/<blog-name>/`.

### Images (responsive AVIF + Open Graph)

Handled by the VIPS script above (`php8.3 scripts/generate_images_build.php`).
See [IMAGE_STRATEGY.md](IMAGE_STRATEGY.md) for the strategy, and CLAUDE.md for the
source-hash cache-busting behavior of `responsiveImage()`.

## Adding Content

### PHP page structure (`PageInfo`)

Every page uses the `PageInfo` class for SEO and Open Graph metadata.

Blog posts load their metadata from the database:

```php
<?php
require_once "includes/classes.php";
$page_info = PageInfo::fromDB('my-post-slug');
include "includes/top.php";
?>
<!-- Page content -->
<?php include "includes/footer.php"; ?>
```

Non-blog pages construct `PageInfo` directly:

```php
<?php
require_once "includes/classes.php";
$page_info = new PageInfo(
    title: "Page Title",
    description: "SEO description",
    og_image: "/img/path/image.png",
    og_type: "website"
);
include "includes/top.php";
?>
<!-- Page content -->
<?php include "includes/footer.php"; ?>
```

### Data layer & Atom feed

Structured data lives in `data/*.php` (`blog_posts.php`, `research.php`,
`press.php`) and is built into `www/generated/site.db` by `scripts/build_db.php`.
The listings and the Atom feed (`/feed`) query that database. See CLAUDE.md for
the full data-layer pattern and the steps for adding a blog post (including
registering it in `tests/fixtures/test-data.ts`).

### Testing Open Graph images

Use [OpenGraph.xyz](https://www.opengraph.xyz/) to preview how a page appears on
social media: enter a page URL (e.g. `https://johnhringiv.com/a_subtle_python_threading_bug`),
and it renders the card across Facebook, Twitter, LinkedIn, and Discord. Verify
image dimensions/cropping (1200×630), per-platform appearance, and that all OG
tags are present.

## Testing

```bash
npm run test:docker      # or ./scripts/test-simple.sh
```

Builds the production image and runs the full Playwright suite on port 8082 —
HTML validation (W3C), SEO/Open Graph metadata, responsive design, code
highlighting and interactive features, feed + sitemap, CSS validation,
favicon/manifest assets, and JavaScript error detection. **Requirement: Docker
only (no Java).** See [tests/README.md](../tests/README.md) for details.

## Commits & Pull Requests

- Branch off `main`; open the PR back to `main`.
- Use conventional-commit prefixes: `feat:`, `fix:`, `docs:`, `test:`,
  `refactor:`, `chore:`. Keep PRs focused.
- Run `npm run test:docker` green before opening a PR.
- **Assets:** raster binaries (PNG/JPG/PDF/ICO) are tracked with **Git LFS**;
  SVGs are versioned as plain text in Git (see `.gitattributes`).
