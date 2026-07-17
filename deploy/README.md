# Deploy runbook: CI → GHCR → promote → poll

johnhringiv.com ships as the self-contained Docker image built by
[`Dockerfile`](../Dockerfile). Deploys are **pull-based**: the production host
sits behind pfSense/Cloudflare with no inbound access, so CI publishes an
image and the host polls for it.

```
push to main ──► CI (test + build) ──► GHCR :latest / :sha-… / :1.2.0
                                             │
                Deploy workflow (button) ────┘ retag ──► GHCR :prod
                                                              │
                       host cron: poll-deploy.sh ────────────┘ pull on change
                                     │
                                     └─► deploy.sh: rolling two-container update
```

## Pipeline

**CI** (`.github/workflows/ci.yml`) runs on every push and PR: `npm run
test:docker` builds the image and runs the full Playwright E2E suite against
it (W3C HTML validation, SEO/OG metadata, images, code highlighting,
feed/sitemap, responsive, CSS). On push to `main` (or a `v*` tag) it then
builds and pushes to GHCR:

- `ghcr.io/johnhringiv/johnhringiv.com:latest` — newest main
- `…:sha-<commit>` — every commit (use these to roll back)
- `…:1.2.0` — for `v*` git tags

A monthly scheduled run rebuilds so the base image picks up CVE fixes.

**Deploy** (`.github/workflows/deploy.yml`) is the promote gate: GitHub →
Actions → **Deploy** → Run workflow retags a published image (default
`latest`, or a `sha-…` tag to roll back) to `:prod` on GHCR. Registry-side
only — takes seconds. Nothing reaches production until you press it (unless
you point the host at `:latest` — see below).

## Host setup (one time)

Public repo → public image, so no registry auth needed on the host.

1. Copy `.env.example` to a private env file next to the scripts, fill it in,
   lock it down:
   ```sh
   cp deploy/.env.example deploy/johnhringiv.env
   $EDITOR deploy/johnhringiv.env      # set CONTAINER_*, PORT_*, PUBLIC_URL
   chmod 600 deploy/johnhringiv.env
   ```
   `PORT_PRIMARY` / `PORT_BACKUP` must match the host ports HAProxy's primary
   and backup backends target.
2. Press **Deploy** once to mint `:prod`, then run the first deploy by hand:
   ```sh
   sh deploy/deploy.sh deploy/johnhringiv.env
   ```
3. Schedule the poller (cron, every 5 min):
   ```
   */5 * * * * cd /path/to/johnhringiv.com/deploy && sh poll-deploy.sh johnhringiv.env >> poll-deploy.log 2>&1
   ```
   Configure cron's `MAILTO` (or your mailer) so a non-zero exit pages you.

To ship **every push to main** without pressing Deploy, set
`IMAGE=…/johnhringiv.com:latest` in the env file — same poller, no button.

## Rolling update (why the site stays up)

The host runs **two** app containers behind HAProxy: a primary and a backup.
HAProxy serves the primary and fails over to the backup when the primary's
`/nginx-health` check (see `config/nginx.conf`) stops answering.

`deploy.sh` recreates them **one at a time**, health-gating each:

1. **Backup first**, on the new image. Wait for `localhost:<backup>/nginx-health`.
   The primary is untouched and still serving the old image throughout — if the
   backup never comes up, the deploy aborts here and the site is unaffected.
2. **Then the primary.** While it's down, its health check fails and HAProxy
   fails over to the freshly-updated backup; once the primary is healthy again
   HAProxy returns to it.

At every moment at least one healthy instance is serving → zero downtime.

## Continuous deploy loop

`poll-deploy.sh`, run from cron:

- **Unchanged tag, both containers running, edge healthy** → cheap no-op (one
  manifest check + one `/nginx-health` probe through Cloudflare).
- **Changed `:prod` digest** → pulls and runs `deploy.sh`, then verifies the
  edge; exits non-zero if the edge never comes up.
- **A container not running on an unchanged tag** (e.g. after a host reboot,
  before Docker's `restart unless-stopped` has settled) → self-heals by
  redeploying both in order.
- **Both containers running but the edge unreachable** → exits non-zero
  *without* redeploying (Cloudflare/HAProxy/DNS trouble a redeploy won't fix).

## Rollback

GitHub → Actions → **Deploy** → Run workflow → enter a `sha-<commit>` tag
(from the CI run of the good commit) instead of `latest`. The poller picks up
the retagged `:prod` within one cycle. Or, on the host, pin `IMAGE` to a
`sha-…` tag and run `deploy.sh` directly.

## Offline / manual image (fallback)

Build and `docker save` anywhere, copy the tarball to the host, and pass it as
the second argument — the pull is skipped:

```sh
docker build -t johnhringiv.com:local .
docker save johnhringiv.com:local | gzip > site.tar.gz
# on the host:
IMAGE=johnhringiv.com:local sh deploy/deploy.sh deploy/johnhringiv.env site.tar.gz
```

This replaces the old `docker save` → copy `.img` → `docker load` → recreate-
by-hand ritual for the cases where you still need it.

## Verify

```sh
curl https://johnhringiv.com/nginx-health          # healthy
curl -s https://johnhringiv.com/ | grep canonical  # sanity-check a page
docker ps --filter name=johnhringiv                # both containers Up
```
