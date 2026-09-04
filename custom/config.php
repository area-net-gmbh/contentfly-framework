<?php
use \Areanet\PIM\Classes\Config\Factory;

/*
 * ─── Environment file loading ───────────────────────────────────────────
 *
 * Everything below (and the rest of the app) reads plain `$_ENV`/getenv().
 * On a host that can inject real process environment variables — docker
 * compose `env_file:`, systemd, a hosting panel — nothing here is needed and
 * this block is a no-op. It exists for hosts that can only place a file
 * (Mittwald Managed PHP), where without it the app would silently fall back
 * to the committed dev defaults.
 *
 * Resolution order, first existing file wins:
 *   1. $USABIQ_ENV_FILE  — explicit absolute path, overrides everything
 *   2. /etc/usabiq/production.env  — the server path documented in the
 *      hosting plan (docs/operations/hosting/…-ovhcloud-cloudflare.md §5)
 *   3. <repo-root>/.env  — one level ABOVE the document root (which is
 *      `backend/`), so it is never web-reachable
 *
 * SECURITY — the file must NEVER live inside the document root. `.htaccess`
 * only rewrites requests for paths that do not exist on disk
 * (`RewriteCond %{REQUEST_FILENAME} !-f`), and Apache's default config blocks
 * only `.ht*` — a `.env` next to index.php would be served in plain text.
 * Lock it down with `chmod 600` and an owner the webserver can read.
 *
 * Loaded IMMUTABLY on purpose: a real process environment variable always
 * beats the file. That way a panel-provided secret cannot be silently
 * overridden by a stale deployed file.
 */
if (class_exists(\Dotenv\Dotenv::class)) {
    $usabiqEnvCandidates = [];

    $usabiqEnvFileOverride = $_ENV['USABIQ_ENV_FILE'] ?? getenv('USABIQ_ENV_FILE') ?: null;
    if (is_string($usabiqEnvFileOverride) && $usabiqEnvFileOverride !== '') {
        $usabiqEnvCandidates[] = $usabiqEnvFileOverride;
    }
    $usabiqEnvCandidates[] = '/etc/usabiq/production.env';
    // ROOT_DIR is `backend/` (defined in lib/contentfly/bootstrap.php), so this
    // resolves to the repository root — outside the document root.
    $usabiqEnvCandidates[] = (defined('ROOT_DIR') ? ROOT_DIR : __DIR__ . '/..') . '/../.env';

    foreach ($usabiqEnvCandidates as $usabiqEnvCandidate) {
        if (!is_file($usabiqEnvCandidate) || !is_readable($usabiqEnvCandidate)) {
            continue;
        }
        // createImmutable() never overwrites an existing env var; safeLoad()
        // does not throw on a malformed or vanished file — a broken secrets
        // file must not take the whole application down at boot.
        \Dotenv\Dotenv::createImmutable(
            dirname($usabiqEnvCandidate),
            basename($usabiqEnvCandidate)
        )->safeLoad();
        break;
    }

    unset($usabiqEnvCandidates, $usabiqEnvCandidate, $usabiqEnvFileOverride);
}

$configFactory = Factory::getInstance();

/*
 ************************************************************************************************
 * 
 * LOCAL DEV
 * 
 ************************************************************************************************ 
 */

$configDefault = new \Areanet\PIM\Classes\Config();

$configDefault->DB_HOST                 = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'usabiq_db';
$configDefault->DB_NAME                 = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'usabiq';
$configDefault->DB_USER                 = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'usabiq';
$configDefault->DB_PASS                 = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: 'usabiq';
$configDefault->DB_GUID_STRATEGY        = true;

// DATETIME — Task 117 / ADR 2026-07-29: every persisted instant is UTC; a timezone
// is presentation only. This overrides the contentfly framework default of
// 'Europe/Berlin' (lib/contentfly/Classes/Config.php) WITHOUT touching lib/, and is
// what bootstrap.php feeds to date_default_timezone_set(). The MySQL session is
// pinned to +00:00 alongside it (both DBAL connections in bootstrap.php), so PHP's
// clock and MySQL's NOW() agree — that equality is what keeps the retention and
// PII-purge jobs comparing like against like.
$configDefault->APP_TIMEZONE            = 'UTC';

// SECURITY — TOP-1.13: APP_DEBUG drives verbose error output incl. full stack
// traces in API/HTML responses (bootstrap-web.php). It must default to OFF in
// production. Resolution: explicit APP_DEBUG env wins; otherwise on for a known
// dev APP_ENV (or unset, which docker-compose leaves blank), off for prod.
// Inlined (not via AppEnv) because config.php loads before the class autoloader
// is guaranteed ready.
$appEnvRaw  = strtolower(trim((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'dev')));
$isDevEnv   = in_array($appEnvRaw, ['dev', 'development', 'test', 'local'], true);
$appDebugEnv = $_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG');
$configDefault->APP_DEBUG = ($appDebugEnv !== false && $appDebugEnv !== null && $appDebugEnv !== '')
    ? filter_var($appDebugEnv, FILTER_VALIDATE_BOOLEAN)
    : $isDevEnv;
$configDefault->APP_ENABLE_SCHEMA_CACHE = false;
$configDefault->APP_AUTOGENERATE_PROXIES = 2; // AUTOGENERATE_FILE_NOT_EXISTS — avoids race conditions on concurrent requests

$configDefault->APP_ALLOW_HEADERS_SDK   = 'Access-Control-Allow-Origin,Access-Control-Allow-Methods,Access-Control-Allow-Headers,contentfly-ionic,content-type,content-security-policy,x-origin-host,x-content-type-options,cache-control,strict-transport-security,x-xsrf-token,appcms-token,authorization';
// CSP for the legacy contentfly bootstrap path. The authoritative CSP is set
// in app.php (after-hook) — see TOP-5. Dropped 'unsafe-eval' here so even
// fall-through responses no longer permit eval(); 'unsafe-inline' kept for
// inline styles that legacy admin pages rely on.
$configDefault->APP_CS_POLICY           = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:;";

// CORS: Allowed origins for production. Null/empty = allow all (dev only).
// In production, set USABIQ_CORS_ORIGINS env var to comma-separated patterns: "*.usabiq.com,app.usabiq.com,admin.usabiq.com"
// This is read directly in bootstrap-web.php (not via Config class to avoid dynamic property issues).

$configDefault->SECURITY_CIPHER_KEY     = $_ENV['SECURITY_CIPHER_KEY'] ?? getenv('SECURITY_CIPHER_KEY') ?: 'Gcbg2480xHGWzw25%bnw';
$configDefault->SECURITY_CIPHER_METHOD  = "AES-256-CBC";

$configFactory->setConfig($configDefault);
/*
 ************************************************************************************************
 * 
 * LIVE Server
 * 
 ************************************************************************************************ 
 */
$configLive = new \Areanet\PIM\Classes\Config('api.usabiq.com', $configDefault);
$configLive->APP_CS_POLICY = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self' *.usabiq.com;";

$configFactory->setConfig($configLive);