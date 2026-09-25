---
id: 000-000-0054
title: Den Lauf auf PHP 8.5 vorbereiten und die Zielplattform einlösen
status: done
depends_on: []
---

# Den Lauf auf PHP 8.5 vorbereiten und die Zielplattform einlösen

## Context
**PHP 8.5 ist die Zielplattform dieses Projekts — belegt ist bisher nur die Constraint-Seite.**
`LockGuaranteesTest` (`011-004-0001`) prüft, dass kein Paket PHP unter 8.5 deckelt. Ob die Suite
dort grün ist, sagt nur ein Lauf, und die Pipeline geht bis 8.4. In der Bilanz von Epic `011`
steht dieses Kriterium deshalb als ⚠️ und nicht als ✅.

**Die Begründung in jener Bilanz ist bereits überholt.** Sie sagt: *„Erst muss es ein
PHP-8.5-Image geben, das alle Erweiterungen mitbringt."* Nachgesehen am 2026-09-17:
**`php:8.5-cli` existiert**, aktuell `8.5.10-cli`. Das Image ist nicht mehr der Grund.

**Der Stand, erhoben statt vermutet:**

| | |
|---|---|
| Paket-Manifest | `"php": "^8.3"` — 8.5 ist damit erlaubt |
| `config.platform.php` | `8.3.0` — sagt Composer, wofür aufgelöst wird |
| Pipeline-Container | `php:8.3-cli` für die drei Prüf-Jobs, Matrix `['8.3', '8.4']` für `test` |
| Erweiterungen | `pdo_mysql`, `gd`, `ldap` über `docker-php-ext-install`, dazu `git` und `openssh-client` |
| Lock | kein Paket deckelt unter 8.5 (Gate, blockierend) |

## Was zu klären ist, bevor eine Zeile geändert wird
- **Bauen die Erweiterungen auf 8.5?** `pdo_mysql` und `gd` sind unkritisch; `ldap` ist die, die
  bei einem PHP-Sprung am ehesten klemmt. Das prüft ein Lauf von
  `tools/ci/install-php-extensions.sh` gegen `php:8.5-cli` — mehr braucht es dafür nicht.
- **Was 8.5 an Deprecations mitbringt.** Das Gate „0 Deprecations" ist blockierend und liest das
  Laufzeit-Log. Neue Meldungen aus 8.5 machen den Lauf rot — **das ist gewollt**, aber es ist
  Arbeit, die vorher benannt gehört und nicht im Pull Request überrascht.
- **Ob 8.5 eine dritte Matrix-Spalte wird oder 8.3 ablöst.** Drei Spalten kosten zwei Minuten
  mehr je Pull Request; 8.3 zu streichen hiesse, die Untergrenze aufzugeben, die das Manifest mit
  `^8.3` zusagt. **Solange `^8.3` im Manifest steht, muss 8.3 geprüft bleiben** — sonst sichert
  das Paket eine Version zu, die niemand fährt.
- **Ob `config.platform.php` mitzieht.** Sie steht bewusst nicht gleich der Zielplattform:
  `LockGuaranteesTest` erklärt den Unterschied — die Plattform sagt, worauf gefahren wird, das
  Ziel, wohin es geht. Wer sie anhebt, ändert die Auflösung für alle.

## Acceptance criteria
- [x] Erhoben und festgehalten, ob `install-php-extensions.sh` auf `php:8.5-cli` durchläuft — mit der Ausgabe als Beleg.
- [x] Die Deprecations eines Laufs auf 8.5 sind gezählt und benannt; jede bekommt einen Verursacher (eigener Code oder Abhängigkeit).
- [x] Entschieden, ob 8.5 als dritte Matrix-Spalte kommt oder 8.3 ablöst — mit dem Bezug zu `^8.3` im Paket-Manifest.
- [x] Entschieden, ob `config.platform.php` mitzieht, und die Entscheidung steht dort, wo `LockGuaranteesTest` sie sucht.
- [x] Die Bilanz in `an_project/work/epic/011-release-neue-version/epic.md` ist nachgezogen: Das ⚠️ wird zu ✅ oder trägt einen neuen, zutreffenden Grund.

## Verification
Ein Lauf der vollständigen Suite unter PHP 8.5 — lokal in Docker mit `php:8.5-cli` genügt für die
Erhebung; die Pipeline folgt erst mit der Entscheidung zur Matrix. Grün **und** 0 Deprecations,
oder eine Liste dessen, was dazwischensteht.

## Abgrenzung
**Dieser Task ändert die Zielplattform nicht und hebt keine Constraint.** Er erhebt, entscheidet
und schreibt auf. Die eigentliche Umstellung ist die Folge — und sie wird erst geschnitten, wenn
die Erhebung sagt, wie gross sie ist.

## Ergebnis (2026-09-25)
**PHP 8.5 trägt: Die Erweiterungen bauen, der Lock installiert, die Suite ist grün.** Zwischen dem
Lauf und einer Pipeline-Spalte stehen **eine** Deprecation im Server und drei Stellen im
Testprozess. Alle stammen aus eigenem Code, keine aus einer Abhängigkeit. Jede ist ein Aufruf, der
seit Jahren nichts mehr tut.

Umgebung: `php:8.5-cli` (PHP 8.5.11), der Pipeline-Job `test` Schritt für Schritt nachgestellt —
`prepare-test-environment.sh`, `phpunit`, `deprecations-pruefen.sh`, mit `CI=true`. Datenbank:
`mysql:8.0` aus `docker-compose.yml`. Stand: `master` bei `1ccf8e63`.

### Die Erweiterungen
`install-php-extensions.sh` läuft auf `php:8.5-cli` durch. Das Ende der Ausgabe:
```
✓ PHP 8.5.11 mit:
    gd
    json
    ldap
    mbstring
    openssl
    pdo_mysql
    tokenizer
    unzip 6.00 (Composer-Entpacker, keine PHP-Erweiterung)
    git version 2.47.3 (für tools/migration/inventory.php, nicht für Composer)
    openssh-client 1:10.0p1-7+deb13u4 (für den SSH-Bezug in tools/ci/bezugsweg-pruefen.sh)
```
**`ldap` klemmt nicht** — die Sorge aus dem Kontext war unbegründet.

### Der Lock unter 8.5
`composer check-platform-reqs --lock`: alle 17 Anforderungen `success`, `php 8.5.11` eingeschlossen.
`composer install` läuft durch, `composer audit --locked`: *No security vulnerability advisories
found.* Das ist mehr als `LockGuaranteesTest` belegt, der nur die Constraints liest.

### Die Suite
**833 Tests grün, 0 übersprungen**, 2:02 Minuten.

### Die Deprecations, gezählt und zugeordnet
| Wo | Meldung | Anzahl | Verursacher |
|---|---|---|---|
| Server, `Classes/File/Processing/Image.php` (Z. 169, 199, 388; dazu 79, 84, 89, 229 ohne Treffer) | `imagedestroy()` — seit 8.5 deprecated, **ohne Wirkung seit PHP 8.0** | 28 Zeilen im Log, 1 Paar für das Gate | eigener Code |
| Testprozess, `tests/Integration/IntegrationTestCase.php` und acht weitere Testdateien, `tools/migration/record-api.php` | `curl_close()` — **ohne Wirkung seit PHP 8.0** | 12 Stellen | eigener Code |
| Testprozess, `tests/Unit/Kernel/RouteNamesTest.php:67` | `ReflectionProperty::setAccessible()` — **ohne Wirkung seit PHP 8.1** | 1 | eigener Code |

**Das Gate wäre rot** — an `imagedestroy()`. Die Meldungen im Testprozess sieht es nicht: Die Suite
meldet Deprecations nur aus `<source>` (`restrictDeprecations`), und `tests/` und `tools/` gehören
nicht dazu. Sichtbar wurden sie erst mit einer Kopie der Konfiguration ohne diese Einschränkung.

**Aus Abhängigkeiten: keine.** Weder im Server-Log noch in der ungefilterten Unit-Suite.

**Dazu, nicht vom Gate erfasst — Warnungen, neu mit 8.5:** `list($width, $height) = getimagesize(…)`
in `FileController.php` (Z. 222 und 576). `getimagesize()` gibt für eine Nicht-Bilddatei `false`
zurück; PHP 8.5 warnt beim Destrukturieren eines Nicht-Arrays (`Cannot use bool as array`, 66-mal).
Unter 8.3 bleibt das still. Das Verhalten ändert sich nicht — `$width` und `$height` sind `null` wie
bisher.

### Entscheidungen
- **8.5 kommt als dritte Matrix-Spalte, 8.3 bleibt.** Das Manifest sagt `^8.3`. Fällt 8.3 aus der
  Matrix, sichert das Paket eine Version zu, die niemand mehr prüft. Zwei Minuten mehr je Pull
  Request sind der Preis dafür. 8.3 fällt erst, wenn die Untergrenze im Manifest steigt.
- **`config.platform.php` bleibt bei `8.3.0`.** Die Plattform bestimmt, wofür Composer den Lock
  auflöst. Angehoben auf 8.5, könnte ein Paket mit PHP 8.4 oder 8.5 als Mindestversion in den Lock
  kommen, und ein Projekt auf 8.3 könnte ihn nicht mehr installieren — gegen die eigene Zusage.
  Festgehalten im Docblock von `TARGET_PLATFORM` in `LockGuaranteesTest`, der Stelle, die Plattform
  und Ziel unterscheidet.

**Umgesetzt ist keine der beiden** — wie im Abschnitt *Abgrenzung* verlangt. Eine Spalte 8.5 wäre
heute am Gate rot. Die Folge ist ein eigener Task: die drei wirkungslosen Aufrufe streichen, die
beiden `getimagesize()`-Stellen gegen `false` absichern, dann die Spalte `test: PHP 8.5` in die Matrix
und in die erforderlichen Checks des Rulesets.

### Bilanz von Epic `011`
Das ⚠️ ist ein ✅: Das Kriterium lautet „`composer audit --locked` sauber unter der Zielplattform“.
Belegt ist jetzt nicht nur die Constraint-Seite, sondern Install, Audit und Suite auf 8.5.11. Offen
bleibt die Pipeline-Spalte, benannt unter *Was offen bleibt* mit dem zutreffenden Grund. Der alte Grund
— es fehle ein 8.5-Image — war seit 2026-09-17 überholt.
