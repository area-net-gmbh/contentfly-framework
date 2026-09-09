#!/bin/sh
#
# Rüstet ein php:<version>-cli-Image mit dem aus, was das Framework braucht.
#
# Zwei Erweiterungen fehlen im Basis-Image:
#
#   pdo_mysql  ohne sie kommt keine Datenbankverbindung zustande.
#   gd         heute von keinem Test gebraucht, aber
#              lib/contentfly/Classes/File/Processing/Image.php benutzt es für
#              Thumbnails. Ohne die Erweiterung scheitert die erste Erweiterung der
#              Suite am Image statt am Code — und der Grund wäre schwer zu finden.
#
# Dazu ein Werkzeug, das nichts mit PHP zu tun hat:
#
#   unzip      Composer entpackt damit die dist-Archive. Ohne einen Entpacker
#              scheitert `composer install` sofort — siehe unten.
#
# mbstring, ctype, json, openssl, filter, hash, phar und tokenizer bringt das Image mit.
# Was es NICHT mitbringt und was hier deshalb dazukommt: siehe die beiden Blöcke unten.
#
# Läuft in der Pipeline (008-005-0001) und beim lokalen Nachspielen mit demselben Aufruf.
#
# ── Warum unzip, und warum erst jetzt (000-000-0021) ──────────────────────────────────
#
# Bis 006-003 lag der vendor/-Baum committet im Repo; der `composer install`-Schritt kam
# erst mit 006-002-0004 in die Pipeline und wurde nie von Null gefahren. Als 006-003-0002
# das zum ersten Mal tat, scheiterte er sofort:
#
#   Failed to download psr/cache from dist:
#     The zip extension and unzip/7z commands are both missing, skipping.
#   Source fallback is disabled. Not trying alternative sources.
#
# php:8.3-cli hat weder die zip-Extension noch unzip, 7z oder git — alle vier einzeln
# nachgemessen. Damit sind BEIDE Wege zu: --prefer-dist findet keinen Entpacker, und der
# Quell-Fallback bräuchte git.
#
# GEWÄHLT: unzip als Systempaket, nicht die zip-Extension. Beide Varianten wurden im Image
# gemessen, je zwei Läufe:
#
#   unzip (apt)                    ~5 s Einrichtung,  495 KB,  composer install 5–6 s
#   zip-Extension (libzip + build) ~12 s Einrichtung,          composer install 6–12 s
#
# Die Extension kostet doppelt so viel Einrichtung, ohne beim Installieren schneller zu
# sein. Sie wäre nur nötig, wenn die ANWENDUNG ZipArchive benutzte — tut sie nicht: kein
# ZipArchive im Code, kein `ext-zip` in composer.lock (geprüft über alle 77 Pakete).
#
# GIT KOMMT BEWUSST NICHT MIT. Es kostet 49,5 MB und zieht neun weitere Pakete nach — das
# Hundertfache von unzip — und wird für nichts gebraucht: Alle 77 Pakete des Locks kommen
# als dist, der Lauf erzeugt keine einzige git-Meldung, und die Manifeste haben kein
# `repositories` mit VCS-Quelle. Das Auschecken des Repos ist Sache des Runners, nicht
# dieses Images (die Pipeline setzt einen Docker-Executor voraus — siehe Kopf von
# .gitlab-ci.yml).
#
# NACHRÜSTEN, WENN: der Lock ein Paket enthält, das nur als `source` verfügbar ist, oder
# ein Manifest eine VCS-`repositories`-Quelle bekommt. Beides meldet sich als
# "Source fallback" oder "git was not found" — dann ist die Zeile hier die Antwort, nicht
# ein neuer Befund.

set -eu

echo "→ Systempakete für gd und den Composer-Entpacker"
apt-get update -qq
apt-get install -y -qq --no-install-recommends \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    unzip \
    > /dev/null

echo "→ gd konfigurieren"
docker-php-ext-configure gd --with-freetype --with-jpeg > /dev/null

echo "→ pdo_mysql und gd bauen"
docker-php-ext-install -j"$(nproc)" pdo_mysql gd > /dev/null

echo "→ Aufräumen"
rm -rf /var/lib/apt/lists/*

echo "✓ PHP $(php -r 'echo PHP_VERSION;') mit:"
php -m | grep -E '^(pdo_mysql|gd|mbstring|json|openssl|tokenizer)$' | sed 's/^/    /'
echo "    unzip $(unzip -v | head -1 | cut -d' ' -f2) (Composer-Entpacker, keine PHP-Erweiterung)"
