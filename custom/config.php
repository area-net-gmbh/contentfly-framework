<?php
/**
 * Project configuration — the values a project changes relative to the framework defaults.
 * Anything not set here comes from `Areanet\PIM\Classes\Config`.
 *
 * This file is a **template**. The `$SET_*` placeholders below are not a mistake:
 * `php bin/console.php appcms:install` replaces them with the credentials entered.
 * As long as `DB_HOST` is `$SET_DB_HOST`, the system counts as **not installed**
 * (`$app['is_installed']` in `lib/contentfly/bootstrap.php`) — the installer checks
 * exactly that.
 */

use Areanet\PIM\Classes\Config;
use Areanet\PIM\Classes\Config\Factory;

/*
 * ─── Optional: load values from a .env file ────────────────────────────────────────
 *
 * Only needed on hosts that cannot set real process environment variables
 * (managed PHP). Where `docker compose`, systemd or a hosting panel provides the
 * variables, this block is a no-op — the rest of the file only reads `$_ENV`
 * or `getenv()` anyway.
 *
 * SECURITY: The file must **never** live in the document root. The `.htaccess` only rewrites
 * requests for which no file exists (`RewriteCond %{REQUEST_FILENAME} !-f`),
 * and Apache itself only blocks `.ht*` — a `.env` next to `index.php` would be served in
 * plain text. Set permissions to `600`.
 *
 * `createImmutable()` leaves an already set environment variable alone: a real value
 * from the process environment beats a stale file. `safeLoad()` does not throw
 * when the file is missing or broken — a defective secrets file must not kill the
 * application at startup.
 *
 * THE `class_exists` STAYS — reviewed and deliberately kept (`006-004-0003`).
 *
 * `tools/dependency-assignment.json` states that the guard was only needed while
 * `vlucas/phpdotenv` lived in `custom/` and could be missing, and is unnecessary in the root.
 * For *this* repo that is true: since `006-002` the package is in the root manifest, and the
 * branch is always true here.
 *
 * But this file is the **template** an existing project receives — and that project's
 * root manifest only knows `vlucas/phpdotenv` once it has been migrated. Without the guard the
 * application would die at startup right there, with a `Class not found` instead of a statement
 * about the missing dependency. The path there is Epic `007` and not yet decided.
 *
 * Keeping it is the reversible choice, removing it is not. Revisit once `007` has settled
 * how a project gets to the root manifest. (As of 007-001: the framework becomes a
 * library package, the project keeps its own manifest — see architecture.md.)
 */
if (class_exists(\Dotenv\Dotenv::class)) {
    $envFile = $_ENV['CONTENTFLY_ENV_FILE'] ?? getenv('CONTENTFLY_ENV_FILE') ?: null;
    if (!is_string($envFile) || $envFile === '') {
        // CONTENTFLY_PROJECT_DIR is the directory containing index.php — one level above it
        // is outside the document root. Until 007-001-0002 the constant was called ROOT_DIR
        // and was computed by the framework from its own location; now the entry point
        // names it.
        $envFile = (defined('CONTENTFLY_PROJECT_DIR') ? CONTENTFLY_PROJECT_DIR : __DIR__ . '/..') . '/../.env';
    }

    if (is_file($envFile) && is_readable($envFile)) {
        \Dotenv\Dotenv::createImmutable(dirname($envFile), basename($envFile))->safeLoad();
    }

    unset($envFile);
}

$configFactory = Factory::getInstance();

/*
 ************************************************************************************
 * Default configuration
 ************************************************************************************
 */

$configDefault = new Config();

/*
 * Database — set by `appcms:install`. Before installation the placeholders are here;
 * afterwards the real values. Do not edit by hand while the installation is still
 * pending: a set DB_HOST makes the installer abort.
 */
$configDefault->DB_HOST                 = '$SET_DB_HOST';
$configDefault->DB_PORT                 = '$SET_DB_PORT';
$configDefault->DB_NAME                 = '$SET_DB_NAME';
$configDefault->DB_USER                 = '$SET_DB_USER';
$configDefault->DB_PASS                 = '$SET_DB_PASS';
$configDefault->DB_GUID_STRATEGY        = '$SET_DB_GUID_STRATEGY';

/*
 * APP_DEBUG controls verbose error output including full stack traces in
 * API and HTML responses (`bootstrap-web.php`). It must be off in production.
 *
 * Resolution: an explicit APP_DEBUG wins; otherwise on for a known development
 * environment, off for everything else. Deliberately spelled out here rather than moved
 * into a helper class — this file is loaded before the autoloader is guaranteed
 * to be available.
 */
$appEnv      = strtolower(trim((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'dev')));
$appDebugEnv = $_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG');

$configDefault->APP_DEBUG = ($appDebugEnv !== false && $appDebugEnv !== null && $appDebugEnv !== '')
    ? filter_var($appDebugEnv, FILTER_VALIDATE_BOOLEAN)
    : in_array($appEnv, ['dev', 'development', 'test', 'local'], true);

unset($appEnv, $appDebugEnv);

$configDefault->APP_ENABLE_SCHEMA_CACHE = false;

/*
 * Time zone. The framework default is 'Europe/Berlin'. Anyone comparing points in time across
 * time zones — reports, retention periods, cron jobs — is better off with UTC:
 * a stored point in time is then unambiguous, and the time zone is pure presentation.
 */
// $configDefault->APP_TIMEZONE         = 'UTC';

/*
 * WEB_ROOT — the path under which the application is served on the web. Default '/'.
 *
 * Only set this if the application lives in a subdirectory. The FileController
 * builds the redirect to a delivered file from it; if the value is wrong, the redirect
 * points nowhere.
 *
 * Until `000-000-0006` the value was derived from `$_SERVER['PHP_SELF']`. That was correct under
 * Apache with the bundled .htaccess and nowhere else — it is something the
 * operator knows and the server can only guess. That is why it lives here now.
 */
// $configDefault->WEB_ROOT             = '/subdirectory/';

/*
 * APP_ALLOW_ORIGIN — the origins a browser client may call the API from (000-000-0039).
 *
 * Without an entry no foreign origin is allowed. A web app on another domain, or an Ionic/Capacitor
 * app (origin `capacitor://localhost` on iOS, `http://localhost` on Android), has to be listed —
 * exact values, comma-separated. `*` allows any origin, but without credentials.
 *
 *     APP_ALLOW_ORIGIN=https://app.example.com,capacitor://localhost,http://localhost
 */
$configDefault->APP_ALLOW_ORIGIN = $_ENV['APP_ALLOW_ORIGIN'] ?? getenv('APP_ALLOW_ORIGIN') ?: null;

/*
 * APP_TRUSTED_PROXIES — which proxies the application sits behind. Default: none.
 *
 * Only set this if a reverse proxy or load balancer sits in front. Without it the
 * application takes the proxy's address for the caller's — the login throttle from `013-001-0003`
 * would then throttle the proxy and with it every user behind it, while the attacker keeps
 * guessing unthrottled.
 *
 * ONLY ENTER WHAT YOU TRUST. A trusted sender may state who the caller is —
 * entering too wide a network lets the attacker dictate the address.
 * 'REMOTE_ADDR' means "the immediate sender, whoever that is" and suits an
 * application that is reachable exclusively through a proxy whose address changes.
 *
 * APP_TRUSTED_HEADERS selects which forwarding headers are believed:
 * 'x-forwarded' (default) or 'forwarded' per RFC 7239. Not both at once.
 */
// $configDefault->APP_TRUSTED_PROXIES  = ['10.0.0.0/8'];
// $configDefault->APP_TRUSTED_HEADERS  = 'x-forwarded';

/*
 * SECURITY — key for encrypting fields with `@PIM\Config(encoded=true)`.
 *
 * **No default value, on purpose.** A key stored in the repository is no
 * key: every installation that forgets to set it then encrypts with a
 * publicly known value — and nobody notices, because everything works. Without a value
 * `StringType` and `TextareaType` refuse to encrypt, with a clear message.
 */
$configDefault->SECURITY_CIPHER_KEY     = $_ENV['SECURITY_CIPHER_KEY'] ?? getenv('SECURITY_CIPHER_KEY') ?: null;

/*
 * SECURITY — signing secret for JWT (`013-002-0003`).
 *
 * Likewise **no default value**, for the same reason: a secret stored in the repository
 * is no secret. Without a value the JWT branch rejects every token instead of
 * silently skipping it — a check that switches itself off is no check.
 *
 * **At least 32 bytes.** `firebase/php-jwt` rejects a shorter secret for HS256; a
 * short one would be guessable anyway. A usable value is produced with
 * `php -r "echo bin2hex(random_bytes(32));"`.
 *
 * JWTs are only issued from `013-003` on; until then the field stays empty in most
 * installations, and the opaque token path is unaffected.
 */
$configDefault->SECURITY_JWT_SECRET     = $_ENV['SECURITY_JWT_SECRET'] ?? getenv('SECURITY_JWT_SECRET') ?: null;

/*
 * SECURITY — key rotation for JWT (`013-003-0004`).
 *
 * A signing secret that cannot be rotated without ending every session does not get
 * rotated — and that makes a leak permanent. That is why every token carries the ID
 * of its key in the header, and two keys can be accepted at the same time.
 *
 * **How a rotation works:**
 *
 *   1. Move the current value to `SECURITY_JWT_SECRET_PREVIOUS`, its ID to
 *      `SECURITY_JWT_KEY_ID_PREVIOUS`.
 *   2. Put a new value in `SECURITY_JWT_SECRET`, a new ID in
 *      `SECURITY_JWT_KEY_ID`. From now on tokens are signed with the new one, both are
 *      accepted — nobody has to log in again.
 *   3. After `SECURITY_JWT_TTL` (default 15 minutes) the longest-lived access JWT still issued
 *      with the old key has expired. The two `*_PREVIOUS` fields can then be
 *      emptied again.
 *
 * The IDs are names, not secrets — they appear in plain text in every token. They
 * only have to differ from each other; the application rejects two identical ones instead of
 * silently accepting only one of the two keys.
 */
// $configDefault->SECURITY_JWT_KEY_ID          = $_ENV['SECURITY_JWT_KEY_ID'] ?? getenv('SECURITY_JWT_KEY_ID') ?: 'k1';
// $configDefault->SECURITY_JWT_SECRET_PREVIOUS = $_ENV['SECURITY_JWT_SECRET_PREVIOUS'] ?? getenv('SECURITY_JWT_SECRET_PREVIOUS') ?: null;
// $configDefault->SECURITY_JWT_KEY_ID_PREVIOUS = $_ENV['SECURITY_JWT_KEY_ID_PREVIOUS'] ?? getenv('SECURITY_JWT_KEY_ID_PREVIOUS') ?: null;

$configFactory->setConfig($configDefault);

/*
 ************************************************************************************
 * Additional hosts
 *
 * The factory allows a separate configuration per hostname that inherits from the default
 * configuration. That way, for example, the production domain gets a stricter
 * Content Security Policy without local development suffering for it.
 ************************************************************************************
 */

// $configLive = new Config('api.example.com', $configDefault);
// $configLive->APP_CS_POLICY = "default-src 'self'; script-src 'self'; img-src 'self' data:;";
