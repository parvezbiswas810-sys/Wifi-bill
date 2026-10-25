<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>

---

# ISP Billing Application — working notes

## Architecture

- **Multi-tenant by ISP.** Every ISP-owned model uses the `BelongsToIsp` trait, which adds a
  global scope on `isp_id`. The active tenant comes from `bind_isp()` / `current_isp_id()`
  in `app/Support/helpers.php`, which checks the `isp`, `customer` and `web` guards in turn.
  When you need to cross tenants deliberately (console commands, cross-checks), call
  `withoutGlobalScope('isp')` and scope by `isp_id` yourself.
- **Two guards.** Administrators use `isp`, customers use `customer`. They are separate
  providers over the same `users` and `customers` tables. A request that lands on the wrong
  guard is redirected by `redirectGuestsTo` in `bootstrap/app.php`.
- **Service layer owns business rules.** Controllers validate and delegate. The rules live in
  `app/Services/`:
  - `InvoiceService` — per-customer schedules; idempotent via the unique index on
    `(customer_id, period_key)`.
  - `SuspensionService` — owns every write to `internet_status`,
    `suspension_owned_by_billing`, `last_suspended_at` and `last_restored_at`.
  - `PaymentService` — transactional approval; idempotent via the unique index on
    `payments.submission_id`.
  - `MikrotikService` — talks to the router only. It never writes customer state.

## Conventions that matter

- **Model `$attributes` mirror database defaults.** Eloquent does not apply DB defaults to an
  in-memory model. Without this, a freshly created `Customer` reports `auto_suspend = null`
  and suspension silently skips it. If you add a boolean or numeric column with a DB default
  that any business rule reads, add it to `$attributes` too.
- **Never report a router action as done until RouterOS confirms it.** All
  `MikrotikService` methods return `['ok' =>, 'message' =>, 'verified' =>]`. Only
  `verified === true` may drive a state change.
- **Restoration is ownership-gated.** Only restore when `suspension_owned_by_billing` is
  true. An account disabled by hand on the router must never be re-enabled by billing.
- **Connectivity comes from live sessions**, not from a PPP secret being enabled. See
  `Customer::isOnline()` and `PppAccount::is_active_session`.
- **Portable SQL.** `DATE_FORMAT` is MySQL-only. Use the `monthExpression()` helper pattern in
  `DashboardController` / `ReportController` so SQLite keeps working in tests.

## Testing

```bash
php artisan test                       # 55 tests, all must pass
php artisan app:smoke-test --fresh     # renders every page; refuses to run in production
```

Tests use SQLite in memory (`phpunit.xml`). After any change to `app/Support/helpers.php`
run `composer dump-autoload` — the file is autoloaded through composer's `files` entry.

**SQLite hides MySQL-only failures, so a green suite does not mean the install works.**
Two shipped bugs proved this, both fatal on the cPanel target and invisible locally:

1. A **circular foreign key** (`payment_submissions.payment_id` ↔ `payments.submission_id`)
   declared on both tables at creation time. MySQL refuses it with errno 150 and `migrate`
   dies partway; SQLite accepts it silently. A cycle must be broken by creating one column
   bare and attaching its constraint in a later migration.
2. `key:generate` and similar commands read `$_ENV` before `$_SERVER`, and an exported
   `APP_KEY`/`APP_ENV`/`DB_CONNECTION` in the shell overrides `.env` entirely.

Before declaring a release good, install from scratch into a real MySQL/MariaDB using the
production credentials and exercise the billing and payment paths. See `DEPLOYMENT.md`.

Always run `php artisan config:clear` before the suite if you previously ran
`config:cache` for a production check; a cached config makes every test 419.

## Deployment

Target is cPanel at `v2.adsxuit.com`. See `DEPLOYMENT.md`. Two things are easy to forget:

1. The scheduler cron entry. Without it, no invoices are generated and nobody is suspended.
2. `deploy/install.php` must be deleted after a successful install.

### Live deployment facts (cPanel, as installed)

| Thing | Value |
|---|---|
| Account / home | `adsxuitc` / `/home/adsxuitc` |
| Application | `/home/adsxuitc/isp-app` (outside the web root) |
| Document root | `/home/adsxuitc/v2.adsxuit.com` |
| PHP (web) | `ea-php84` via the `AddHandler` block in the docroot `.htaccess` |
| PHP (CLI) | `/opt/alt/php84/usr/bin/php` with `PHP_INI_SCAN_DIR=/opt/alt/php84/etc/php.d.std` |
| Scheduler | `* * * * * bash /home/adsxuitc/isp-app/cron-scheduler.sh` |

Three traps that cost real time on the first deploy, all now documented in `DEPLOYMENT.md`:

1. **CLI PHP loads almost no extensions.** The `/opt/alt/php84` binary reads an ini set
   without `fileinfo`/`dom`, so `composer install` fails on platform requirements even
   though the extensions exist. `PHP_INI_SCAN_DIR=/opt/alt/php84/etc/php.d.std` fixes it.
   Composer also needs `HOME` and `COMPOSER_HOME` exported; `exec()` from a PHP script has
   neither.
2. **The web SAPI can run a different PHP than the CLI.** MultiPHP Manager reported
   `ea-php84`, but the site executed 8.0.30 because the docroot `.htaccess` had no
   cPanel-generated `AddHandler` block, so LiteSpeed used the server default. Composer's
   `platform_check.php` then aborted every request with a 500. Setting the version through
   `LangPHP::php_set_vhost_versions` is not enough on its own — the handler block is what
   the running config actually reads.
3. **`public/build` is gitignored.** A clone has no compiled assets, and `@vite` throws
   "Vite manifest not found". Build locally (`npm run build`) and upload `public/build`
   into the **document root** (`<docroot>/build`), not just into `isp-app/public/build` —
   the docroot is what the browser reads.

When repairing the docroot, remember that copying `deploy/.htaccess` over the live
`.htaccess` drops the PHP handler and takes the site down with a version-mismatch 500.
Re-apply the handler after any such copy.
