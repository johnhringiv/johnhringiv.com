# Build and Test Scripts

This directory contains scripts for building, testing, and deploying the johnhringiv.com website.

## Docker Testing

### test-simple.sh

**Purpose:** Simple Docker build and test runner with HTML validation.

**Usage:**
```bash
npm run test:docker
# or: ./scripts/test-simple.sh
```

**What it does:**
1. Starts HTML validator (`ghcr.io/validator/validator` Docker container on port 8888; reused if already running)
2. Builds Docker image
3. Starts container on port 8082
4. Runs the Playwright E2E suite across every page — HTML validation, SEO/metadata, image generation, code highlighting, interactive features, feed + sitemap, responsive design, and CSS validation
5. Shows pass/fail
6. Cleans up (container + validator)

**Requirements:**
- Docker installed and running (also runs the HTML validator — no Java needed)
- Node.js and npm dependencies installed

**Error handling:**
- Exits immediately with error if Docker is not installed
- Exits immediately with error if validator fails to start
- Exits with test exit code if tests fail

## Content Generation

### generate_shiki_highlights.mjs

**Purpose:** Generate syntax-highlighted HTML from code snippets using Shiki v3.

**Usage:**
```bash
npm run generate:shiki
# or with specific theme:
npm run generate:shiki -- vitesse-dark
```

**Details:**
- Scans `/code_snippets/` for source code files
- Generates HTML with class-based token styling (not inline styles)
- Outputs to `/www/generated/highlighted-shiki/`
- Supports line numbers and copy buttons
- Theme colors defined in CSS (bundle.css)

### generate_mermaid_diagrams.js

**Purpose:** Generate SVG diagrams from Mermaid definition files.

**Usage:**
```bash
npm run generate:mermaid
# or with specific theme:
npm run generate:mermaid -- dark
```

**Details:**
- Scans `/code_snippets/` for `.mmd` files
- Uses Puppeteer and mermaid-cli to render SVGs
- Outputs to `/www/generated/mermaid/`
- Supports themes: default, forest, dark, neutral, base

### generate_images_build.php

**Purpose:** Generate responsive image variants (AVIF) and optimize images for serving.

**Usage:**
```bash
php scripts/generate_images_build.php
```

**Details:**
- Converts SVG Open Graph images to PNG
- Generates AVIF versions of images for srcsets
- Uses VIPS for image processing
- Called automatically during Docker build
- Skips regeneration if files are up-to-date

### generate-sprite.sh

**Purpose:** Generate SVG icon sprite from Bootstrap Icons.

**Usage:**
```bash
./scripts/generate-sprite.sh
```

**Details:**
- Creates sprite with all Bootstrap Icons
- Development version includes all icons
- Production build uses `generate-used-icons.php` to optimize
- Outputs to `/www/generated/sprite.svg`

### generate-used-icons.php

**Purpose:** Generate optimized icon sprite with only icons used on the site.

**Usage:**
```bash
php scripts/generate-used-icons.php
```

**Details:**
- Scans PHP files for `bi_inline()` calls
- Extracts used icon names
- Generates minimal sprite (smaller file size)
- Called automatically during Docker build

## Utility Scripts

### router.php

**Purpose:** PHP development server router for clean URLs.

**Usage:**
```bash
php -S localhost:8080 scripts/router.php
```

**Details:**
- Removes `.php` extension from URLs
- Serves static assets directly
- Used by JetBrains development server

### sitemap.php

**Purpose:** Generate sitemap.xml for search engines.

**Usage:**
```bash
php scripts/sitemap.php > www/sitemap.xml
```

**Details:**
- Discovers all PHP pages automatically
- Adds blog posts, research, press entries
- Generates proper XML with lastmod dates
- Priority values based on page importance

## Configuration Files

- `puppeteer-config.json` - Puppeteer settings for Mermaid generation
- `mermaid-config.json` - Mermaid diagram theming and rendering options

## Development Workflow

### Local Development (JetBrains on port 8080)
1. Make code changes
2. Run `npm run watch` to auto-rebuild CSS/JS
3. Test at `http://localhost:8080`

### Release Candidate Testing (Docker on port 8082)
1. Run `./scripts/test-release-candidate.sh` for full validation
2. Review any failures and fix issues
3. Re-run until all tests pass
4. Deploy to production

### Production Deployment
1. Ensure all tests pass with `npm run test:rc`
2. Build production image: `docker build -t johnhringiv.com:prod .`
3. Deploy to production server
4. Verify with smoke tests against production URL

## Troubleshooting

### "Error: Docker is not installed"
The test script runs the HTML validator (and the app image) via Docker.
Install Docker and ensure the daemon is running, then re-run `npm run test:docker`.

### "Error: Validator failed to start"
Check validator logs:
```bash
docker logs johnhringiv-validator
```

Common issues:
- Port 8888 already in use
- First run is slow while `ghcr.io/validator/validator` is pulled

### Manual validator start (if needed)
```bash
docker run -d --name johnhringiv-validator -p 8888:8888 ghcr.io/validator/validator
```

### Port already in use
```bash
docker ps -a
docker stop johnhringiv-test && docker rm johnhringiv-test
```

### Tests failing
```bash
# View detailed HTML report
npm run test:report

# Run in UI mode for debugging
npm run test:e2e:ui
```
