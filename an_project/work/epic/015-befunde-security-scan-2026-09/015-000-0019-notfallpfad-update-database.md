---
id: 015-000-0019
title: Notfallpfad erlaubt anonymes updateDatabase bei kaputtem Schema
status: todo
depends_on: []
---

# Notfallpfad erlaubt anonymes updateDatabase bei kaputtem Schema

## Context
**Security-Scan 2026-09, LOW. Finding F33.**

Wirft `authenticate()` eine `InvalidFieldNameException`, verschluckt die `checkAuth`-Closure in
`SystemControllerProvider` (`Classes/Controller/Provider/Base/SystemControllerProvider.php:59`)
sie, sobald der Body `method=updateDatabase` enthält. `/system/do` läuft dann ohne Benutzer und
ohne Admin-Prüfung. Die Aktion ist `SchemaTool::updateSchema`, das unter ORM 3 den vollen Diff
anwendet, einschliesslich Drops.

Ablauf: Direkt nach einem Deploy, der das Mapping von User oder Token ändert, und bevor die
Betreiber migrieren, sendet ein Angreifer `POST /system/do {"method":"updateDatabase"}` mit einem
beliebigen Bearer-Wert. Die kaputte Token-Abfrage wirft, der Notfallzweig lässt durch, und
`updateSchema` läuft zu einem Zeitpunkt, den der Angreifer wählt. Dabei kann es Spalten oder
Tabellen verwerfen, die migriert werden sollten.

## Acceptance criteria
- [ ] Der anonyme Notfallpfad ist entfernt. Schema-Reparatur geht nur über die Console.
- [ ] Die Doku (`runbook.md`, `deployment.md`) nennt den Console-Weg für diesen Fall, und der Wegfall steht in `breaking-changes.md`.

## Verification
Test: Schema von `pim_token` absichtlich vom Mapping abweichen lassen, dann
`POST /system/do {"method":"updateDatabase"}` mit beliebigem Token. Vor dem Fix läuft das Update,
nach dem Fix antwortet der Request 401 bzw. 500, und das Schema ist unverändert.
