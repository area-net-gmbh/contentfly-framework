---
id: 015-000-0009
title: hasUserId wertet id == User-ID als Eigentum
status: todo
depends_on: []
---

# hasUserId wertet id == User-ID als Eigentum

## Context
**Security-Scan 2026-09, MEDIUM. Finding F13.**

`Base::hasUserId` (`Entity/Base.php:283`) endet mit

```php
return in_array($id, $ids) || $this->id == $id;
```

und vergleicht damit die User-ID des Aufrufers mit dem **Primärschlüssel des Datensatzes**. Die
Eigentumsprüfungen in `Api::getSingle` (1973/1978), `doUpdate` (530), `doDelete` (112), in den
Join-, Multijoin-, Checkbox-, File- und Onejoin-Typen und in `FileController:643` stützen sich
darauf. Die Standard-ID-Strategie des Installers ist `auto`: Ganzzahlen, pro Tabelle gezählt.
Benutzer N passt damit auf Datensatz N in **jeder** Entity.

Folge: Jeder Nicht-Admin mit `OWN`- oder `GROUP`-Recht kann in jeder so verengten Entity
(`PIM\File` und `PIM\Group` eingeschlossen) genau einen fremden Datensatz lesen, ändern und
löschen, nämlich den mit seiner eigenen User-ID als ID.

## Acceptance criteria
- [ ] `hasUserId` prüft nur noch die zugeordneten Benutzer (`|| $this->id == $id` entfällt).
- [ ] Der Vergleich in `in_array` ist strikt und stimmt mit den `FIND_IN_SET`-Filtern von list/all/count überein.
- [ ] Das Bearbeiten des eigenen `PIM\User` funktioniert weiter. Das deckt `doUpdate` über `$object != auth.user` bereits ab, ein Test belegt es.

## Verification
Integrationstest mit Ganzzahl-IDs: Benutzer mit ID N und `OWN`-Recht auf eine Entity liest,
ändert und löscht dort Datensatz N, den ein anderer angelegt hat. Vor dem Fix gelingt das, nach
dem Fix antworten alle drei Zugriffe 403.
