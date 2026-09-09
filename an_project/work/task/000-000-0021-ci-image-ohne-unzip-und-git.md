---
id: 000-000-0021
title: Das CI-Image kann composer install nicht ausführen
status: todo
depends_on: [006-003-0002]
---

# Das CI-Image kann composer install nicht ausführen

## Context
Gefunden mit `006-003-0002` beim Nachmessen der Build-Dauer im Pipeline-Image. Der Befund ist
nicht die Dauer, sondern dass der Schritt überhaupt nicht läuft:

```
Failed to download psr/cache from dist:
  The zip extension and unzip/7z commands are both missing, skipping.
Source fallback is disabled. Not trying alternative sources.
```

`php:8.3-cli` bringt **weder** die `zip`-Extension **noch** `unzip`, `7z` **noch** `git` mit —
alle vier einzeln nachgemessen, alle vier fehlen. `tools/ci/install-php-extensions.sh` baut nur
`pdo_mysql` und `gd`; die Datei nennt in ihrem Kopf ausdrücklich, was das Image mitbringe, und
`zip` steht dort nicht.

Damit scheitert `composer install` in **beiden** Ausprägungen:

| Aufruf | Ergebnis |
|---|---|
| `composer install --prefer-dist` (so steht es in `.gitlab-ci.yml`) | scheitert — kein Entpacker |
| `composer install` ohne den Schalter | scheitert ebenso — der Quell-Fallback braucht `git`, und das fehlt auch |

**Der Bruch ist älter als `006-003`.** Er entstand mit `006-002-0004`, als der
`composer install`-Schritt in `.gitlab-ci.yml` kam. Solange `vendor/` noch committet war, wäre
der Job trotzdem rot gewesen — ein fehlgeschlagenes `before_script` bricht ab, der committete
Baum hätte ihn nicht gerettet. Aufgefallen ist es erst jetzt, weil `006-003-0002` das Image
zum ersten Mal seit `008-005-0001` wieder von Null gefahren hat.

## Umfang
`tools/ci/install-php-extensions.sh` ist die Stelle: Sie rüstet das Basis-Image auf das aus,
was das Framework braucht — und ein Entpacker gehört dazu, sobald der Baum im Job entsteht.

Zu entscheiden ist, welcher Weg genommen wird:

- **`unzip` als Systempaket** — der billigere Weg. Composer benutzt das Kommando, die
  PHP-Extension braucht es dafür nicht.
- **`zip` als PHP-Extension** (`libzip-dev` + `docker-php-ext-install zip`) — teurer im Build,
  aber unabhängig von einem Systemkommando.

`git` ist die zweite Frage: Ohne es gibt es keinen Quell-Fallback. Solange jedes Paket des
Locks als `dist` verfügbar ist, wird er nicht gebraucht — aber genau darauf hat sich der
alte Vendor-Baum schon einmal verlassen und daneben gelegen (`006-001-0003`: 27 von 78 Paketen
lagen als `source` ohne `.git`).

Der Kopfkommentar der Datei zählt auf, was das Image mitbringt. Er ist Teil der Änderung, nicht
Beiwerk — er ist heute falsch.

## Abgrenzung
Keine Änderung an `.gitlab-ci.yml` selbst; der Aufruf dort stimmt. Keine Änderung an den
Constraints in `composer.json`. Kein `composer audit` — das ist `006-005`.

## Acceptance criteria
- [ ] `tools/ci/install-php-extensions.sh` stellt einen Entpacker bereit; die Wahl zwischen
      `unzip` und der `zip`-Extension ist begründet.
- [ ] Entschieden und begründet, ob `git` mit ins Image gehört.
- [ ] Der Kopfkommentar der Datei nennt den neuen Stand — er behauptet heute, das Image bringe
      alles Nötige ausser `pdo_mysql` und `gd` mit.
- [ ] `composer install --no-interaction --no-progress --prefer-dist` läuft im Image durch und
      erzeugt die 77 Pakete des Locks.

## Verification
Der vollständige `before_script`-Ablauf lokal in Docker nachgespielt, mit demselben Image und
denselben Aufrufen wie in `.gitlab-ci.yml` — so wie `008-005-0001` es vorgemacht hat:

```sh
docker run --rm -v "$PWD":/src:ro php:8.3-cli sh -c '
  sh /src/tools/ci/install-php-extensions.sh
  php -r "copy(\"https://getcomposer.org/installer\",\"/tmp/composer-setup.php\");"
  php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet
  mkdir -p /build && cp /src/composer.json /src/composer.lock /build/ && cd /build
  composer install --no-interaction --no-progress --prefer-dist'
```

Danach derselbe Lauf im Image `php:8.4-cli` — der zweite Job der Pipeline benutzt es.
