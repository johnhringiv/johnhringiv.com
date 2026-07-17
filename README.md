# johnhringiv.com

Personal website for John H. Ring IV — built with PHP, vanilla CSS (modern features, no Bootstrap), and minimal JavaScript. Runs in a Docker container with Nginx and PHP-FPM on Alpine Linux.

**🌐 Live site**: [https://johnhringiv.com](https://johnhringiv.com)

## Project Structure

```
├── config/              # Nginx, PHP-FPM, supervisor configs
├── code_snippets/       # Raw code examples for blog posts
├── data/                # Structured data (blog_posts.php, research.php, press.php)
├── docs/                # CONTRIBUTING, STYLE_GUIDE, IMAGE_STRATEGY
├── scripts/             # Build scripts (PHP, JS)
├── src/
│   ├── css/             # Vanilla CSS source (main.css with @layer, OKLCH, native nesting)
│   └── js/              # JS source files
├── www/
│   ├── *.php            # Visitable pages
│   ├── img/             # Images
│   ├── includes/        # PHP includes (classes.php, top.php, footer.php)
│   ├── feed.php         # Atom 1.0 feed generator
│   └── generated/       # All generated assets (site.db, bundles, sprite, shiki, mermaid) — gitignored
├── tests/               # Playwright E2E suite
├── Dockerfile
└── CLAUDE.md            # Architecture + AI assistant instructions
```

## Usage

The build is completely self-contained in the Dockerfile — no PHP or Node needed on the host to run it.

```sh
docker build -t johnhringiv.com:latest .
docker run -p 8082:8080 johnhringiv.com:latest
```

Then open <http://localhost:8082>.

**Port notes:** the container serves on 8080 internally; map it to 8082 on the host to avoid clashing with the local dev server (JetBrains on 8080). The test suite also uses 8082.

### Build for another host

In normal operation you don't build for the host by hand — CI builds and
publishes the image to GHCR and the host pulls it (see [Deployment](#deployment)).
For the offline fallback, save a tarball and copy it over:

```sh
docker build -t johnhringiv.com:latest .
docker save -o myimage johnhringiv.com:latest
```

## Testing

Build and test a release candidate before deploying:

```bash
npm run test:docker      # or ./scripts/test-simple.sh
```

It starts the W3C Nu HTML validator (Docker), builds the image, runs the Playwright suite on port 8082, and cleans up. **Requirement: Docker only — no Java.**

Coverage: HTML validation (W3C), SEO/Open Graph metadata, responsive design, code highlighting & interactive features, feed + sitemap, CSS validation, favicon/manifest assets, and JavaScript error detection. See [`tests/README.md`](tests/README.md) for details.

## Deployment

The site ships as the self-contained Docker image above. In production the container runs behind **HAProxy on pfSense**, fronted by Cloudflare — the same stack as [gotflashes.com](https://gotflashes.com). The full walk-through is the blog post [Secure, Scalable Home Web Hosting](https://johnhringiv.com/secure-scalable-home-web-hosting).

**Deployment stack:**
- **Cloudflare** — DNS and CDN (firewall restricts origin traffic to Cloudflare IPs only)
- **ACME / Let's Encrypt** — automated TLS certificates
- **HAProxy (on pfSense)** — SSL termination and reverse proxy, with domain/subdomain routing
- **Docker container** — the app (nginx + PHP-FPM, Alpine)

**Zero-downtime updates:** two instances of the container run behind HAProxy — a **primary** and a **backup**. HAProxy serves the primary and fails over to the backup when the primary's health check (`/nginx-health`, in `config/nginx.conf`) stops responding. `deploy/deploy.sh` recreates them one at a time — backup first, then primary, health-gating each — so while one restarts on the new version HAProxy keeps serving from the other and the site stays up.

**Pipeline (pull-based).** The host has no inbound access, so deploys are pull-based:

1. **CI** ([`.github/workflows/ci.yml`](.github/workflows/ci.yml)) tests every push/PR (`npm run test:docker`) and, on push to `main` or a `v*` tag, builds and pushes the image to GHCR (`:latest`, `:sha-<commit>`, `:semver`).
2. **Deploy** ([`.github/workflows/deploy.yml`](.github/workflows/deploy.yml)) is a manual button that promotes a published image to `:prod` (rollback = promote an older `sha-…` tag).
3. The **host** runs [`deploy/poll-deploy.sh`](deploy/poll-deploy.sh) on cron: it pulls `:prod`, runs the rolling `deploy.sh` when the digest changes, self-heals after a reboot, and health-checks the edge.

Setup, rollback, and the offline-tarball fallback are documented in the **[deploy runbook](deploy/README.md)**.

## Development

See **[docs/CONTRIBUTING.md](docs/CONTRIBUTING.md)** for local environment setup (WSL/Ubuntu, PHP 8.5, the PHP 8.3 + VIPS image toolchain, Node via nvm) and the build pipelines (CSS, JS, icons, Shiki, Mermaid, images).

## Documentation

- **[docs/CONTRIBUTING.md](docs/CONTRIBUTING.md)** — local setup and build pipelines
- **[deploy/README.md](deploy/README.md)** — deploy runbook (CI → GHCR → promote → poll, rollback)
- **[CLAUDE.md](CLAUDE.md)** — architecture, conventions, and design decisions (data layer, OG image pipeline, asset versioning, icon system)
- **[docs/STYLE_GUIDE.md](docs/STYLE_GUIDE.md)** — content and visual style guide
- **[docs/IMAGE_STRATEGY.md](docs/IMAGE_STRATEGY.md)** — responsive image / Open Graph strategy
- **[tests/README.md](tests/README.md)** — test suite documentation
