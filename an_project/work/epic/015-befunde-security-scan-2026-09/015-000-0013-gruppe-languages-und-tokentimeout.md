---
id: 015-000-0013
title: Nicht-Admins ändern languages und tokenTimeout ihrer Gruppe
status: done
depends_on: []
---

# Nicht-Admins ändern languages und tokenTimeout ihrer Gruppe

## Context
**Security-Scan 2026-09, MEDIUM. Findings F18, F19, F31.** F18 und F19 betreffen `languages`,
F31 zusätzlich `tokenTimeout`.

`RightsManagement::assertMayWrite` (`Classes/Security/RightsManagement.php:57`) erklärt die
Rechteverwaltung zur Admin-Sache, sperrt bei `PIM\Group` aber nur den Schlüssel `permissions`.
Zwei weitere Felder der Gruppe sind rechterelevant und für Nicht-Admins mit Schreibrecht auf die
Gruppe offen:
- **`languages`**, die Sprachrechte, die `I18nPermission::isWritable`, `isTranslatable` und
  `isOnlyReadable` durchsetzen. Die API speichert den Wert ungeprüft über
  `I18nPermissionsType::toDatabase` (`Types/I18nPermissionsType.php:62`) und
  `Group::setLanguages` (`Entity/Group.php:137`). Mit `{"languages":"{}"}` hebt ein Mitglied die
  Sprachsperren der eigenen Gruppe auf. Anderen Gruppen kann es Sperren wie
  `{"de":"readable"}` setzen.
- **`tokenTimeout`**, die Lebensdauer der Tokens der Gruppe (`TokenHandler::timeoutFor`). `0`
  heisst: läuft nie ab.

Voraussetzung ist Schreibrecht auf `PIM\Group`. Laut den Notizen zu `000-000-0090` vergibt das
kein bekanntes Projekt, es ist aber eine unterstützte Konfiguration.

## Acceptance criteria
- [x] Nicht-Admins können `languages` und `tokenTimeout` auf `PIM\Group` nicht ändern. Verglichen wird mit dem gespeicherten Wert, damit unveränderte Round-Trips weiter durchgehen (wie bei `permissions`).
- [x] Geprüft und entschieden: Positivliste der Felder, die Nicht-Admins auf Rechte-Entities schreiben dürfen, statt einer Sperrliste.
- [x] Die Form von `languages` wird geprüft: nur bekannte Sprachcodes, Werte nur `readable` oder `translatable`.

## Verification
Integrationstest: Ein Nicht-Admin mit Schreibrecht auf die eigene Gruppe setzt `languages` auf
`{}` und `tokenTimeout` auf `0`. Vor dem Fix wird beides gespeichert, nach dem Fix antworten beide
Versuche 403. Ein Update mit unveränderten Werten geht weiter durch.
