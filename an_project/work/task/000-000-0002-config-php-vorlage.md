---
id: 000-000-0002
title: custom/config.php auf die Vorlage zurückführen — Platzhalter statt Kundendaten
status: done
depends_on: []
---

# custom/config.php auf die Vorlage zurückführen — Platzhalter statt Kundendaten

## Context
`custom/config.php` ist wie zuvor `custom/app.php` die Konfigurationsdatei eines Kundenprojekts
und nicht die Vorlage: Sie trägt fertige Werte (`DB_NAME`/`DB_USER`/`DB_PASS` mit dem Fallback
`usabiq`) statt der Platzhalter `$SET_DB_HOST`, `$SET_DB_NAME`, `$SET_DB_USER`, `$SET_DB_PASS`
und `'$SET_DB_GUID_STRATEGY'`, gegen die die Installation arbeitet.

Zwei Folgen:

1. **Das Framework gilt als installiert**, obwohl es das nicht ist — `$app['is_installed']`
   prüft genau `DB_HOST != '$SET_DB_HOST'`. Der Install-Command aus `012-002-0002` bricht
   deshalb sofort mit „ist bereits installiert" ab und lässt sich nicht end-to-end testen.
2. **Ein neues Projekt startet mit fremden Zugangsdaten** in seiner Konfiguration.

Aufgefallen bei der Umsetzung von `012-002-0002`. Derselbe Defekt wie `000-000-0001`, eine
Datei weiter — beim Ausdünnen von `custom/` wurde auch diese übersehen.

## Acceptance criteria
- [x] `custom/config.php` enthält die `$SET_*`-Platzhalter und keine projektspezifischen Werte
      mehr.
- [x] Alles, was inhaltlich zur Vorlage gehört (Struktur, Kommentare, sinnvolle Standardwerte
      für Zeitzone, Charset, Systemtypen), bleibt erhalten — die Datei soll erklären, was
      konfigurierbar ist.
- [x] Was an Kundenspezifischem entfernt wurde, ist in `an_project/docs/technical.md`
      festgehalten, wie bei `custom/app.php`.
- [x] `$app['is_installed']` ist auf einem frischen Checkout `false`.
- [x] Der Install-Command aus `012-002-0002` läuft gegen eine leere Datenbank durch und die
      offene Verifikation dieses Tasks ist damit nachgeholt.

## Verification
Auf einem frischen Checkout `php bin/console.php appcms:install --dry-run` aufrufen: Der Guard
darf **nicht** greifen, stattdessen müssen die Prüfungen laufen. Danach die Installation gegen
eine leere Datenbank fahren und Tabellen sowie den Benutzer `admin` nachweisen.
