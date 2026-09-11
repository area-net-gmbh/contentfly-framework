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
 *
 * DAS `class_exists` BLEIBT — geprüft und bewusst behalten (`006-004-0003`).
 *
 * `tools/dependency-assignment.json` hält fest, der Schutz sei nur nötig gewesen, solange
 * `vlucas/phpdotenv` in `custom/` lag und fehlen konnte, und erübrige sich im Root. Für
 * *dieses* Repo stimmt das: Seit `006-002` steht das Paket im Root-Manifest, der Zweig ist
 * hier immer wahr.
 *
 * Diese Datei ist aber die **Vorlage**, die ein Bestandsprojekt bekommt — und dessen
 * Root-Manifest kennt `vlucas/phpdotenv` erst, wenn es migriert ist. Ohne den Schutz stürbe
 * genau dort die Anwendung beim Start, mit einem `Class not found` statt mit einer Aussage
 * über die fehlende Abhängigkeit. Der Weg dorthin ist Epic `007` und noch nicht entschieden.
 *
 * Behalten ist die umkehrbare Wahl, Entfernen nicht. Revidieren, wenn `007` festgelegt hat,
 * wie ein Projekt an das Root-Manifest kommt. (Stand 007-001: Das Framework wird ein
 * Bibliothekspaket, das Projekt behaelt sein eigenes Manifest — siehe architecture.md.)
 */
if (class_exists(\Dotenv\Dotenv::class)) {
    $envFile = $_ENV['CONTENTFLY_ENV_FILE'] ?? getenv('CONTENTFLY_ENV_FILE') ?: null;
    if (!is_string($envFile) || $envFile === '') {
        // CONTENTFLY_PROJEKT ist das Verzeichnis mit der index.php — eine Ebene darüber
        // ist außerhalb des Document-Roots. Bis 007-001-0002 hiess die Konstante ROOT_DIR
        // und wurde vom Framework aus dessen eigener Lage gerechnet; jetzt benennt sie der
        // Einstiegspunkt.
        $envFile = (defined('CONTENTFLY_PROJEKT') ? CONTENTFLY_PROJEKT : __DIR__ . '/..') . '/../.env';
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
 * WEB_ROOT — der Pfad, unter dem die Anwendung im Web haengt. Vorgabe '/'.
 *
 * Nur zu setzen, wenn die Anwendung in einem Unterverzeichnis liegt. Der FileController
 * baut daraus die Weiterleitung auf eine ausgelieferte Datei; steht der Wert falsch, zeigt
 * sie ins Leere.
 *
 * Bis `000-000-0006` wurde der Wert aus `$_SERVER['PHP_SELF']` abgeleitet. Das stimmte unter
 * Apache mit der mitgelieferten .htaccess und sonst nirgends — es ist eine Angabe, die der
 * Betreiber kennt und der Server nur raten kann. Deshalb steht sie jetzt hier.
 */
// $configDefault->WEB_ROOT             = '/unterverzeichnis/';

/*
 * APP_TRUSTED_PROXIES — hinter welchen Proxies die Anwendung steht. Vorgabe: keine.
 *
 * Nur zu setzen, wenn ein Reverse Proxy oder Loadbalancer davorsteht. Ohne diese Angabe hält
 * die Anwendung dessen Adresse für die des Aufrufers — die Anmeldebremse aus `013-001-0003`
 * bremste dann ihn und damit alle Benutzer dahinter, während der Angreifer ungebremst
 * weiterrät.
 *
 * NUR EINTRAGEN, WEM MAN TRAUT. Ein vertrauter Absender darf sagen, wer der Aufrufer ist —
 * wer hier ein zu weites Netz einträgt, lässt sich die Adresse vom Angreifer diktieren.
 * 'REMOTE_ADDR' bedeutet „der unmittelbare Absender, wer immer das ist" und passt für eine
 * Anwendung, die ausschliesslich über einen Proxy erreichbar ist, dessen Adresse wechselt.
 *
 * APP_TRUSTED_HEADERS wählt, welchen Weiterleitungs-Headern dabei geglaubt wird:
 * 'x-forwarded' (Vorgabe) oder 'forwarded' nach RFC 7239. Nicht beide gleichzeitig.
 */
// $configDefault->APP_TRUSTED_PROXIES  = ['10.0.0.0/8'];
// $configDefault->APP_TRUSTED_HEADERS  = 'x-forwarded';

/*
 * SECURITY — Schlüssel für die Verschlüsselung von Feldern mit `@PIM\Config(encoded=true)`.
 *
 * **Kein Standardwert, mit Absicht.** Ein im Repository hinterlegter Schlüssel ist kein
 * Schlüssel: Jede Installation, die vergisst ihn zu setzen, verschlüsselt dann mit einem
 * öffentlich bekannten Wert — und niemand merkt es, weil alles funktioniert. Ohne Wert
 * verweigern `StringType` und `TextareaType` die Verschlüsselung mit klarer Meldung.
 */
$configDefault->SECURITY_CIPHER_KEY     = $_ENV['SECURITY_CIPHER_KEY'] ?? getenv('SECURITY_CIPHER_KEY') ?: null;

/*
 * SECURITY — Signaturgeheimnis für JWT (`013-002-0003`).
 *
 * Ebenfalls **kein Standardwert**, aus demselben Grund: Ein im Repository hinterlegtes
 * Geheimnis ist keines. Ohne Wert weist der JWT-Zweig jeden Token ab, statt ihn
 * stillschweigend zu überspringen — eine Prüfung, die sich selbst abschaltet, ist keine.
 *
 * **Mindestens 32 Byte.** `firebase/php-jwt` weist ein kürzeres Geheimnis für HS256 ab; ein
 * kurzes wäre ohnehin ratbar. Ein brauchbarer Wert entsteht mit
 * `php -r "echo bin2hex(random_bytes(32));"`.
 *
 * Ausgestellt werden JWT erst mit `013-003`; bis dahin bleibt das Feld in den meisten
 * Installationen leer, und der opaque Token-Weg ist davon unberührt.
 */
$configDefault->SECURITY_JWT_SECRET     = $_ENV['SECURITY_JWT_SECRET'] ?? getenv('SECURITY_JWT_SECRET') ?: null;

/*
 * SECURITY — Schlüsselwechsel für JWT (`013-003-0004`).
 *
 * Ein Signaturgeheimnis, das sich nicht wechseln lässt, ohne alle Sitzungen zu beenden, wird
 * nicht gewechselt — und damit ist ein Leak dauerhaft. Deshalb trägt jedes Token die Kennung
 * seines Schlüssels im Header, und es lassen sich zwei Schlüssel gleichzeitig akzeptieren.
 *
 * **So läuft ein Wechsel ab:**
 *
 *   1. Den bisherigen Wert nach `SECURITY_JWT_SECRET_PREVIOUS`, seine Kennung nach
 *      `SECURITY_JWT_KEY_ID_PREVIOUS`.
 *   2. Einen neuen Wert nach `SECURITY_JWT_SECRET`, eine neue Kennung nach
 *      `SECURITY_JWT_KEY_ID`. Ab jetzt wird mit dem neuen signiert, angenommen werden beide —
 *      niemand muss sich neu anmelden.
 *   3. Nach `SECURITY_JWT_TTL` (Vorgabe 15 Minuten) ist das längste noch mit dem alten
 *      Schlüssel ausgestellte Access-JWT abgelaufen. Dann können die beiden
 *      `*_PREVIOUS`-Felder wieder leer.
 *
 * Die Kennungen sind Namen, keine Geheimnisse — sie stehen im Klartext in jedem Token. Sie
 * müssen sich nur voneinander unterscheiden; die Anwendung weist zwei gleiche ab, statt
 * stillschweigend nur einen der beiden Schlüssel zu akzeptieren.
 */
// $configDefault->SECURITY_JWT_KEY_ID          = $_ENV['SECURITY_JWT_KEY_ID'] ?? getenv('SECURITY_JWT_KEY_ID') ?: 'k1';
// $configDefault->SECURITY_JWT_SECRET_PREVIOUS = $_ENV['SECURITY_JWT_SECRET_PREVIOUS'] ?? getenv('SECURITY_JWT_SECRET_PREVIOUS') ?: null;
// $configDefault->SECURITY_JWT_KEY_ID_PREVIOUS = $_ENV['SECURITY_JWT_KEY_ID_PREVIOUS'] ?? getenv('SECURITY_JWT_KEY_ID_PREVIOUS') ?: null;

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
