---
id: 007-005-0001
title: Kopie bereitstellen und Bestandsaufnahme
status: todo
depends_on: []
---

# Kopie bereitstellen und Bestandsaufnahme

## Context
Bevor irgendetwas umgestellt wird, muss feststehen, **was** dieses Projekt vom Framework benutzt.
Bei einem Projekt dieser Grösse entscheidet die Bestandsaufnahme, wie gross der Rest der Story
wird — und ob Task `0003`/`0004` das ganze Projekt oder einen repräsentativen Ausschnitt nehmen.

Phase 1 des Leitfadens (`an_project/docs/migration.md`) verlangt eine Bestandsaufnahme — aber
nur als Frageliste. Hier wird sie **erhoben**, und das Werkzeug dafür ist selbst ein Ergebnis:
Das nächste Bestandsprojekt braucht dieselbe Liste.

**Am Rand bereits gesehen:** `backend/lib/contentfly/` weicht in 76 Dateien vom Import-Stand
dieses Repos (`b9284090`) ab. Die Frage ist, welche davon **Projekt-Patches am Framework** sind:
Die gehen beim Wechsel auf das Paket verloren, wenn niemand sie findet.

## Acceptance criteria
- [ ] **Eine lauffähige Kopie der Datenbank** liegt in einer eigenen Instanz, getrennt vom Bind-Mount `docker/mysql8/` des Projekts; der Bind-Mount ist nachweislich unberührt.
- [ ] **Das alte Projekt läuft** in seinem bestehenden Container und antwortet auf `/api/config` — Voraussetzung für Task `0002`.
- [ ] **Ein Inventar-Werkzeug** unter `tools/migration/` erhebt für ein beliebiges Projektverzeichnis: Entities, `@ORM`- und `@PIM`-Annotationen (gegen `pim-annotationen-migration.md`), eigene Types, Plugins, LoginManager, genutzte `$app[...]`-Schlüssel (gegen die feste Liste aus `architecture.md`), eigene Commands und Controller-Provider, Nutzung der **sieben Codepfade ohne Auslöser** aus dem Epic.
- [ ] **Die Framework-Kopie des Projekts ist eingeordnet:** Jede der abweichenden Dateien ist als Projekt-Patch, als Versionsunterschied oder als unklar markiert; jeder Projekt-Patch mit einem Satz, was er tut und ob das neue Framework das schon abdeckt.
- [ ] **Der Bericht** steht im Ergebnis dieses Tasks: Zahlen, die Einordnung der Patches, und eine Empfehlung, ob Task `0003`/`0004` das ganze Projekt oder einen Ausschnitt nehmen — mit Begründung.
- [ ] Im Projekt-Repo ist **nichts** geändert.

## Verification
`git -C <projekt> status` vor und nach dem Task leer. Das Inventar-Werkzeug einmal gegen die
Vorlage `custom/` dieses Repos laufen lassen (bekannte Zahlen) und einmal gegen das Projekt.
`/api/config` des alten Projekts antwortet.
