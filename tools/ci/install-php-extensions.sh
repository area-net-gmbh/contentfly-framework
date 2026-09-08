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
# mbstring, ctype, json, openssl, filter, hash, phar und tokenizer bringt das Image mit.
#
# Läuft in der Pipeline (008-005-0001) und beim lokalen Nachspielen mit demselben Aufruf.

set -eu

echo "→ Systempakete für gd"
apt-get update -qq
apt-get install -y -qq --no-install-recommends \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    > /dev/null

echo "→ gd konfigurieren"
docker-php-ext-configure gd --with-freetype --with-jpeg > /dev/null

echo "→ pdo_mysql und gd bauen"
docker-php-ext-install -j"$(nproc)" pdo_mysql gd > /dev/null

echo "→ Aufräumen"
rm -rf /var/lib/apt/lists/*

echo "✓ PHP $(php -r 'echo PHP_VERSION;') mit:"
php -m | grep -E '^(pdo_mysql|gd|mbstring|json|openssl|tokenizer)$' | sed 's/^/    /'
