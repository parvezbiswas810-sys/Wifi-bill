# Deployment guide

Target: standard cPanel hosting, PHP 8.3+ with MySQL/MariaDB. Written for
`v2.adsxuit.com` on `cpanel.adsxuit.com`, but the steps apply to any cPanel host.

The application uses no long-running processes. Everything scheduled runs from one cron
entry, which is what shared hosting supports.

## 1. Create the database

cPanel -> MySQL Databases:

1. Create a database, for example `adsxuitc_wifi`.
2. Create a user, for example `adsxuitc_wifi`, with a strong password.
3. Add the user to the database with **ALL PRIVILEGES**.

Note the full names. cPanel prefixes them with your account name.

## 2. Set the PHP version

cPanel -> MultiPHP Manager -> select the domain -> choose **PHP 8.3** or newer.

Then cPanel -> MultiPHP INI Editor, and raise these for the domain:

```
upload_max_filesize = 8M
post_max_size = 10M
max_execution_time = 300
memory_limit = 256M
```

The installer and `composer` both need more than the default execution time on the first
run.

## 3. Required PHP extensions

cPanel -> Select PHP Version -> Extensions. Confirm these are enabled:

```
pdo_mysql  mbstring  openssl  curl  json  bcmath  ctype  fileinfo  tokenizer  xml  dom  filter  hash  session
```

## 4. Upload the application

### Option A - File Manager (no SSH)

1. Build the frontend assets locally: `npm ci && npm run build`. The `public/build`
   directory must be uploaded; the server does not need Node.js.
2. Zip the project, excluding `.git`, `node_modules`, `tests` and `.env`.
3. cPanel -> File Manager -> upload into the directory the subdomain points at.
4. Extract the archive.
5. Move the contents of `public/` into the subdomain document root, then edit
   `public/index.php` so the two `require` paths point at the application directory:

   ```php
   require __DIR__.'/../isp-app/vendor/autoload.php';
   $app = require_once __DIR__.'/../isp-app/bootstrap/app.php';
   ```

   Keeping the application outside the document root is the safer layout: `.env`,
   `storage` and `vendor` are then not reachable over HTTP.

### Option B - SSH (if available)

```bash
cd ~
git clone <your-repo> isp-app
cd isp-app
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

## 5. Point the document root at `public`

Two acceptable layouts:

| Layout | Document root | Notes |
|--------|---------------|-------|
| Application outside the web root | `~/v2.adsxuit.com` (holds only `index.php`, `.htaccess`, `build/`, `storage-link`) | Most secure |
| Application inside the web root | `~/isp-app/public` | Requires changing the subdomain document root in cPanel -> Domains |

If the host will not let you change the document root, keep the application one level above
the document root and follow the `index.php` path change in Option A.

Add this `.htaccess` next to `index.php`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>
```

## 6. Configure the environment

Copy `.env.example` to `.env` and set at minimum:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://v2.adsxuit.com
APP_KEY=            # filled by php artisan key:generate

DB_DATABASE=adsxuitc_wifi
DB_USERNAME=adsxuitc_wifi
DB_PASSWORD=your-database-password

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database
```

`APP_DEBUG=false` is important: with debug on, exception pages leak configuration and
environment values to anyone who triggers an error.

Generate the key:

```bash
php artisan key:generate
```

**Keep `APP_KEY` safe and backed up.** Router credentials are encrypted with it. Losing the
key means every stored router password must be entered again.

## 7. Run the installer

### Web installer (no SSH)

1. Edit `deploy/install.php`: set a random `$INSTALL_TOKEN`, and fill in the database and
   administrator values.
2. Upload it to the application root.
3. Open `https://v2.adsxuit.com/install.php?token=YOUR_TOKEN`.
4. **Delete `install.php` immediately afterwards.** Leaving it in place allows anyone with
   the token to re-run migrations.

### SSH

```bash
php artisan migrate --force
SEED_ADMIN_PASSWORD='a-strong-password' php artisan db:seed --force
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

## 8. Add the scheduler cron entry

cPanel -> Cron Jobs -> Add New Cron Job. Common settings:

```
* * * * * cd /home/adsxuitc/isp-app && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

Find the real PHP path with `which php` over SSH, or use the PHP binary cPanel shows in
MultiPHP Manager. Without this entry, invoices are never generated and overdue customers
are never suspended.

To confirm it is working, run once by hand and check the output:

```bash
php artisan schedule:list
php artisan billing:generate-invoices
```

## 9. Enable HTTPS

cPanel -> SSL/TLS Status -> Run AutoSSL. Then confirm `APP_URL` starts with `https://` and
that `SESSION_SECURE_COOKIE=true` is set, so session cookies are never sent over plain
HTTP.

## 10. File permissions

```
storage/          775 (recursive)
bootstrap/cache/  775 (recursive)
.env              600
```

Use 755 for directories and 644 for files elsewhere. Do not use 777.

## 11. Configure MikroTik

Sign in at `https://v2.adsxuit.com/loginisp`, then go to **MikroTik routers** and add the
router.

On the router:

```
/ip service enable api
/user add name=billing password=STRONG_PASSWORD group=full
/ip firewall address-list add list=api-allowed address=<hosting-server-ip>
```

Then restrict the API service to that list:

```
/ip service set api address=<hosting-server-ip>/32
```

Press **Test connection**. If it fails, the message tells you which stage failed:
unreachable host, refused connection, authentication rejected, or TLS failure.

The hosting server must be able to reach the router on the API port. Credentials being
correct is not enough - a firewall between the two will still block the connection. If the
router is behind a home or office connection with a dynamic address, consider a VPN or a
reverse tunnel rather than exposing the API to the internet.

## 12. First-run checklist

1. Sign in at `/loginisp` and change the administrator password under Settings -> Security.
2. Set the ISP profile: name, contact number, address, timezone and currency.
3. Review the billing policy: default amount, due days, grace period, suspension time.
4. Add at least one payment method and enable it.
5. Add the MikroTik router and press **Test connection**.
6. Press **Sync PPP clients** and confirm accounts appear under PPP clients.
7. Add a customer and assign an existing PPP account.
8. Confirm the cron entry is running: check `storage/logs/laravel.log` and the
   synchronization log page.

## Backups

Back up all three of these:

1. **Database** - cPanel -> phpMyAdmin -> Export, or a cron `mysqldump`.
2. **`.env`** - contains `APP_KEY`; without it encrypted router credentials are lost.
3. **`storage/app`** - payment screenshots and generated files.

Restore is the reverse: import the database, restore `.env`, restore `storage/app`, then
run `php artisan config:cache`.

## Troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| 500 on every page | Missing `APP_KEY`, wrong DB credentials, or unreadable `storage/` | Check `storage/logs/laravel.log`; run `php artisan key:generate` |
| Blank page, no error | `APP_DEBUG=false` hiding a fatal error | Temporarily set `APP_DEBUG=true`, fix, then set it back to `false` |
| Assets unstyled | `public/build` not uploaded | Run `npm run build` locally and upload `public/build` |
| 403 from the router | Firewall or `api` service disabled | Enable the service and allow the server IP |
| 401 from the router | Wrong API username or password | Re-enter the password under Settings -> MikroTik |
| Router test times out | Port blocked or wrong host | Test the port from the server; check the router firewall |
| Invoices not generated | Cron entry missing | Add the cron entry and confirm with `php artisan schedule:list` |
| Session lost after login | `SESSION_SECURE_COOKIE=true` without HTTPS | Enable AutoSSL, or set the value to `false` while testing |
| "SQLSTATE: too many connections" | Cron running too often | Keep the cron at one entry per minute |
| `composer install` fails with `ext-fileinfo`/`ext-dom` missing | CloudLinux CLI PHP loads almost no extensions from the default ini set | Prefix commands with `PHP_INI_SCAN_DIR=/opt/alt/php84/etc/php.d.std` (see below) |
| `composer` fails with "The HOME or COMPOSER_HOME environment variable must be set" | Cron/PHP `exec()` has no `HOME` | Export `HOME` and `COMPOSER_HOME` before running composer |
| Every page 500s with "Your Composer dependencies require a PHP version >= 8.4.1. You are running 8.0.30" | The domain has no cPanel-generated PHP handler, so LiteSpeed falls back to the server default | Add the `AddHandler` block to the document root `.htaccess` (see below) |

### CloudLinux CLI PHP loads almost no extensions

On CloudLinux/cPanel hosts the `php` binary under `/opt/alt/` reads an ini set that omits
most extensions, so `composer install` reports `ext-fileinfo`, `ext-dom` and others as
missing even though they are installed. The full set lives in a parallel scan directory.
Export it for the command:

```bash
export PHP_INI_SCAN_DIR=/opt/alt/php84/etc/php.d.std
export HOME=/home/USERNAME
export COMPOSER_HOME=/home/USERNAME/.composer
/opt/alt/php84/usr/bin/php /usr/local/bin/composer install --no-dev --optimize-autoloader
```

Verify with `php -m | grep -E 'fileinfo|dom|phar'` before blaming the code.

### The web server runs a different PHP version than the CLI

MultiPHP Manager can report `ea-php84` for a domain while the site actually executes a
different version, because the version is applied through a cPanel-generated handler
block in the document root `.htaccess`. If that block is missing (common after moving
files in by hand, or when the document root is outside `public_html`), LiteSpeed falls
back to the server default and Composer's `platform_check.php` aborts every request.

Confirm what the site is really running by requesting a temporary `phpinfo()`-style file,
then make the handler explicit in the document root `.htaccess`:

```apache
# php -- BEGIN cPanel-generated handler, do not edit
<IfModule mime_module>
  AddHandler application/x-httpd-ea-php84 .php .php8 .phtml
</IfModule>
# php -- END cPanel-generated handler, do not edit
```

### The scheduler cron needs its own wrapper

cPanel cron runs with a minimal environment, so `php artisan schedule:run` can pick the
wrong binary or the reduced extension set. Point the cron entry at a small wrapper that
pins everything:

```bash
#!/bin/bash
export HOME=/home/USERNAME
export PHP_INI_SCAN_DIR=/opt/alt/php84/etc/php.d.std
cd /home/USERNAME/isp-app || exit 1
exec /opt/alt/php84/usr/bin/php -d memory_limit=512M artisan schedule:run \
  >> /home/USERNAME/isp-app/storage/logs/scheduler.log 2>&1
```

Then set the cron entry to `* * * * * bash /home/USERNAME/isp-app/cron-scheduler.sh`.
Watch `storage/logs/scheduler.log` for one line per scheduled job per minute.

### Aggressive WAFs block bulk requests from a single address

Hosts running Imunify360 or Cloudflare in front of cPanel will challenge or block an
address that issues many rapid requests (a deploy loop, a page-crawl smoke test). The
site keeps working for real users; the block is per-address and lifts on its own. Prefer
one batched request over many small ones, and keep a cPanel API token handy so cleanup
can still be done through the control panel.

## Security hardening after install

- Delete `install.php`.
- Set `APP_DEBUG=false` and `APP_ENV=production`.
- Give the database user only the privileges the application needs, not `GRANT`.
- Use a dedicated MikroTik API user with the narrowest policy set that still works.
- Restrict the MikroTik API service to the hosting server's address.
- Keep `storage` and `vendor` outside the document root.
- Review the audit log periodically for unexpected financial changes.
