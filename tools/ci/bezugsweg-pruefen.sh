#!/bin/sh
#
# Fährt den Weg, den ein neues Projekt geht (011-002-0004).
#
# Frisches Verzeichnis AUSSERHALB dieses Repos, Skeleton-Dateien hinein, `composer install`
# gegen das Paket-Repository, Installation, ein API-Aufruf. Was hier grün ist, kann ein
# Projekt auf einem beliebigen Rechner auch.
#
# ── Warum das ein Gate ist und keine Notiz im Runbook ─────────────────────────────────
#
# Ein Weg, den niemand fährt, verrottet. Der Bezug über das Paket-Repository hängt an
# Dingen, die sich ändern, ohne dass jemand an dieses Projekt denkt: an einem Tag, der
# fehlt, an einem Deploy Key, der abläuft, an einem `version`-Feld, das jemand gut gemeint
# zurücklegt. Jedes davon meldet sich hier — und sonst erst bei dem, der das nächste
# Projekt aufsetzt.
#
# ── Was es NICHT tut ──────────────────────────────────────────────────────────────────
#
# Es fährt ausdrücklich NICHT gegen das `path`-Repository dieses Baums. Liefe es im Baum,
# bewiese es nichts: `composer install` fände das Paket lokal, und dass es von aussen nicht
# beziehbar ist, fiele niemandem auf. Deshalb das Verzeichnis ausserhalb, deshalb die
# Gegenprobe an `composer.lock` weiter unten.
#
# Erwartete Umgebung:
#
#   CONTENTFLY_TEST_DB_HOST / _PORT / _USER / _PASSWORD   Datenbank für die Installation
#   GIT_SSH_COMMAND                                       Zugang zum Paket-Repository
#   PAKET_REPO_URL                                        Vorgabe: die echte URL

set -eu

. "$(dirname "$0")/schritt.sh"

WURZEL=$(cd "$(dirname "$0")/../.." && pwd)
PAKET_REPO_URL="${PAKET_REPO_URL:-git@github.com:area-net-gmbh/contentfly-framework-dist.git}"
DB_NAME="${BEZUGSWEG_DB_NAME:-bezugsweg_probe}"
ADRESSE="${BEZUGSWEG_ADRESSE:-127.0.0.1:8146}"

# DIE CONSTRAINT STEHT AN EINER STELLE, und sie trägt ihr Verfallsdatum im Kommentar:
#
# `@RC` ist nötig, solange es nur Vorab-Tags gibt. Sobald `011-004` die Release-Version
# entscheidet und `v2.0.0` gesetzt ist, gehört hier `^2.0` hin — und im Runbook dieselbe
# Zeile. Dass beide Stellen dieselbe Zeichenkette tragen, ist Absicht: Eine Constraint, die
# in der Doku anders steht als im Gate, prüft den falschen Weg.
CONSTRAINT="${BEZUGSWEG_CONSTRAINT:-^2.0@RC}"

PROJEKT=$(mktemp -d)
aufraeumen() {
    if [ -n "${SERVER_PID:-}" ]; then
        kill "$SERVER_PID" 2>/dev/null || true
        # Ohne das `wait` meldet die Shell den beendeten Hintergrundprozess als
        # „Terminated" ans Log — eine Zeile, die wie ein Fehler aussieht und keiner ist.
        wait "$SERVER_PID" 2>/dev/null || true
    fi
    rm -rf "$PROJEKT"
}
trap aufraeumen EXIT

echo "→ Projekt in $PROJEKT (ausserhalb von $WURZEL)"

# ── 1. Die Dateien, mit denen ein Projekt startet ──────────────────────────────────────
#
# Die Wurzel dieses Repos ist zugleich das Skeleton (007-001-0004) — abzüglich dessen, was
# nur der Entwicklung dient. Was hier kopiert wird, ist genau diese Liste.

for eintrag in .htaccess bin custom data index.php plugins favicon.ico robots.txt; do
    cp -R "$WURZEL/$eintrag" "$PROJEKT/"
done

cat > "$PROJEKT/composer.json" <<JSON
{
    "name": "probe/bezugsweg",
    "type": "project",
    "license": "proprietary",
    "repositories": [
        { "type": "vcs", "url": "$PAKET_REPO_URL" }
    ],
    "require": {
        "areanet/contentfly": "$CONSTRAINT",
        "vlucas/phpdotenv": "^5.6"
    },
    "config": {
        "preferred-install": { "areanet/contentfly": "source" }
    },
    "autoload": {
        "psr-4": { "Custom\\\\": "custom/", "Plugins\\\\": "plugins/" }
    }
}
JSON

# ── preferred-install: source — und warum das keine Bequemlichkeit ist ─────────────────
#
# Composer bezieht ein Paket am liebsten als `dist`, also als Zip. Bei einem Repository auf
# GitHub holt es dieses Zip über die REST-API — und die kennt einen SSH-Deploy-Key NICHT.
# Bei einem PRIVATEN Repository antwortet sie mit `404 Not Found`, was aussieht, als gäbe es
# das Paket nicht.
#
# `source` heisst: klonen statt herunterladen. Das geht über SSH und damit mit genau dem
# Zugang, den der Deploy Key gewährt.
#
# DIE ALTERNATIVE WÄRE EIN API-TOKEN je Entwickler und je CI (`composer config
# github-oauth.github.com …`). Das war schon bei der Wahl des Deploy Keys die verworfene
# Variante: Ein Token hängt an einem Konto, ein Deploy Key an einem Repository.
#
# Die Einschränkung gilt nur für dieses eine Paket — die 54 anderen kommen von Packagist und
# weiterhin als `dist`.

# ── 2. Beziehen ────────────────────────────────────────────────────────────────────────

cd "$PROJEKT"
schritt "composer install gegen $PAKET_REPO_URL" \
    composer install --no-interaction --no-progress

# ── 3. Gegenprobe: kam es wirklich aus dem Paket-Repository? ───────────────────────────
#
# Die zwei Fehlschläge, die hier still durchgingen, wenn niemand nachsieht:
#
#   - Die Quelle ist ein PFAD. Dann lief der Lauf doch im Baum und beweist nichts.
#   - Die Version ist `dev-…`. Dann hat Composer die Tags nicht gesehen — genau der Fehler
#     aus 011-002-0003, bei dem ein `version`-Feld im Paket-Manifest jeden Tag verwarf.
#     Der Lauf wäre grün, und das Paket wäre trotzdem unbrauchbar.

echo "→ Herkunft prüfen"
php -r '
    $lock = json_decode(file_get_contents("composer.lock"), true);
    $paket = null;
    foreach ($lock["packages"] as $p) {
        if ($p["name"] === "areanet/contentfly") { $paket = $p; }
    }
    if ($paket === null) {
        fwrite(STDERR, "✗ areanet/contentfly steht nicht im Lock.\n");
        exit(1);
    }
    $version = $paket["version"];
    $quelle  = $paket["source"]["url"] ?? "";
    printf("    Version  %s\n    Quelle   %s\n", $version, $quelle);

    if (str_starts_with($version, "dev-")) {
        fwrite(STDERR, "✗ Bezogen wurde \"".$version."\" — also KEIN Tag.\n");
        fwrite(STDERR, "  Composer hat die Tags nicht gesehen. Der häufigste Grund: Die composer.json\n");
        fwrite(STDERR, "  des Pakets deklariert wieder ein \"version\"-Feld; dann verwirft Composer jeden\n");
        fwrite(STDERR, "  Tag, der anders heisst — wortlos. Siehe 011-002-0003.\n");
        exit(1);
    }
    if ($quelle === "" || $quelle[0] === "/" || str_starts_with($quelle, "..")) {
        fwrite(STDERR, "✗ Die Quelle ist ein Pfad: ".$quelle."\n");
        fwrite(STDERR, "  Dann lief die Prüfung im Baum und beweist nichts über den Bezug von aussen.\n");
        exit(1);
    }
'
echo "  ✓ getaggte Version aus einem entfernten Repository"

# ── 4. Installieren und antworten lassen ───────────────────────────────────────────────

schritt "Contentfly installieren" \
    php bin/console.php appcms:install -n \
        --db-host="$CONTENTFLY_TEST_DB_HOST" \
        --db-port="$CONTENTFLY_TEST_DB_PORT" \
        --db-name="$DB_NAME" \
        --db-user="$CONTENTFLY_TEST_DB_USER" \
        --db-pass="$CONTENTFLY_TEST_DB_PASSWORD" \
        --db-strategy=guid \
        --admin-password="bezugsweg-probe"

APP_ENV=production APP_DEBUG=0 php -d display_errors=Off -S "$ADRESSE" index.php > "$PROJEKT/server.log" 2>&1 &
SERVER_PID=$!

echo "→ Warte auf den Server"
i=0
until php -r '$r = @file_get_contents("http://'"$ADRESSE"'/api/config"); exit($r !== false && json_decode($r, true) !== null ? 0 : 1);' 2>/dev/null; do
    i=$((i + 1))
    if [ "$i" -ge 30 ]; then
        echo "✗ Der Server des Probe-Projekts antwortet nicht." >&2
        cat "$PROJEKT/server.log" >&2 || true
        exit 1
    fi
    sleep 1
done
echo "  ✓ nach ${i}s"

# ── 5. Die Antwort muss die Form aus Epic 011 haben ────────────────────────────────────
#
# Nicht nur "es antwortet": Wer das Paket bezieht, bekommt die API, die dieses Release
# zusichert — `data`, `errors`, `meta`. Ein Paket, das antwortet, aber in alter Form, wäre
# ein falscher Stand im Paket-Repository und hier nicht zu sehen, wenn nur der Statuscode
# zählte.

echo "→ Antwortform prüfen"
php -r '
    $roh = file_get_contents("http://'"$ADRESSE"'/api/config");
    $body = json_decode($roh, true);
    $ist  = array_keys($body);
    if ($ist !== array("data", "errors", "meta")) {
        fwrite(STDERR, "✗ Die Antwort hat die Form ".implode(", ", $ist)."; erwartet data, errors, meta.\n");
        fwrite(STDERR, "  Das bezogene Paket ist nicht der Stand, den dieses Release zusichert.\n");
        exit(1);
    }
    printf("  ✓ data / errors / meta — Version %s\n", $body["meta"]["version"]);
'

echo "✓ Der Bezugsweg trägt: Ein Projekt kann Contentfly ohne Kenntnis dieses Arbeitsverzeichnisses aufsetzen."
