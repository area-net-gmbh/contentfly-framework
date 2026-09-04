<!-- PURPOSE: Landeseite in GitLab. Einziger Zweck: Leute in unter 10 Sekunden zur richtigen Anleitung schicken. Enthaelt bewusst keine Setup-Befehle — die stehen ausschliesslich in tutorial/quickstart.md. -->

# ki-dev-framework

Unsere gemeinsame Arbeitsgrundlage für Projekte mit Claude Code: gleiche
Ordnerstruktur, gleiche Slash-Commands, gleiche Git-Konventionen in jedem Projekt.

Das Framework wird als getaggter Release unter `.an_framework/` in ein Projekt
kopiert und mitcommittet — dort nur gelesen, nie verändert. Ein plain `git clone`
holt damit automatisch die richtige Framework-Version mit.

---

## 👉 Womit fange ich an?

| Wer du bist | Wo du anfängst |
|---|---|
| **Neu hier — egal ob Entwickler:in oder nicht** | **[tutorial/quickstart.md](tutorial/quickstart.md)** — Schritt für Schritt: Installation, Projekt aufsetzen, Framework nachrüsten, Projekt übernehmen, Arbeitsalltag, Fehlersuche. Für macOS und Windows. Kein Vorwissen nötig. |
| **Dein Projekt läuft schon und soll eine neuere Framework-Version bekommen** | **[tutorial/update.md](tutorial/update.md)** — Schritt für Schritt in ~10 Minuten: Version heraussuchen, Kern tauschen, Neues nachziehen, als eigener Commit abschicken. Mit Fehlerfällen und einer Tabelle, welche Version welchen Zusatzhandgriff braucht. |
| Du kennst Git und willst das Modell verstehen | [tutorial/how-to-use.md](tutorial/how-to-use.md) — Work-Modell, Overlay-Regel, warum ein Update so läuft, Troubleshooting mit Ursachen. |
| Du willst am Framework **selbst** arbeiten | [CLAUDE.md](CLAUDE.md) und [docs/framework-architecture.md](docs/framework-architecture.md). |

> Wenn du nur eines liest: **[tutorial/quickstart.md](tutorial/quickstart.md)**.

---

## Was hier drin liegt

| Ort | Inhalt |
|---|---|
| [`AGENT-GUIDE.md`](AGENT-GUIDE.md) | Die Regeln, die jedes Projekt per `@`-Import in seine `CLAUDE.md` zieht. |
| [`commands/`](commands/) | Die zehn Slash-Commands im Volltext: `board` · `briefing` · `commit` · `done` · `implement` · `new-epic` · `new-project` · `new-story` · `new-task` · `refine`. Dazu `implement-task` als reiner Alias auf `implement` — für Projekte, die vor v2.2 aufgesetzt wurden. |
| [`templates/`](templates/) | Die Blueprints für Epic, Story und Task. |
| [`docs/`](docs/) | Baseline für Code-Stil und Git-Konventionen, dazu die Architektur des Frameworks. |
| [`skeleton/`](skeleton/) | Die Startausstattung, die beim Einrichten **einmal** in ein Projekt kopiert wird. |
| [`tutorial/`](tutorial/) | Die drei Anleitungen für Menschen: [quickstart](tutorial/quickstart.md) (einrichten), [update](tutorial/update.md) (auf eine neue Version heben), [how-to-use](tutorial/how-to-use.md) (verstehen). |
| [`VERSION`](VERSION) | Die aktuelle Version. Projekte tragen sie im mitcommitteten `.an_framework/VERSION`. |

## Das Arbeitsmodell in drei Zeilen

Arbeit liegt als **Epic → Story → Task** unter `an_project/work/` im Projekt, jedes Item mit einer
ID `EEE-SSS-TTTT`. Angelegt wird nichts von Hand — dafür gibt es `/new-epic`,
`/new-story`, `/new-task`. Umgesetzt wird mit `/implement <id>`; **die ID entscheidet die
Ebene**: ein Task bekommt seinen eigenen Branch, eine Story **einen Story-Branch** mit einem
Commit je Task, ein Epic plant nur und fragt vor jeder Story nach. Abgeschlossen wird mit
`/done` (lokaler Merge in den Integrationsbranch). Den Überblick gibt `/board`. Neu im Team
gibt `/briefing` in wenigen Minuten den Einstieg in ein bestehendes Projekt.

## Die eine Regel

**Der Kern wird zur Laufzeit nur gelesen.** In einem Projekt ist `.an_framework/`
read-only — jetzt per Konvention, nicht mehr per Submodule-Mechanik. Änderungen am
Framework gehören in dieses Repo, nicht in ein einzelnes Projekt.

---

<!-- Keine Setup-Befehle in dieser Datei. Sie stehen ausschliesslich in
     tutorial/quickstart.md (Teil B, C und D) — eine zweite Kopie driftet garantiert
     auseinander, siehe die Anti-Drift-Regel in CLAUDE.md. -->
