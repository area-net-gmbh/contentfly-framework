---
id: 015-000-0016
title: Löschen einer Sprachvariante löscht alle anderen ohne Rechteprüfung
status: done
depends_on: []
---

# Löschen einer Sprachvariante löscht alle anderen ohne Rechteprüfung

## Context
**Security-Scan 2026-09, MEDIUM, Konfidenz mittel. Finding F25.**

`Api::doDelete` prüft die `OWN`/`GROUP`-Eigentümerschaft und `I18nPermission::isWritable` nur für
die Sprachzeile aus dem Request. Danach löscht es per DQL alle anderen Sprachzeilen derselben ID
(`Classes/Api.php:217`):

```php
DELETE FROM $entityFullName e WHERE e.id = :id AND NOT e.lang = :lang
```

Wem diese Zeilen gehören und was der Aufrufer in diesen Sprachen darf, wird nicht geprüft.

Ablauf: Eine Übersetzerin darf die Hauptsprache nur lesen, hat aber volle Rechte auf `en`, oder
`writable=ALL` mit `deletable=OWN`. Sie besitzt die `en`-Variante eines Datensatzes, den ein Admin
angelegt hat, und löscht diese Variante. Die geschützte Hauptsprach-Zeile und alle anderen
Übersetzungen verschwinden mit.

## Acceptance criteria
- [x] Vor dem Entfernen jeder weiteren Sprachzeile gelten für sie dieselben Prüfungen (Eigentum und `I18nPermission`) wie für die angefragte Zeile. Scheitert eine davon, wird nichts gelöscht, oder es wird nur die angefragte Zeile gelöscht. Welche der beiden Varianten gilt, ist entschieden und dokumentiert.
- [x] Wer alle Varianten löschen darf, löscht weiterhin alle in einem Aufruf.

## Verification
Integrationstest: Gruppe mit Hauptsprache `readable`, Datensatz des Admins mit `de`- und
`en`-Zeile, die `en`-Zeile gehört dem Testbenutzer. `/api/delete` mit `lang: "en"`. Vor dem Fix
ist auch die `de`-Zeile weg, nach dem Fix bleibt sie erhalten.
