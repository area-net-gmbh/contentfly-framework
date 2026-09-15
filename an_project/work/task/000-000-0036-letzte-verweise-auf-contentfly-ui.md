---
id: 000-000-0036
title: Letzte Verweise auf die entfernte Oberfläche (contentfly-ui)
status: review
depends_on: []
---

# Letzte Verweise auf die entfernte Oberfläche (contentfly-ui)

## Context
Epic `012` hat die PIM-Oberfläche ersatzlos gestrichen. Der letzte Rest auf der Platte,
`lib/contentfly-ui/` (unversioniert, nur noch eine `.DS_Store`), ist am 2026-09-15 gelöscht
worden. **Im versionierten Baum stehen noch Verweise darauf**, gesucht mit
`git grep 'contentfly-ui\|PIM-UI'` ausserhalb von Backlog und Changelog:

| Stelle | was dort steht | Art |
|---|---|---|
| `build.xml`, Target `bower` | `bower install` in `lib/contentfly-ui/assets/`; `main` hängt davon ab | ausführbar — schlägt fehl, das Verzeichnis gibt es nicht |
| `build.xml`, Target `directories` | fünf `mkdir custom/Frontend/contentfly-ui/…` | ausführbar — legt Ordner für eine Oberfläche an, die es nicht gibt |
| `README.md:35` | „AngularJS für die Oberfläche" in der Stack-Liste | falsche Aussage über den Ist-Stand |
| `README.md:56` | `appcms/areanet/PIM-UI => lib/contentfly-ui` im Abschnitt „Migration 1.5 auf 1.6" | historisch richtig, aber ohne Hinweis, dass das Ziel entfallen ist |
| `an_project/docs/architecture.md:150` | „`lib/contentfly-ui/` liegt unversioniert … noch auf der Platte" | seit dem Löschen falsch |

`an_project/docs/tech-stack.md` und `an_project/project-description.md` nennen
`lib/contentfly-ui` als das, was mit Epic `012` gestrichen wird. Das ist der beschriebene Plan und
bleibt stehen.

**Bewusst nicht Teil dieses Tasks:** `build.xml` hat weitere Altlasten ohne Bezug zur Oberfläche
(`zip-release` packt eine `licence.txt`, die es nicht gibt; `files` legt Traits an und kopiert
`config.sample.php` statt `appcms:install` zu benutzen). Ob die Datei insgesamt noch gebraucht
wird, ist eine eigene Frage. Hier fallen nur die Oberflächen-Reste.

## Acceptance criteria
- [x] `build.xml` enthält kein `contentfly-ui` und kein `bower` mehr; `main` hängt nicht mehr an einem entfernten Target.
- [x] `README.md` nennt AngularJS nicht mehr als Teil des Stacks, und die Zeile im Abschnitt „Migration 1.5 auf 1.6" sagt, dass `lib/contentfly-ui` mit Epic `012` entfallen ist.
- [x] `architecture.md` beschreibt `lib/` ohne den Rest auf der Platte.
- [x] `git grep 'contentfly-ui\|PIM-UI'` trifft ausserhalb von Backlog, Changelog und Abhängigkeiten-Inventar nur noch die beiden Plan-Aussagen in `tech-stack.md` und `project-description.md` sowie die korrigierte README-Zeile.
- [x] `build.xml` ist wohlgeformtes XML, und die volle Suite bleibt grün.

## Verification
`git grep -n 'contentfly-ui\|PIM-UI\|bower\|AngularJS'` vorher und nachher. `php -r` mit
`simplexml_load_file('build.xml')` auf Wohlgeformtheit. Volle Suite.

## Ergebnis

**Die fünf Stellen sind nachgezogen:**

- `build.xml`: Target `bower` entfernt, `main` hängt nicht mehr daran, die fünf
  `mkdir custom/Frontend/contentfly-ui/…` sind weg. Wohlgeformt (`simplexml_load_file` liefert
  ein Objekt).
- `README.md`: AngularJS aus der Stack-Liste gestrichen. Die Zeile im Abschnitt „Migration 1.5 auf
  1.6" bleibt als Geschichte stehen und trägt den Zusatz „mit Epic `012` ersatzlos entfallen".
- `architecture.md`: Die Zeile zu `lib/` sagt nur noch „der Frameworkcode". Die Aussage über den
  Rest auf der Platte stimmte nicht mehr.

**`git grep 'contentfly-ui\|PIM-UI'`** ausserhalb von Backlog, Changelog und
Abhängigkeiten-Inventar: vorher 10 Treffer in fünf Dateien, nachher 3 — die korrigierte
README-Zeile und die beiden Plan-Aussagen in `tech-stack.md` und `project-description.md`.

**Nicht angefasst:** `.gitignore` trägt `npm-debug.log` mit dem Kommentar „aus bower_components".
Die Regel nennt keinen Pfad zur Oberfläche und schadet nicht; ihr Kommentar ist Geschichte.

**Verifiziert:** volle Suite `Tests: 534, Assertions: 1746, Skipped: 3`. Der erste Lauf war rot
(254 Fehler und Fehlschläge), weil der Datenbank-Container nicht mehr lief; der
Umgebungswächter meldete genau das. Nach `docker compose up -d` und neuer Installation grün.
