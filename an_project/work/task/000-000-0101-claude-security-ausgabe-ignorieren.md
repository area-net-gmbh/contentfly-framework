---
id: 000-000-0101
title: Die Ausgabe von Claude Code Security von Git fernhalten
status: review
depends_on: []
---

# Die Ausgabe von Claude Code Security von Git fernhalten

## Context
Das Plugin *Claude Code Security* legt bei jedem Scan einen Ordner `CLAUDE-SECURITY-<timestamp>/` im
Projektverzeichnis an — Bericht, JSONL, SARIF, Patch-Vorschläge. **Das Repository ist öffentlich.** Ein
versehentlich committeter Bericht veröffentlicht Schwachstellen, bevor sie behoben sind; `0097` hat
gezeigt, dass eine Lücke erst mit ihrem Fix sichtbar werden soll.

## Acceptance criteria
- [x] `.gitignore` schliesst `/CLAUDE-SECURITY-*/` aus, mit Begründung.
- [x] `git check-ignore` greift für eine Datei in einem solchen Ordner.

## Verification
`git check-ignore -v CLAUDE-SECURITY-<irgendwas>/<datei>` nennt die Regel aus `.gitignore`.

## Ergebnis (2026-09-25)
`/CLAUDE-SECURITY-*/` steht in `.gitignore`, bei den übrigen Laufzeitartefakten und vor der Regel
`!.an_framework/**`, die laut Kommentar die letzte bleiben muss. Belegt:
`git check-ignore -v CLAUDE-SECURITY-probe/x` → `.gitignore:79:/CLAUDE-SECURITY-*/`.

Bis zum Merge schützt eine gleichlautende Zeile in `.git/info/exclude` den lokalen Arbeitsbaum — sie
ist nicht versioniert und wirkt nur hier.
