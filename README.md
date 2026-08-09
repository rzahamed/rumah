# baseCMS-Starter

A generic, de-branded, environment-driven CMS starter (Laravel + Filament,
PostgreSQL only). Each client is a separate repository and database — no
shared-client data model exists, and no client naming, branding, or domain
logic lives in starter code.

## Architecture

- **Two hosts, fail-closed.** The public site is served only on the host
  parsed from `PUBLIC_APP_URL`. The Filament admin panel is mounted at the
  **root path** of the dedicated `ADMIN_DOMAIN` host — the string `/admin`
  is never used as a path. Missing `PUBLIC_APP_URL` or `ADMIN_DOMAIN` throws
  at boot; unknown hosts return 404. All admin responses carry
  `X-Robots-Tag: noindex`.
- **PostgreSQL only** — in development, tests, CI, and production. No
  SQLite or MySQL code paths exist. The PG session timezone is pinned to
  UTC so `timestamptz` columns store the intended instant on any machine.
- **Bilingual, env-driven.** `SUPPORTED_LOCALES` / `RTL_LOCALES` /
  `APP_LOCALE` control locales everywhere. Translatable content is stored
  as locale-keyed JSONB (`{"en": …, "ar": …}`), so the schema is valid for
  any locale set. Admin users have a per-user panel language.
- **Roles.** `super_admin` (all abilities via an active-only `Gate::before`
  override), `admin` (user, content, and form administration), `editor`
  (posts, categories, team only). Every policy also requires the acting
  user to be status-active.

## Modules (implemented and test-verified)

| Module | Notes |
|---|---|
| Users & invitations | Invite-only creation (no password fields in the panel). Single-use SHA-256-hashed tokens, `INVITATION_EXPIRY_DAYS` expiry, resend, indistinguishable 404s for invalid tokens, rate limiting. Last-active-super-admin cannot be deleted, deactivated, or demoted; role changes require `users.manage_roles`. Anti-enumeration password reset. Last-login tracking. |
| Posts & categories | Draft/published with a required publish date for published posts; `published()` scope hides future-dated posts; deleting a category never deletes posts. |
| Team members | Sort order, visibility, photo upload on the configured media disk with commit-safe file lifecycle (replacement and deletion remove files only after the DB change commits). |
| Forms & submissions | Admin-defined forms (5 field types, per-locale labels, unique machine names, max 20 fields). Public submission endpoints (default + localized) validate against the form's own definition, store only declared fields, collect no requester metadata, and are rate limited. Submissions are protected admin data: list/view/single-delete only — no create/edit routes, no bulk actions; forms with submissions cannot be deleted (`restrictOnDelete`). |

## Requirements

- PHP `^8.3` (see composer.json), Composer
- PostgreSQL (developed and verified against 16)
- A hostname-aware local server for the two hosts (Laravel Herd, Valet, or
  a reverse proxy). `php artisan serve` alone cannot serve both hosts on
  port 80.
- Redis (predis client) for the default `.env` session/cache/queue drivers.
  Without a local Redis, artisan commands work with an inline override,
  e.g. `CACHE_STORE=array php artisan db:seed`.

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan storage:link   # makes locally stored public media (team photos) web-accessible
```

1. Add hosts entries for your chosen hostnames (the values below are
   per-client **examples**, not fixed names):

   ```
   127.0.0.1 example-cms.test admin.example-cms.test
   ```

2. Provision the databases. Names and roles are per-client **examples** —
   choose your own, and set passwords locally (never commit them):

   ```sql
   CREATE ROLE example_app LOGIN;
   CREATE DATABASE example_cms OWNER example_app;
   REVOKE CONNECT ON DATABASE example_cms FROM PUBLIC;
   -- repeat for the dedicated test database, e.g. example_cms_testing
   ```

3. Fill `.env`: `APP_URL`, `PUBLIC_APP_URL`, `ADMIN_APP_URL`,
   `ADMIN_DOMAIN`, the `DB_*` values, and (optionally) locale settings.

4. Migrate, seed, and create the first super administrator:

   ```bash
   php artisan migrate
   php artisan db:seed
   php artisan users:create-super-admin
   ```

   The command is interactive, enforces the password policy (min 12 chars,
   letters + numbers), and refuses to run once a super administrator
   exists — later administrators are invited from the panel.

5. Run a queue worker. Invitation notifications are **queued**
   (`QUEUE_CONNECTION=redis` by default) — invitations are not delivered
   until a worker processes them:

   ```bash
   php artisan queue:work
   ```

   The test suite runs the queue synchronously, so tests need no worker.

## Testing

- The suite runs **only** against a dedicated PostgreSQL test database.
  `phpunit.xml` pins the connection with `force="true"`, `DB_URL` is pinned
  empty, and a fail-fast guard (`tests/Support/TestDatabaseGuard`) refuses
  any resolved database whose name does not end in `_testing`/`_test`.
- `php artisan test` requires a `.env` to exist; bare `vendor/bin/phpunit`
  runs from `phpunit.xml` (plus the git-ignored `.env.testing` for local DB
  credentials). Test host values come from `phpunit.xml`, not
  `.env.testing`.
- `RefreshDatabase` migrates the test database automatically. The media
  lifecycle tests use `DatabaseMigrations` so after-commit file deletion is
  exercised with real commits.

## Media & Google Cloud Storage

- `MEDIA_DISK` selects the media disk (default `public`, the local disk).
  Uploads and their lifecycle are covered by tests on fake storage.
- Two GCS disks are configured (`gcs_private`, `gcs_public_website`) using
  `spatie/laravel-google-cloud-storage`. Both use **Uniform Bucket-Level
  Access** (public exposure via bucket IAM, never per-object ACLs) and fail
  loudly (`throw`/`report`). Clients are constructed only when a disk is
  resolved — the definitions alone never contact GCP.
- Credentials: an empty `GOOGLE_CLOUD_KEY_FILE` uses **Application Default
  Credentials**; set a key-file path only when explicitly needed.
- Public URLs: `PUBLIC_ASSET_URL`, when set, must be the base-through-bucket
  URL (e.g. a CDN host bound to the bucket) **without** the path prefix —
  the adapter appends `prefix/stored-path` itself. Otherwise the base is
  derived from `PUBLIC_ASSET_BASE_URL` + bucket.
- Local Application Default Credentials (when not using a key file):

  ```bash
  gcloud auth application-default login
  ```

  Add the real project/bucket values to the uncommitted `.env` **before**
  resolving a GCS disk. Real GCS smoke testing is still pending.
- **Not yet verified against real GCP**: no live uploads have been run;
  configuration and behavior are covered by tests that never touch the
  network. `GOOGLE_CLOUD_SIGNING_SERVICE_ACCOUNT` and `BLOG_FEATURED_*`
  are reserved for planned features and are not yet consumed by code.

## Deployment notes

- All environment-specific behavior is env-driven — production swaps values
  only, no code changes. `config:cache` and `route:cache` are verified to
  work.
- **Planned — not implemented**: production infrastructure, a real mail
  transport (local default is `MAIL_MAILER=log`; the Resend SDK is
  installed but no transport is configured), production PostgreSQL
  authentication hardening, and live GCS verification.

## Creating a client application

1. Create a **new repository** from this starter (one application per
   client).
2. Replace every per-project identifier: `APP_NAME`, both hostnames, the
   database name and role, `SESSION_COOKIE`, `CACHE_PREFIX`,
   `REDIS_PREFIX`, `REDIS_QUEUE`. Never reuse the example names.
3. Client-specific frontend, branding, and CMS additions live only in the
   client repository — never in this starter.
4. Provision databases, run the setup above, and run `php artisan test`
   before building client features.
