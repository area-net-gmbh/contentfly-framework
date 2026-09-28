---
id: 015-000-0011
title: lang in anderer Schreibweise umgeht die Sprachrechte
status: todo
depends_on: []
---

# lang in anderer Schreibweise umgeht die Sprachrechte

## Context
**Security-Scan 2026-09, MEDIUM. Finding F15.**

Der Request-Parameter `lang` geht unverändert in `I18nPermission` (`Classes/I18nPermission.php:87`,
aufgerufen aus `ApiController::updateAction` Zeile 709, ebenso Insert und Delete). Dort wird er als
Array-Schlüssel mit Beachtung der Gross-/Kleinschreibung nachgeschlagen, und ein unbekannter
Schlüssel gilt als **offen** (`Group.php:146-148`, `158-160`). Den Datensatz wählt dasselbe `lang`
dann per DQL `a.lang = :lang` (`Api.php:1870-1871`) unter `utf8_unicode_ci`, das Gross- und
Kleinschreibung nicht unterscheidet und nachgestellte Leerzeichen ignoriert (PAD SPACE).

Folge: Mit `EN`, `En` oder `en ` (nachgestelltes Leerzeichen) behandelt die Rechteprüfung die
Sprache als uneingeschränkt, die Abfrage trifft trotzdem die `en`-Zeile. Eine Gruppe mit
`languages = {"en":"readable"}` kann so englische Inhalte ändern (`/api/update`), löschen
(`/api/delete`) und anlegen (`/api/insert`).

## Acceptance criteria
- [ ] `lang` wird vor jeder Rechteprüfung und Abfrage normalisiert (trim, lowercase) und muss danach exakt in `APP_LANGUAGES` stehen, sonst 400.
- [ ] `Group::langIsWritable` und `langIsTranslatable` lehnen Schlüssel ab, die keiner konfigurierten Sprache entsprechen (fail closed).

## Verification
Integrationstest: Gruppe mit `{"en":"readable"}`, Update, Delete und Insert mit `lang: "EN"` und
`lang: "en "`. Vor dem Fix gehen sie durch, nach dem Fix antworten sie mit 403 bzw. 400, und die
`en`-Zeile ist unverändert.
