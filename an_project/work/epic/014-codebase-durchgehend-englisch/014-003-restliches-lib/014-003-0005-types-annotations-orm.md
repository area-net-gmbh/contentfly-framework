---
id: 014-003-0005
title: Types, Annotations, ORM, Events und File auf Englisch
status: done
depends_on: [014-003-0004]
---

# Types, Annotations, ORM, Events und File auf Englisch

## Context
Umfang:
- `Classes/Type.php`, `Classes/Type/**`, `Classes/Types/**` (21 Feldtypen)
- `Classes/Annotations/**`
- `Classes/ORM/**` mit `EntityManagerFactory::erzeugen()` → `create()`
- `Classes/Events/**`
- `Classes/File/**` (Backend und Processing)
- `Classes/Exceptions/**`

## Acceptance criteria
- [x] Die Dateien des Tasks sind vollständig englisch: Namen, Methoden, Eigenschaften, Variablen, Konstanten, Meldungen und Kommentare.
- [x] Werte, die gespeichert oder über die API ausgeliefert werden (Tabellen- und Spaltennamen, Message-Keys, Token-`purpose`), bleiben unverändert, wenn sie schon englisch sind. Ein deutscher gespeicherter Wert wird nur nach Rückfrage geändert.
- [x] Alle Aufrufer im Baum sind mitgezogen (`lib`, `custom`, `bin`, `tests`, `tools`, `.gitlab-ci.yml`, `phpstan.neon.dist`, `rector.php`, `dev-guide.md`, soweit ein Test daran abgleicht). Testklassen, die nach einer umbenannten Klasse heissen, sind mit umbenannt.
- [x] Die Ausnahmen im Sprachwächter, die dieser Task auflöst, sind gestrichen.
- [x] Verhalten unverändert: volle Suite mit gleicher Testzahl, PHPStan `[OK]`, Deprecation-Gate grün, `console list` läuft, `custom/config.php` ohne Zugangsdaten.

## Verification
Volle Suite gegen `contentfly-db-0004` mit Vergleich der Zahlen, PHPStan (Result-Cache geleert),
`tools/ci/deprecations-pruefen.sh`, Nachsuche nach jedem alten Namen (auch in umgebrochenen Aufrufen),
Suche nach deutschen Wörtern in den Dateien des Tasks, `sh tools/check-template-config.sh`.

## Ergebnis

**Types, Annotations, ORM, Events, File und Exceptions sind englisch**, zusammen 56 Dateien.
Kommentare haben zwei Agenten übersetzt; die Code-Token sind in allen 56 Dateien identisch.
Danach sind die Namen und Meldungen umgestellt:
- `EntityManagerFactory::erzeugen()` → `create()`, mit `$queryCache`, `$metadataCache` und
  `$class`; Aufrufer sind `bootstrap.php` und `InstallCommand.php`
- `SelectType::wertPruefen()` → `validateValue()`, `$erlaubt` → `$allowed`
- Meldungen: „… is not one of the allowed options (…)", „Access to … denied.", „GDLib function
  … is not available on the server.", „ImageMagick function … is not executable on the server."

In den Kommentaren von `Select.php` und `ContentflyQuoteStrategy.php` sind zwei Beispiel-Platzhalter
englisch (`value=label`, `column_counter`).

**Bewusst noch nicht geändert:** `Events/LoadMetadata.php` zitiert im Kommentar die Meldung des
Installers („Die Installation ist fehlgeschlagen: …"). Die Meldung selbst ändert sich in `0006`,
und das Zitat zieht dort mit.

**Ein Commit, der eine fremde Datei mitnimmt:** `InstallCommand.php` gehört zu `0006`, trägt aber den
Aufruf `create()`. Ohne ihn wäre dieser Commit ein kaputter Zwischenstand, weil die Installation
`erzeugen()` riefe, das es nicht mehr gibt. Die Datei ist deshalb hier dabei, samt ihrer schon
übersetzten Kommentare. Namen und Meldungen des Commands folgen in `0006`.

Die Ausnahme für `EntityManagerFactory::erzeugen` im Sprachwächter ist gestrichen.

Geprüft: volle Suite `OK (528 tests, 1702 assertions)` wie vorher, PHPStan `[OK] No errors`,
Deprecation-Gate grün. Der Detektor findet in den 56 Dateien nur das Zitat in `LoadMetadata.php`.
