# Deploy runbook: CI → GHCR → poll (continuous deploy from main)

johnhringiv.com ships as the self-contained Docker image built by
[`Dockerfile`](../Dockerfile). Deploys are **pull-based**: the production host
sits behind pfSense/Cloudflare with no inbound access, so CI publishes an
image and the host polls for it. There is **no manual promote step** — every
push to `main` that passes CI is live within one poll cycle.

```
push to main ──► CI (build + smoke) ──► GHCR :latest  (+ :sha-<commit>)
                                             │
                    host cron: poll-deploy.sh ┘ pull :latest when digest changes
                                     │
                                     └─► deploy.sh: rolling two-container update
```

## Pipeline

**CI** (`.github/workflows/ci.yml`) runs on every push and PR: it builds the
Docker image — which itself validates `build_db.php` (slugs, dates, OG images),
responsive image generation, and shiki highlighting — and runs a **Chromium
smoke pass** (all pages 200, CSS/JS/sprite load, no console errors, sprite
completeness) against the running container. The full multi-browser +
static-analysis suite (`npm run test:docker`) stays a **local pre-push gate**;
several of its specs read built assets from `www/generated/` on disk, which
only exists inside the image, not on a clean CI runner.

On push to `main` (or a `v*` tag) CI pushes to GHCR:

- `ghcr.io/johnhringiv/johnhringiv.com:latest` — newest main (**what the host deploys**)
- `…:sha-<commit>` — every commit (pin one to roll back)
- `…:v5` — a `v*` git tag, verbatim (plain release number, no semver)

A monthly scheduled run rebuilds so the base image picks up CVE fixes.

**Deployment model — continuous deploy from `main`.** There is no `:prod` tag
and no promote button: the host polls `:latest` and ships whatever CI last
published from `main`. Merging to `main` *is* the release decision. (Rollback
is a host-side pin plus a `git revert` — see [Rollback](#rollback).)

## Host setup (one time)

Public repo → public image, so no registry auth needed on the host. **There
are no secrets and no config file** — the site ships nothing sensitive and
there's exactly one production host, so image, container names, ports
(`8080` primary / `8086` backup), health path, and public URL are hardcoded
as defaults in the scripts. Any of them is overridable by an env var for a
one-off (see below), but nothing needs setting for a normal deploy.

1. First deploy by hand. On Synology/DSM the Docker socket is root-only, so the
   scripts need `sudo`:
   ```sh
   sudo sh deploy.sh
   ```
   This pulls `:latest` and brings up both containers.
2. Schedule the poller to run every 5 minutes **as root** — on Synology via
   Task Scheduler (see below); on a generic host via cron:
   ```
   */5 * * * * cd /path/to/deploy && sh poll-deploy.sh >> poll-deploy.log 2>&1
   ```
   Have cron/DSM notify you on a non-zero exit — the only time the poller
   signals a problem.

If the HAProxy backend ports ever change, override `PORT_PRIMARY` /
`PORT_BACKUP` via env var rather than editing the script.

### Synology (DSM) Task Scheduler

DSM overwrites the system crontab, so schedule the poller through the UI:

1. **Control Panel → Task Scheduler → Create → Scheduled Task → User-defined
   script**.
2. **General**: name it (e.g. `johnhringiv poll-deploy`); **User: `root`**
   (Docker is root-only on DSM); Enabled.
3. **Schedule**: Daily, First run `00:00`, **Frequency: Every 5 minutes**, Last
   run `23:55`.
4. **Task Settings → Run command → User-defined script**:
   ```sh
   cd /volume3/docker_ssd/deploy_scripts/johnhringiv.com && sh poll-deploy.sh >> poll-deploy.log 2>&1
   ```
   Also tick **"Send run details by email … only when the script terminates
   abnormally"** and set your address — `poll-deploy.sh` exits non-zero only on a
   real problem (unhealthy deploy, or the edge unreachable), so this pages you
   exactly then and stays quiet on the routine no-op cycles.
5. Select the task → **Run** to fire it once; check `poll-deploy.log` in the
   script directory.

DSM gotchas: don't use `--cpus` (DSM kernels lack the CFS scheduler — the
scripts don't); when replacing containers by hand, kill the old ones first so
ports `8080`/`8086` are free (`deploy.sh` only removes containers named
`johnhringiv-primary` / `johnhringiv-backup`).

## Rolling update (why the site stays up)

The host runs **two** app containers behind HAProxy: a primary and a backup.
HAProxy serves the primary and fails over to the backup when the primary's
`/nginx-health` check (see `config/nginx.conf`) stops answering.

`deploy.sh` recreates them **one at a time**, gating each on health *and
version*:

1. **Backup first**, on the new image. Wait until `localhost:<backup>/nginx-health`
   returns 200 **and reports the exact version baked into the pulled image**
   (`healthy <git-describe>`). The primary is untouched and still serving the
   old image throughout — if the backup never comes up healthy on the expected
   version, the deploy aborts here and the site is unaffected.
2. **Then the primary.** While it's down, its health check fails and HAProxy
   fails over to the freshly-updated backup; once the primary is healthy again
   HAProxy returns to it.

The version check (not just liveness) means a stale/half-broken container or a
bad pull that came up on the wrong build can't pass the gate and take the
primary down with it. At every moment at least one healthy instance is serving
→ zero downtime.

## Continuous deploy loop

`poll-deploy.sh`, run from cron:

- **Unchanged `:latest`, both containers running, edge healthy** → cheap no-op
  (one manifest check + one `/nginx-health` probe through Cloudflare).
- **Changed `:latest` digest** (a new push to `main` landed) → pulls and runs
  `deploy.sh`, then verifies the edge; exits non-zero if the edge never comes up.
- **A container not running on an unchanged tag** (e.g. after a host reboot,
  before Docker's `restart unless-stopped` has settled) → self-heals by
  redeploying both in order.
- **Both containers running but the edge unreachable** → exits non-zero
  *without* redeploying (Cloudflare/HAProxy/DNS trouble a redeploy won't fix).

## Rollback

Immediate stopgap — on the host, pin the last-good build and redeploy:

```sh
sudo IMAGE=ghcr.io/johnhringiv/johnhringiv.com:sha-<commit> sh deploy.sh
```

Get the `sha-<commit>` from the CI run (or `git log`) of the good commit. This
rolls back in seconds with no rebuild.

**But the pin is only a stopgap:** the poller watches `:latest`, so the next
cycle would re-pull the bad `:latest` and redeploy it. For a durable rollback,
also **`git revert` the bad commit and push** — CI republishes `:latest` as the
fixed build and the poller converges on it. Pin first to stop the bleeding,
then revert to make it stick.

## Offline / manual image (fallback)

Build and `docker save` anywhere, copy the tarball to the host, and pass it as
the sole argument — the pull is skipped and the loaded image is used:

```sh
docker build -t johnhringiv.com:local .
docker save johnhringiv.com:local | gzip > site.tar.gz
# on the host:
IMAGE=johnhringiv.com:local sh deploy.sh site.tar.gz
```

This replaces the old `docker save` → copy `.img` → `docker load` → recreate-
by-hand ritual for the cases where you still need it.

## Verify

```sh
curl https://johnhringiv.com/nginx-health          # healthy <git-describe>
curl -s https://johnhringiv.com/ | grep canonical  # sanity-check a page
docker ps --filter name=johnhringiv                # both containers Up
```
