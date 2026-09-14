---
id: 014-003-0006
title: Commands, Migration, Config und Manifest auf Englisch
status: review
depends_on: [014-003-0005]
---

# Commands, Migration, Config und Manifest auf Englisch

## Context
Umfang nach der Tabelle in Epic `014`:
- `ProviderAbgleichCommand` → `ProviderSyncCommand`, Command-Name `appcms:provider:sync`
- `ReencryptCommand::spalteUmschluesseln()` → `reencryptColumn()`
- `InstallCommand`, `SetupCommand` und `TokenCleanupCommand`: Beschreibungen, Ausgaben und Kommentare
- `Migration\EntfalleneAttributfelderRector` → `RemovedAttributeFieldsRector`, samt `rector.php`
- `Classes/Config.php` (der Rest nach `014-002`)
- `lib/contentfly/composer.json` (`description`, `suggest`, `extra.hinweis`)
- `config.sample.php` und `version.php`

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

**Die fünf Commands, die Rector-Regel, `Config.php`, `config.sample.php`, `version.php`, das
Framework-Manifest und `rector.php` sind englisch.** Kommentare haben zwei Agenten übersetzt, die
Code-Token sind identisch. Namen und Meldungen habe ich umgestellt:

- **`ProviderAbgleichCommand` → `ProviderSyncCommand`, Command `appcms:provider:sync`**, samt
  Variablen und Ausgaben. Der Test heisst `ProviderSyncApiTest` und prüft „could not give an
  answer".
- **`ReencryptCommand`:** `betroffeneFelder()` → `affectedFields()`, `spalteUmschluesseln()` →
  `reencryptColumn()`, Rückgabe-Schlüssel `checked`, `reencrypted`, `skipped` und `entity`,
  `field`, `table`, `column`, `key_column`, alle Variablen und Meldungen.
- **`TokenCleanupCommand`, `SetupCommand` und `InstallCommand`:** Beschreibungen, Optionstexte,
  Ausgaben, Fehlermeldungen und Variablen, dazu der DQL-Parameter `:now`. `console list` zeigt
  alle fünf Commands mit englischer Beschreibung.
- **`EntfalleneAttributfelderRector` → `RemovedAttributeFieldsRector`**, mit `$removed` und
  englischer Regelbeschreibung. `rector.php` referenziert die neue Klasse, und `RectorRegelTest`
  fährt die Regel darüber grün.
- **`lib/contentfly/composer.json`:** `description`, `suggest` und `extra.hinweis` → `extra.notes`,
  alles englisch.
- **`LoadMetadata.php`:** Das Zitat der Installer-Meldung lautet jetzt „The installation failed: …".

**`composer.lock` ist mitgezogen, und zwar gezielt:** Der Lock führt eine Kopie der Metadaten von
`areanet/contentfly`, die noch deutsch war. Aktualisiert ist nur dieses eine Paket, per
`composer update areanet/contentfly`. Keine andere Paketversion hat sich bewegt, geprüft durch
Vergleich aller Versionen vor und nach dem Lauf. Beim letzten Neuauflösen war genau das passiert.

**Für `0007` notiert:** Der Detektor findet im ganzen Paket noch den Indexnamen
`uniq_user_fremdkennung` und einen Kommentar-Platzhalter `<kennung>` in `Entity/User.php`, beide
aus `0001` übersehen.

Geprüft: volle Suite `OK (528 tests, 1702 assertions)` wie vorher, PHPStan `[OK] No errors`,
Deprecation-Gate grün, `composer validate` sauber.
