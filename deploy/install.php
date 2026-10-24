<?php
/**
 * One-shot web installer for cPanel hosting without SSH access.
 *
 * Upload the application to the domain root, then open:
 *   https://your-domain.com/install.php?token=CHANGE_ME
 *
 * It writes .env, generates the application key, runs migrations, seeds the
 * first administrator and caches the configuration.
 *
 * SECURITY: delete this file immediately after a successful install.
 */

declare(strict_types=1);

// ---- 1. Change this token before uploading -------------------------------
$INSTALL_TOKEN = 'CHANGE_ME_BEFORE_UPLOAD';

// ---- 2. Pre-fill these values -------------------------------------------
$DB_DATABASE = 'youruser_wifi';
$DB_USERNAME = 'youruser_wifi';
$DB_PASSWORD = '';
$APP_URL     = 'https://v2.adsxuit.com';
$ISP_NAME    = 'My ISP';
$ADMIN_USER  = 'admin';
$ADMIN_EMAIL = 'admin@example.com';
$ADMIN_PASS  = '';   // leave blank to generate a random password

// -------------------------------------------------------------------------

if (($INSTALL_TOKEN === 'CHANGE_ME_BEFORE_UPLOAD') || ($_GET['token'] ?? '') !== $INSTALL_TOKEN) {
    http_response_code(403);
    exit('Forbidden: set a token in install.php and pass it as ?token=');
}

$root = __DIR__;
if (is_dir($root.'/../vendor') && ! is_dir($root.'/vendor')) {
    // install.php sits in the Laravel root in this deployment layout.
}

header('Content-Type: text/plain; charset=utf-8');
set_time_limit(600);

function step(string $label, callable $fn): void
{
    echo str_pad($label.' ', 46, '.');
    try {
        $result = $fn();
        echo " OK\n";
        if (is_string($result) && $result !== '') {
            echo "    {$result}\n";
        }
    } catch (Throwable $e) {
        echo " FAILED\n    ".$e->getMessage()."\n";
    }
}

echo "Laravel ISP Billing installer\n";
echo str_repeat('=', 60)."\n\n";

// 1. .env
step('.env file', function () use ($root, $DB_DATABASE, $DB_USERNAME, $DB_PASSWORD, $APP_URL, $ISP_NAME, $ADMIN_USER, $ADMIN_EMAIL, $ADMIN_PASS) {
    if (file_exists($root.'/.env')) {
        return 'already exists - left untouched';
    }
    $example = file_get_contents($root.'/.env.example');
    if ($example === false) {
        throw new RuntimeException('.env.example not found');
    }
    $map = [
        'APP_NAME' => '"'.$ISP_NAME.' Billing"',
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'APP_URL' => $APP_URL,
        'DB_DATABASE' => $DB_DATABASE,
        'DB_USERNAME' => $DB_USERNAME,
        'DB_PASSWORD' => $DB_PASSWORD,
        'SEED_ISP_NAME' => '"'.$ISP_NAME.'"',
        'SEED_ADMIN_USERNAME' => $ADMIN_USER,
        'SEED_ADMIN_EMAIL' => $ADMIN_EMAIL,
        'SEED_ADMIN_PASSWORD' => $ADMIN_PASS,
    ];
    foreach ($map as $key => $value) {
        $example = preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $key.'='.$value, $example);
    }
    file_put_contents($root.'/.env', $example);
    return 'written';
});

// 2. bootstrap
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// 3. key
step('Application key', function () use ($kernel) {
    if (config('app.key')) {
        return 'already set';
    }
    $kernel->call('key:generate', ['--force' => true]);
    return trim($kernel->output());
});

// 4. database
step('Database connection', function () {
    $pdo = new PDO(
        'mysql:host='.config('database.connections.mysql.host').';dbname='.config('database.connections.mysql.database'),
        config('database.connections.mysql.username'),
        config('database.connections.mysql.password'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    return 'connected to '.config('database.connections.mysql.database');
});

// 5. migrate
step('Migrations', function () use ($kernel) {
    $kernel->call('migrate', ['--force' => true]);
    return trim($kernel->output());
});

// 6. seed
step('Seed administrator and payment methods', function () use ($kernel) {
    $kernel->call('db:seed', ['--force' => true]);
    return trim($kernel->output());
});

// 7. storage link
step('Storage symlink', function () use ($kernel) {
    $kernel->call('storage:link');
    return trim($kernel->output());
});

// 8. caches
step('Configuration cache', function () use ($kernel) {
    $kernel->call('config:cache');
    $kernel->call('route:cache');
    $kernel->call('view:cache');
    return 'cached';
});

echo "\n".str_repeat('=', 60)."\n";
echo "Install finished.\n\n";
echo "NEXT STEPS\n";
echo "  1. DELETE this install.php file immediately.\n";
echo "  2. Add the scheduler cron entry in cPanel:\n";
echo "     * * * * * cd {$root} && ".PHP_BINARY." artisan schedule:run >> /dev/null 2>&1\n";
echo "  3. Sign in at {$APP_URL}/loginisp\n";
echo "  4. Add your MikroTik router under Settings, then test the connection.\n";
