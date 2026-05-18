# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Working in This Repo

**Always read the code before asking questions or making suggestions.** This means: read the relevant files first, every time. Check `web/app/plugins/`, `web/app/mu-plugins/`, `composer.json`, `config/`, and `.github/workflows/` before forming any opinion about what the site does, how it works, or what might be wrong. The plugins directory in particular reveals the site's purpose and architecture — do not skip it. Only ask the user a question if the answer genuinely cannot be determined from the codebase.

**For any Terminus-related task**, invoke the `terminus` skill before proceeding.

## What This Is

`s3q.us` is Chris Reynolds' personal URL shortener. WordPress is the backend; [Safe Redirect Manager](https://wordpress.org/plugins/safe-redirect-manager/) stores redirects as `redirect_rule` posts. The core workflow: a browser bookmarklet POSTs to a custom REST endpoint (`/wp-json/redirect-manager/v1/add`), which creates a redirect rule, and Safe Redirect Manager handles the actual redirect when a short URL is hit.

The site is intentionally single-owner. All logins except User ID 1 are hard-blocked via `s3q-limit-logins.php`. There is no multi-user access model; do not add one.

Built on [Pantheon's WordPress (Composer Managed)](https://github.com/pantheon-systems/wordpress-composer-managed) upstream — a [Bedrock](https://roots.io/bedrock)-style layout. Code lives in GitHub; GitHub Actions build and push to Pantheon via the [`push-to-pantheon`](https://github.com/pantheon-systems/push-to-pantheon) action. Pantheon is the runtime; GitHub is the source of truth.

- **PHP**: 8.3 (platform target in `composer.json`; `pantheon.yml` sets runtime)
- **Database**: MariaDB 10.6
- **Object cache**: Redis + Object Cache Pro; token fetched at runtime via `pantheon_get_secret('ocp_token')`
- **Pantheon site name**: `cxr-s3q-us`

## Commands

```bash
composer install          # Install all dependencies
composer lint             # PHP syntax check + PHPCS + shellcheck
composer lint:phpcs       # PHPCS only (what CI runs)
composer lint:phpcbf      # Auto-fix PHPCS violations
composer lint:bash        # shellcheck private/scripts/*.sh

composer deploy           # Deploy test + live via Terminus (requires auth)
composer push             # git push origin main, then wait for dev workflow
composer update-deps      # composer update, commit composer.* (run manually with --ignore-platform-reqs locally)
composer update-and-deploy # update-deps + push + deploy

composer update-ocp-drop-in  # Regenerate Object Cache Pro drop-in via SFTP
```

Local `composer update` requires `--ignore-platform-reqs` — the local environment lacks `ext-redis`, which Object Cache Pro requires, causing dependency resolution to fail without the flag.

There are no PHP unit tests (`"test": []` in `composer.json`). Lint is the quality gate.

## Directory Layout (Bedrock)

```
config/
  application.php         # Main WP config (env-driven via vlucas/phpdotenv)
  application.pantheon.php # Pantheon-specific overrides
web/
  wp/                     # WordPress core (managed by Composer, not edited)
  app/                    # wp-content equivalent (CONTENT_DIR = /app)
    mu-plugins/           # Must-use plugins
    plugins/              # Composer-installed plugins
    themes/               # Themes (only twentytwentyfive)
    object-cache.php      # OCP drop-in (copied from plugin stubs by post-install-cmd)
  index.php               # WP entry point
private/
  scripts/
    helpers.sh            # Symlink creation (post-install), Sage install helper
    slack-notification.php # Quicksilver webhook (runs on Pantheon, not locally)
upstream-configuration/   # Pantheon upstream config (treat as read-only)
```

WordPress core is installed into `web/wp/`. A post-install Composer hook (`maybe-add-symlinks`) creates symlinks from `web/` to `web/wp/*` for files that WP expects at the webroot (except `index.php` and `wp-settings.php`, which are managed separately).

## Configuration

Environment variables drive all WP config via `vlucas/phpdotenv`. On Pantheon, `$_ENV['PANTHEON_ENVIRONMENT']` is set automatically. Locally, create a `.env` file with `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `WP_HOME`, `WP_SITEURL`, etc. `.env.local` overrides `.env`.

`WP_DEBUG` / `WP_DEBUG_LOG` are enabled on `dev`, `test`, and local environments; disabled on `live`.

OCP serializer is `igbinary` + `zstd` on Pantheon; falls back to `php` + `none` under Lando (`$_ENV['LANDO'] === 'ON'`).

## GitHub Actions

| Workflow | Trigger | What it does |
|---|---|---|
| `deploy-to-pantheon.yml` | push to `main` | Runs PHPCS, then pushes to Pantheon dev via `push-to-pantheon@0.7.0` |
| `deploy-pr.yml` | PR targeting `main` | Same, but targets multidev `pr-{number}` |
| `composer-diff.yml` | PR touches `composer.lock` | Posts a collapsible composer diff comment on the PR |

Changes to `.github/`, `README.md`, `CHANGELOG.md`, `web/app/object-cache.php`, `private/scripts/*`, and `upstream-configuration/**` are excluded from deploy triggers — they do not push to Pantheon.

## Custom MU-Plugins

`web/app/mu-plugins/s3q-limit-logins.php` — site-specific plugin that restricts WordPress logins to User ID 1 only. Any other account is blocked at the `authenticate` filter. This is intentional; don't remove it.

`web/app/mu-plugins/filters.php` — upstream Pantheon filters for Composer-managed multisite config handling.

## PHPCS

Uses the `Pantheon-WP` ruleset (`phpcs.xml`). Scans all `.php` files in the repo except:
- `web/wp/`, all Composer-installed plugins/themes, `vendor/`, `upstream-configuration/`, `config/application.*`

The `private/scripts/*.php` Quicksilver scripts are excluded from nonce/escaping rules since they run as CLI-style scripts on Pantheon.

## Remotes

```bash
# Pantheon git remote (for OCP drop-in cherry-picks)
site_id=$(terminus site:info cxr-s3q-us --fields=id --format=list)
git remote add pantheon ssh://codeserver.dev."$site_id"@codeserver.dev."$site_id".drush.in:2222/~/repository.git

# Upstream remote (pull updates from the WordPress Composer Managed upstream)
git remote add upstream git@github.com:pantheon-upstreams/wordpress-composer-managed.git
```

## Quicksilver

`pantheon.yml` hooks `private/scripts/slack-notification.php` into four workflow events: multidev creation, deploy, code sync, and cache clear. These run server-side on Pantheon — they are not invoked locally.
