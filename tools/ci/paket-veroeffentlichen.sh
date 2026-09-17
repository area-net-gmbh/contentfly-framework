#!/bin/sh
#
# Veröffentlicht `lib/contentfly` als eigenständiges Repository (011-002-0003).
#
# WARUM ES DIESEN SCHRITT ÜBERHAUPT GIBT. Composer liest die `composer.json` aus der WURZEL
# eines Repositories. Unsere liegt in `lib/contentfly`, die Wurzel trägt das Skeleton
# (`areanet/contentfly-skeleton`). Ohne diesen Schritt kann kein Projekt `areanet/contentfly`
# beziehen — beim Probelauf am Bestandsprojekt UFP (007-005-0003) brauchte dessen
# composer.json deshalb ein `path`-Repository auf einen absoluten Pfad, den es nur im
# Container gab.
#
# DAS ZIEL-REPOSITORY IST EIN ERZEUGNIS, KEINE QUELLE. Niemand schreibt dort von Hand hinein;
# sein Inhalt entsteht bei jedem Tag neu aus `lib/contentfly`. Deshalb auch die README, die
# dieser Lauf mitliefert — sie sagt das dem, der dort landet.
#
# Läuft in der Pipeline und beim lokalen Nachspielen mit demselben Aufruf. Erwartete Umgebung:
#
#   PAKET_REPO_URL     SSH-URL des Ziel-Repositories
#   TAG                der Tag, der veröffentlicht wird (mit führendem `v`)
#   GIT_SSH_COMMAND    zeigt auf den Deploy Key (setzt der Workflow)
#
# TROCKENLAUF: `PAKET_TROCKEN=1` macht alles bis zum Push und lässt den Push weg. Der Split
# ist dann trotzdem gebaut und lässt sich ansehen — das ist der Weg, ihn zu prüfen, ohne ein
# fremdes Repository zu beschreiben.

set -eu

. "$(dirname "$0")/schritt.sh"

PRAEFIX=lib/contentfly

for name in PAKET_REPO_URL TAG; do
    eval "wert=\${$name:-}"
    if [ -z "$wert" ]; then
        echo "✗ Umgebungsvariable $name ist nicht gesetzt." >&2
        exit 1
    fi
done

# ── 1. Tag, composer.json und version.php müssen dieselbe Version nennen ───────────────
#
# DREI STELLEN, EINE ZAHL. `PackageManifestTest` hält schon zusammen, dass `composer.json`
# und `version.php` übereinstimmen — „eine Zusicherung, die niemand prüft, ist keine
# Zusicherung". Der Tag ist die dritte Stelle, und er ist die einzige, die ein Mensch im
# Moment des Veröffentlichens tippt.
#
# Eine Abweichung bricht hier ab, statt eine falsch benannte Version zu veröffentlichen.
# Das ist die teurere Richtung des Irrtums: Ein abgebrochener Lauf kostet einen zweiten
# Anlauf, ein falscher Tag im Paket-Repository ist in jedem `composer.lock` da draussen.

# EIN VORAB-ZUSATZ IST ERLAUBT, ein abweichender Kern nicht. `v2.0.0` und `v2.0.0-rc1` gehören
# beide zum Manifest `2.0.0`; `v2.1.0` nicht.
#
# Der Grund ist nicht Bequemlichkeit, sondern dass sich dieser Weg sonst nur beweisen liesse,
# indem man eine echte Release-Version veröffentlicht. Composer kennt denselben Unterschied:
# `^2.0` nimmt `2.0.0`, aber **nicht** `2.0.0-rc1` — ein Vorab-Tag lässt sich also setzen und
# prüfen, ohne dass ihn ein Projekt versehentlich zieht.

VERSION_TAG=$(echo "$TAG" | sed 's/^v//')
VERSION_KERN=$(echo "$VERSION_TAG" | sed 's/-.*//')
VERSION_MANIFEST=$(sed -n 's/.*"version": "\([^"]*\)".*/\1/p' "$PRAEFIX/composer.json" | head -1)
VERSION_PHP=$(sed -n "s/.*APP_VERSION = '\([^']*\)'.*/\1/p" "$PRAEFIX/version.php" | head -1)

echo "→ Version prüfen"
echo "    Tag             $TAG  (→ $VERSION_TAG, Kern $VERSION_KERN)"
echo "    composer.json   $VERSION_MANIFEST"
echo "    version.php     $VERSION_PHP"

if [ "$VERSION_KERN" != "$VERSION_MANIFEST" ] || [ "$VERSION_KERN" != "$VERSION_PHP" ]; then
    echo "✗ Die drei Stellen nennen nicht dieselbe Version." >&2
    echo "  Verglichen wird der Kern des Tags ($VERSION_KERN) — ein Vorab-Zusatz wie -rc1 ist" >&2
    echo "  erlaubt, ein abweichender Kern nicht." >&2
    echo "  Der Tag ist die einzige der drei Stellen, die von Hand getippt wird: vermutlich fehlt" >&2
    echo "  der Bump in $PRAEFIX/composer.json und $PRAEFIX/version.php, oder der Tag heisst anders." >&2
    echo "  Ein falscher Tag im Paket-Repository steht danach in jedem composer.lock." >&2
    exit 1
fi

if [ "$VERSION_TAG" != "$VERSION_KERN" ]; then
    echo "  ✓ $VERSION_TAG — ein Vorab-Tag von $VERSION_KERN; ^$VERSION_MANIFEST zieht ihn NICHT"
else
    echo "  ✓ $VERSION_TAG"
fi

# ── 2. Den Teilbaum herauslösen ────────────────────────────────────────────────────────
#
# `git subtree split` erzeugt aus der Historie des Präfixes eine eigene Historie, in der
# `lib/contentfly` die Wurzel ist. Deterministisch: Derselbe Eingangsstand ergibt dieselben
# Commit-Kennungen, ein zweiter Lauf schiebt also fort statt zu kollidieren.
#
# Es braucht die VOLLE Historie — mit einem flachen Klon (`fetch-depth: 1`) hat der Split
# nichts zu spalten. Der Workflow setzt `fetch-depth: 0`.

schritt "Teilbaum $PRAEFIX herauslösen" \
    git subtree split --prefix="$PRAEFIX" -b paket-split

SPLIT=$(git rev-parse paket-split)
echo "  ✓ $SPLIT"

# ── 3. Gegenprobe: liegt die composer.json wirklich in der Wurzel? ─────────────────────
#
# Der eine Fehler, der alles Weitere wertlos macht und den man am Ergebnis nicht sieht:
# Ein falsches Präfix erzeugt einen Split, der aussieht wie ein Repository und den Composer
# nicht lesen kann. Also nachsehen, statt darauf zu vertrauen.

echo "→ Wurzel des Splits prüfen"
if ! git cat-file -e "$SPLIT:composer.json" 2>/dev/null; then
    echo "✗ Im Split liegt keine composer.json in der Wurzel — Composer könnte das Paket nicht lesen." >&2
    echo "  Gefunden wurde:" >&2
    git ls-tree --name-only "$SPLIT" | sed 's/^/    /' >&2
    exit 1
fi

NAME=$(git cat-file -p "$SPLIT:composer.json" | sed -n 's/.*"name": "\([^"]*\)".*/\1/p' | head -1)
if [ "$NAME" != "areanet/contentfly" ]; then
    echo "✗ Die composer.json der Wurzel nennt \"$NAME\", erwartet \"areanet/contentfly\"." >&2
    exit 1
fi
echo "  ✓ areanet/contentfly, composer.json in der Wurzel"

# Was NICHT mitkommen darf. Der Split nimmt genau das Präfix, diese Prüfung ist also eine
# Gegenprobe gegen ein verrutschtes Präfix — nicht gegen den Split selbst.
for verboten in tests an_project tools phpunit.xml.dist phpstan.neon.dist; do
    if git cat-file -e "$SPLIT:$verboten" 2>/dev/null; then
        echo "✗ \"$verboten\" liegt im Split — das gehört zur Entwicklung, nicht ins Paket." >&2
        exit 1
    fi
done
echo "  ✓ ohne tests/, an_project/, tools/ und die Gate-Konfigurationen"

# ── 4. Veröffentlichen ─────────────────────────────────────────────────────────────────
#
# Zwei Refs: der Zweig, damit das Repository einen Stand hat, und der Tag, den ein Projekt
# als `^2.0` auflöst. Der Tag ist ein einfacher Tag auf den Split-Commit — ein annotierter
# brächte nichts, was Composer läse.

if [ "${PAKET_TROCKEN:-0}" = "1" ]; then
    echo "→ Trockenlauf: nicht gepusht."
    echo "  Es würde gepusht: $SPLIT → $PAKET_REPO_URL (master und $TAG)"
    exit 0
fi

schritt "Stand nach $PAKET_REPO_URL schieben" \
    git push "$PAKET_REPO_URL" "$SPLIT:refs/heads/master"

schritt "Tag $TAG setzen" \
    git push "$PAKET_REPO_URL" "$SPLIT:refs/tags/$TAG"

echo "✓ $NAME $VERSION_TAG veröffentlicht."
