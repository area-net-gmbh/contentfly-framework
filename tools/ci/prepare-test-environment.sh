#!/bin/sh
#
# Baut die Umgebung auf, die die Integrationstests brauchen: Datenbank abwarten,
# Contentfly installieren, Versandfalle einrichten, Testserver starten.
#
# Läuft in der Pipeline (008-005-0001) und beim lokalen Nachspielen mit demselben
# Aufruf — deshalb steht der Ablauf hier und nicht in der .gitlab-ci.yml. Eine
# Pipeline-Definition, deren Schritte man nur in der Pipeline ausprobieren kann,
# ist beim Suchen eines Fehlers nutzlos.
#
# Erwartete Umgebungsvariablen (die .gitlab-ci.yml setzt sie):
#
#   CONTENTFLY_TEST_DB_HOST / _PORT / _NAME / _USER / _PASSWORD
#   CONTENTFLY_TEST_BASE_URL       Adresse, unter der der Testserver antwortet
#   CONTENTFLY_TEST_MAIL_TRAP      Verzeichnis der Versandfalle
#   CONTENTFLY_TEST_ADMIN_PASS     Passwort des Admin-Benutzers
#   CONTENTFLY_TEST_JWT_SECRET     Signaturgeheimnis fuer die JWT-Tests (mind. 32 Byte)#   CONTENTFLY_TEST_PROVIDER       Liste fuer den BeispielProvider (013-004-0004),
#                                  Format kennung:geheimnis:gruppe|gruppe, mehrere per Komma

set -eu

WARTEZEIT="${CONTENTFLY_CI_TIMEOUT:-90}"

fehlt() {
    echo "✗ Umgebungsvariable $1 ist nicht gesetzt." >&2
    echo "  Ohne sie kann die Testumgebung nicht aufgebaut werden; siehe tests/README.md." >&2
    exit 1
}

for name in CONTENTFLY_TEST_DB_HOST CONTENTFLY_TEST_DB_PORT CONTENTFLY_TEST_DB_NAME \
            CONTENTFLY_TEST_DB_USER CONTENTFLY_TEST_DB_PASSWORD \
            CONTENTFLY_TEST_BASE_URL CONTENTFLY_TEST_MAIL_TRAP CONTENTFLY_TEST_ADMIN_PASS \
            CONTENTFLY_TEST_JWT_SECRET CONTENTFLY_TEST_PROVIDER; do
    eval "wert=\${$name:-}"
    [ -n "$wert" ] || fehlt "$name"
done

# ── 1. Auf die Datenbank warten ────────────────────────────────────────────────────
#
# Ein Service ist gestartet, lange bevor er antwortet. Feste sleep-Werte sind hier
# die schlechteste Lösung: zu kurz und der Lauf ist sporadisch rot, zu lang und
# jede Pipeline zahlt die Wartezeit. Also fragen statt raten.

echo "→ Warte auf die Datenbank ($CONTENTFLY_TEST_DB_HOST:$CONTENTFLY_TEST_DB_PORT)"
i=0
until php -r '
    try {
        new PDO(
            sprintf("mysql:host=%s;port=%s", getenv("CONTENTFLY_TEST_DB_HOST"), getenv("CONTENTFLY_TEST_DB_PORT")),
            getenv("CONTENTFLY_TEST_DB_USER"),
            getenv("CONTENTFLY_TEST_DB_PASSWORD")
        );
        exit(0);
    } catch (Throwable $e) {
        exit(1);
    }
' 2>/dev/null; do
    i=$((i + 1))
    if [ "$i" -ge "$WARTEZEIT" ]; then
        echo "✗ Die Datenbank antwortet nach ${WARTEZEIT}s nicht." >&2
        exit 1
    fi
    sleep 1
done
echo "  ✓ nach ${i}s erreichbar"

# ── 2. Installieren ────────────────────────────────────────────────────────────────
#
# Der Command nimmt seit 012-002 alle Werte als Optionen; -n unterdrückt jede
# Rückfrage. Er schreibt die Zugangsdaten in custom/config.php — eine Datei, die als
# Vorlage im Repo liegt. In der Pipeline ist der Checkout flüchtig und das deshalb
# unkritisch; lokal ist es eine Falle (siehe 008-005-0003).

echo "→ Installation"
php bin/console.php appcms:install -n \
    --db-host="$CONTENTFLY_TEST_DB_HOST" \
    --db-port="$CONTENTFLY_TEST_DB_PORT" \
    --db-name="$CONTENTFLY_TEST_DB_NAME" \
    --db-user="$CONTENTFLY_TEST_DB_USER" \
    --db-pass="$CONTENTFLY_TEST_DB_PASSWORD" \
    --db-strategy=guid \
    --admin-password="$CONTENTFLY_TEST_ADMIN_PASS"

# ── 3. Versandfalle ────────────────────────────────────────────────────────────────
#
# Kein Testlauf darf eine Mail verschicken — und das gehört nachgewiesen, nicht
# angenommen. Der Testserver bekommt deshalb ein Fangskript als sendmail_path;
# VersandfalleTest prüft beide Hälften: dass das Skript fängt, und dass der Server genau
# dieses benutzt.
#
# /api/mail war der Anlass und ist mit 000-000-0016 entfernt — er verschickte seit dem
# Sprung auf PHP 8 ohnehin nichts. Die Falle bleibt: $app['mailer'] steht Projekten weiter
# zur Verfügung, und eine Sicherung, die man mit ihrem ersten Anlass abbaut, fehlt beim
# zweiten.

echo "→ Versandfalle unter $CONTENTFLY_TEST_MAIL_TRAP"
mkdir -p "$CONTENTFLY_TEST_MAIL_TRAP"
cat > "$CONTENTFLY_TEST_MAIL_TRAP/sendmail" <<'FANGSKRIPT'
#!/bin/sh
# Fängt alles ab, was mail() zustellen wollte. Stellt NICHTS zu.
cat >> "$(dirname "$0")/postausgang.log"
echo "--- ENDE MAIL ---" >> "$(dirname "$0")/postausgang.log"
exit 0
FANGSKRIPT
chmod +x "$CONTENTFLY_TEST_MAIL_TRAP/sendmail"
: > "$CONTENTFLY_TEST_MAIL_TRAP/postausgang.log"

# ── 4. Testserver ──────────────────────────────────────────────────────────────────
#
# tests/router.php ist nicht optional: Ohne ihn schickt der eingebaute Server jede
# Anfrage durch index.php, auch die für eine Datei, die auf der Platte liegt. Apache
# tut das nicht, und die Dateiauslieferung hängt genau daran.
#
# APP_DEBUG=0 ist die Produktionseinstellung: Die Suite soll messen, was eine Installation
# ausliefert. Dass ein PHP-Fehler dabei als JSON-Antwort der Anwendung ankommt und nicht als
# Symfonys „Whoops"-Seite, ist seit 000-000-0006 unabhängig davon zugesichert.
#
# display_errors=Off ist NICHT kosmetisch, sondern Voraussetzung dafür, dass die Suite
# überhaupt misst, was sie zu messen glaubt:
#
#   PHP schreibt eine Deprecation direkt in den Antwortstrom. Passiert das, bevor der
#   Kernel den Statuscode setzt, sind die Header schon unterwegs — und die Antwort trägt 200,
#   obwohl die Anwendung 405 oder 500 meint. Beim ersten CI-Lauf sind daran sechs Tests
#   gescheitert, die lokal grün waren (008-005-0001).
#
# Es ist zugleich die Produktionseinstellung: Eine Instanz, die Deprecations ausliefert,
# verrät Dateipfade an jeden Aufrufer. Dass das Framework sie bei APP_DEBUG=0 NICHT
# erzwingt, ist ein eigener Befund — siehe 000-000-0018.
#
# log_errors=On, damit die Deprecations nicht verschwinden, sondern im Serverlog stehen.
# Das ist die Quelle, aus der das „0 Deprecations"-Gate aus 006-005 später liest.

ADRESSE=$(echo "$CONTENTFLY_TEST_BASE_URL" | sed 's#^https\{0,1\}://##')

# SECURITY_JWT_SECRET kommt aus der Umgebung, wie in Produktion (013-003-0001). Die
# ausgelieferte custom/config.php liest den Wert von dort; ohne ihn stellt der Login keine JWT
# aus, und die Tests dafuer haetten nichts zu messen.
#
# Der Name unterscheidet sich absichtlich: CONTENTFLY_TEST_* ist die Umgebung des Testlaufs,
# SECURITY_JWT_SECRET die der Anwendung. Sie hier gleichzusetzen ist eine Entscheidung dieser
# Datei und keine, die in der Anwendung steht.
echo "→ Testserver auf $ADRESSE"
# CONTENTFLY_BEISPIEL_PROVIDER speist die Vorlage aus 013-004-0004. Ohne sie laesst sie
# NIEMANDEN herein — was ein eigener Test misst; hier bekommt sie einen Wert, damit der andere
# Test die Anmeldung ueber einen Provider end-to-end durchspielen kann.
APP_ENV=production APP_DEBUG=0 SECURITY_JWT_SECRET="$CONTENTFLY_TEST_JWT_SECRET" \
    CONTENTFLY_BEISPIEL_PROVIDER="$CONTENTFLY_TEST_PROVIDER" php \
    -d display_errors=Off \
    -d log_errors=On \
    -d sendmail_path="$CONTENTFLY_TEST_MAIL_TRAP/sendmail" \
    -S "$ADRESSE" tests/router.php > "${CONTENTFLY_CI_LOG:-/tmp/testserver.log}" 2>&1 &

echo $! > /tmp/testserver.pid

# ── 5. Auf den Testserver warten ───────────────────────────────────────────────────
#
# Der offene Port allein genügt nicht — er ist offen, bevor die Anwendung antwortet.
# Geprüft wird deshalb eine echte Antwort von /api/config, der einzigen Route ohne
# Token. Damit ist zugleich belegt, dass die Installation gegriffen hat.

echo "→ Warte auf den Testserver"
i=0
until php -r '
    $roh = @file_get_contents(getenv("CONTENTFLY_TEST_BASE_URL")."/api/config");
    exit(($roh !== false && json_decode($roh, true) !== null) ? 0 : 1);
' 2>/dev/null; do
    i=$((i + 1))
    if [ "$i" -ge "$WARTEZEIT" ]; then
        echo "✗ Der Testserver antwortet nach ${WARTEZEIT}s nicht." >&2
        echo "--- Serverlog ---" >&2
        cat "${CONTENTFLY_CI_LOG:-/tmp/testserver.log}" >&2 || true
        exit 1
    fi
    sleep 1
done
echo "  ✓ nach ${i}s erreichbar"

echo "✓ Testumgebung steht."
