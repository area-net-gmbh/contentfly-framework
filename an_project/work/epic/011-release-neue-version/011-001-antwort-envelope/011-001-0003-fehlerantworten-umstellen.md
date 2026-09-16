---
id: 011-001-0003
title: Fehlerantworten in dieselbe Form bringen
status: todo
depends_on: [011-001-0002]
---

# Fehlerantworten in dieselbe Form bringen

## Context
Fehler entstehen an einer Stelle — dem Handler in `bootstrap-web.php` — und tragen heute
`message`, `type`, `status` sowie je nach Ausnahme `message_value`, `message_entity`,
`message_lang`. Das ist eine zweite Form neben der Erfolgsform, und ein Client braucht beide.

Zielform laut `api-envelope.md`: `data` = `null`, `errors` = Liste, `meta` wie beim Erfolg. Offen
und hier zu entscheiden: welche Felder ein Eintrag in `errors` trägt (`code`, `detail`, und was aus
`message_value`/`message_entity`/`message_lang` wird) und ob mehrere Einträge heute überhaupt
entstehen können.

**Was sich nicht ändert:** die Statuscodes. Sie sind mit `000-000-0006` in Ordnung gebracht worden,
und der Rumpf wiederholt sie nicht — `status` fällt weg, wie in `api-envelope.md` entschieden.

## Acceptance criteria
- [ ] Der Fehlerhandler antwortet in der Zielform; `data` ist `null`, `errors` eine Liste.
- [ ] Die Felder der Contentfly-Ausnahmen (`message_value`, `message_entity`, `message_lang`) haben im Eintrag einen benannten Platz; keine Information geht verloren.
- [ ] Statuscodes unverändert, mit Test.
- [ ] `status` steht nicht mehr im Rumpf.
- [ ] Register und Leitfaden tragen den Bruch, mit der Zuordnung alt → neu je Feld.
- [ ] Volle Suite, PHPStan, Deprecation-Gate grün.

## Verification
Je ein Test für 400, 401, 403, 404 und 500: Form gleich, Statuscode wie vorher. Gegenprobe gegen
den Stand davor.
