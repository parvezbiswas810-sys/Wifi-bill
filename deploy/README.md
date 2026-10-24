# Deployment helpers

## `install.php`

A one-shot web installer for hosts without SSH. It writes `.env`, generates the
application key, runs migrations, seeds the first administrator and caches the config.

Before uploading:

1. Change `$INSTALL_TOKEN` to a long random string. The installer refuses to run
   while it is left empty, so it cannot be triggered by accident.
2. Fill in the database name, user, password and the administrator details.
3. Upload to the application root and open `/install.php?token=YOUR_TOKEN`.
4. **Delete it immediately after the install finishes.**

The version committed to git carries placeholder values, because the repository is
public. `build.sh` injects the real token and database password into the staged copy
before packing the tarball, so the shipped archive installs without hand-editing while
the repository stays free of live credentials.

## `index.php` and `.htaccess`

For the recommended layout where the application sits outside the document root:

```
/home/USER/isp-app/            <- the Laravel application
/home/USER/v2.domain.com/      <- document root, contains only:
    index.php                  <- from this directory
    .htaccess                  <- from this directory
    build/                     <- copied from isp-app/public/build
    storage/                   <- symlink to isp-app/storage/app/public
```

Set `$appPath` in `index.php` if the application is not at `../isp-app`.

Create the storage symlink over SSH with `php artisan storage:link`. Without SSH, create it
from cPanel -> File Manager by making `storage` a symlink, or simply copy
`isp-app/storage/app/public` into the document root as `storage`.

## Cron entry

```
* * * * * cd /home/USER/isp-app && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

Use the PHP binary reported by `which php` on the server.
