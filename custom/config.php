<?php
/**
 * Projekt-Konfiguration — die Werte, die ein Projekt gegenüber den Framework-Standards
 * ändert. Alles, was hier nicht gesetzt wird, kommt aus `Areanet\PIM\Classes\Config`.
 *
 * Diese Datei ist eine **Vorlage**. Die `$SET_*`-Platzhalter unten sind kein Versehen:
 * `php bin/console.php appcms:install` ersetzt sie durch die eingegebenen Zugangsdaten.
 * Solange `DB_HOST` auf `$SET_DB_HOST` steht, gilt das System als **nicht installiert**
 * (`$app['is_installed']` in `lib/contentfly/bootstrap.php`) — die Installation prüft
 * genau darauf.
 */

use Areanet\PIM\Classes\Config;
use Areanet\PIM\Classes\Config\Factory;

/*
 * ─── Optional: Werte aus einer .env-Datei laden ────────────────────────────────────
 *
 * Nur nötig auf Hostern, die keine echten Prozess-Umgebungsvariablen setzen können
 * (Managed PHP). Wo `docker compose`, systemd oder ein Hosting-Panel die Variablen
 * liefert, ist dieser Block ein No-op — der Rest der Datei liest ohnehin nur `$_ENV`
 * bzw. `getenv()`.
 *
 * SECURITY: Die Datei darf **niemals** im Document-Root liegen. Die `.htaccess` leitet
 * nur Anfragen um, für die keine Datei existiert (`RewriteCond %{REQUEST_FILENAME} !-f`),
 * und Apache blockt von sich aus nur `.ht*` — eine `.env` neben der `index.php` würde im
 * Klartext ausgeliefert. Rechte auf `600` setzen.
 *
 * `createImmutable()` lässt eine bereits gesetzte Umgebungsvariable stehen: Ein echter
 * Wert aus der Prozessumgebung schlägt eine veraltete Datei. `safeLoad()` wirft nicht,
 * wenn die Datei fehlt oder kaputt ist — eine defekte Secrets-Datei darf die Anwendung
 * nicht beim Start umbringen.
 */
if (class_exists(\Dotenv\Dotenv::class)) {
    $envFile = $_ENV['CONTENTFLY_ENV_FILE'] ?? getenv('CONTENTFLY_ENV_FILE') ?: null;
    if (!is_string($envFile) || $envFile === '') {
        // ROOT_DIR ist das Verzeichnis mit der index.php — eine Ebene darüber ist
        // außerhalb des Document-Roots.
        $envFile = (defined('ROOT_DIR') ? ROOT_DIR : __DIR__ . '/..') . '/../.env';
    }

    if (is_file($envFile) && is_readable($envFile)) {
        \Dotenv\Dotenv::createImmutable(dirname($envFile), basename($envFile))->safeLoad();
    }

    unset($envFile);
}

$configFactory = Factory::getInstance();

/*
 ************************************************************************************
 * Standard-Konfiguration
 ************************************************************************************
 */

$configDefault = new Config();

/*
 * Datenbank — von `appcms:install` gesetzt. Vor der Installation stehen hier die
 * Platzhalter; danach die echten Werte. Nicht von Hand ändern, solange die
 * Installation noch aussteht: Ein gesetzter DB_HOST lässt den Installer abbrechen.
 */
$configDefault->DB_HOST                 = '$SET_DB_HOST';
$configDefault->DB_PORT                 = '$SET_DB_PORT';
$configDefault->DB_NAME                 = '$SET_DB_NAME';
$configDefault->DB_USER                 = '$SET_DB_USER';
$configDefault->DB_PASS                 = '$SET_DB_PASS';
$configDefault->DB_GUID_STRATEGY        = '$SET_DB_GUID_STRATEGY';

/*
 * APP_DEBUG steuert ausführliche Fehlerausgabe inklusive vollständiger Stacktraces in
 * API- und HTML-Antworten (`bootstrap-web.php`). In Produktion muss das aus sein.
 *
 * Auflösung: Ein ausdrückliches APP_DEBUG gewinnt; sonst an für eine bekannte
 * Entwicklungsumgebung, aus für alles andere. Bewusst hier ausgeschrieben und nicht in
 * eine Hilfsklasse ausgelagert — diese Datei wird geladen, bevor der Autoloader
 * garantiert bereitsteht.
 */
$appEnv      = strtolower(trim((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'dev')));
$appDebugEnv = $_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG');

$configDefault->APP_DEBUG = ($appDebugEnv !== false && $appDebugEnv !== null && $appDebugEnv !== '')
    ? filter_var($appDebugEnv, FILTER_VALIDATE_BOOLEAN)
    : in_array($appEnv, ['dev', 'development', 'test', 'local'], true);

unset($appEnv, $appDebugEnv);

$configDefault->APP_ENABLE_SCHEMA_CACHE = false;

/*
 * Zeitzone. Der Framework-Standard ist 'Europe/Berlin'. Wer Zeitpunkte über Zeitzonen
 * hinweg vergleicht — Auswertungen, Aufbewahrungsfristen, Cron-Jobs — fährt mit UTC
 * besser: Ein gespeicherter Zeitpunkt ist dann eindeutig, die Zeitzone reine Darstellung.
 */
// $configDefault->APP_TIMEZONE         = 'UTC';

/*
 * SECURITY — Schlüssel für die Verschlüsselung von Feldern mit `@PIM\Config(encoded=true)`.
 *
 * **Kein Standardwert, mit Absicht.** Ein im Repository hinterlegter Schlüssel ist kein
 * Schlüssel: Jede Installation, die vergisst ihn zu setzen, verschlüsselt dann mit einem
 * öffentlich bekannten Wert — und niemand merkt es, weil alles funktioniert. Ohne Wert
 * verweigern `StringType` und `TextareaType` die Verschlüsselung mit klarer Meldung.
 */
$configDefault->SECURITY_CIPHER_KEY     = $_ENV['SECURITY_CIPHER_KEY'] ?? getenv('SECURITY_CIPHER_KEY') ?: null;

$configFactory->setConfig($configDefault);

/*
 ************************************************************************************
 * Weitere Hosts
 *
 * Die Factory erlaubt eine eigene Konfiguration je Hostname, die von der Standard-
 * Konfiguration erbt. So bekommt etwa die Produktivdomain eine strengere
 * Content-Security-Policy, ohne dass die lokale Entwicklung darunter leidet.
 ************************************************************************************
 */

// $configLive = new Config('api.example.com', $configDefault);
// $configLive->APP_CS_POLICY = "default-src 'self'; script-src 'self'; img-src 'self' data:;";
