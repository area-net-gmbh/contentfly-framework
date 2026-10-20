<!-- PURPOSE: Framework-Baseline für die Commit-Message — Format, Types, Scopes, Body, Sprache, Ref-Zeile. Gelesen von /commit. Branch-Schema und Integration stehen in git-branching.md. -->

# Git Conventions — Framework-Baseline

Verbindliche Grundlage für **Commits**: Format, Types, Scopes, Body, Sprache und die
`Ref:`-Zeile. Gelesen von `.an_framework/commands/commit.md`.

**Wo** gearbeitet und **wie** integriert wird — Branch-Schema, Branch-Ebene, Standard-Ablauf,
Merge und PRs — steht in `.an_framework/docs/git-branching.md`, gelesen von `/implement`
und `/done`. Diese Datei hier wiederholt es nicht.

**Overlay-Regel:** Projekte kopieren diese Datei **nicht**, sondern **verengen** sie in
ihrer eigenen `an_project/docs/git.md` — vor allem die Scopes, die hier bewusst offen bleiben,
und die Sprache der Commit-Message. Dieselbe Overlay-Datei verengt auch `git-branching.md`;
bei Konflikt gewinnt die Projekt-Datei.

---

## Grundregeln

- Ein Commit = eine fachliche Intention
- Commits beschreiben **warum**, nicht nur was
- Commits sind revertierbar – kein Scope-Creep
- `master` ist jederzeit deploybar – **kein direkter Push aufs Remote**. Integriert wird
  je nach Projekt über `/done` (lokaler Merge) **oder** über Push + PR (siehe
  `.an_framework/docs/git-integration.md`, *Merge / Integration*)
- Commits entstehen über `/commit`, nicht von Hand — der Command liest diese Datei
  und die Verengung in `an_project/docs/git.md`

## Format

```
<type>(<scope>): <kurze Aussage im Präsens>

[Ref: #<ticket-id>]
[Ticket: <url>]

[optionaler Body]
```

Die `Ref:`-Zeile steht nur, wenn ein Work-Item existiert. Gibt es keines — Setup-Commit,
Framework-Bump, Repo-Chore — entfällt sie ersatzlos: nie `#000-000-0000`, nie `#none`,
nie eine erfundene ID.

Die URL eines externen Tickets steht **nie** in der `Ref:`-Zeile, sondern auf einer
eigenen `Ticket:`-Zeile darunter. Sie ergänzt die Work-Item-ID, sie ersetzt sie nie.
So bleibt `Ref: #EEE-SSS-TTTT` maschinell auffindbar.

## Types

| Type | Bedeutung |
|------|-----------|
| feat | Neues Feature |
| fix | Bugfix |
| improve | Funktionale Verbesserung |
| refactor | Umstrukturierung ohne Funktionsänderung |
| perf | Performance-Optimierung |
| docs | Dokumentation |
| test | Tests |
| chore | Build / Tooling / CI |
| security | Sicherheitsrelevant |
| revert | Rücknahme eines Commits |
| wip | Work in Progress – nicht merge-fähig |

## Scopes

Fachliche Module, nicht Dateinamen. Ein Commit darf mehrere technische Bereiche
(Backend / DB / Frontend) betreffen, solange sie einer Intention dienen.
→ Projektspezifisch ergänzen in `an_project/docs/git.md`: `authentication`, `data-model`,
`infrastructure`, `ci`, `docs` …

## Body (optional)

Nutzen wenn Logik, Risiko oder Auswirkungen erklärungsbedürftig sind:

```
WHY:     Begründung für die Änderung
WHAT:    Was wurde umgesetzt
AFFECTS: backend / db / frontend / …
```

## Sprache der Commit-Message

Default **Deutsch**, wie die allgemeine Content-language-Regel in
`.an_framework/AGENT-GUIDE.md`. Projektabweichung: `an_project/docs/git.md`, Abschnitt
*Commit language* (gewinnt bei Konflikt). `/commit --de` / `--en` überschreibt für einen Lauf.
Immer englisch bleiben: Types, Scope und die Schlüssel `Ref:` · `Ticket:` · `WHY:` ·
`WHAT:` · `AFFECTS:`.

---

## Commit-Referenz

Jeder Commit auf dem Branch trägt die ID des Work-Items — mit externem Ticket auf
einer eigenen Zeile darunter:

```
feat(authentication): Login-Formular mit Passwort-Reset-Link

Ref: #001-001-0001
Ticket: https://tracker.example/browse/ABC-123
```

`/commit` leitet die ID in dieser Reihenfolge ab, erster Treffer gewinnt:

1. das explizit übergebene Argument,
2. der Branch-Name nach dem Schema `<type>/<EEE-SSS-TTTT>-<slug>` (`.an_framework/docs/git-branching.md`),
3. das einzige Work-Item mit `status: in-progress` auf Task-Ebene,
4. keines davon → die `Ref:`-Zeile entfällt, nach ausdrücklicher Bestätigung.

Widersprechen sich Argument und Branch, wird gefragt statt geraten. **Eine Ausnahme:** der
Story-Branch. Ist die Branch-ID eine Story (`…-0000`) und das Argument ein Task **derselben**
Story (gleiches `EEE-SSS`), gewinnt das Argument — das ist der Normalfall aus
`/implement <story-id>`, kein Widerspruch. Alles andere stoppt weiterhin.

Ein fehlgeschlagener Commit — Pre-Commit-Hook, Signatur — wird gemeldet, nicht
umgangen: `--no-verify` und `--no-gpg-sign` sind verboten.

<!-- ERGÄNZUNGSBEDARF (projektspezifisch in an_project/docs/git.md festlegen):
     - Sprache der Commit-Message (de / en) — Baseline ist `de`.
       Wird von /commit gelesen: Abschnitt "Commit language"
     - Scopes des Projekts (Abschnitt "Scopes")
     - Basis-URL des externen Trackers, falls es einen gibt (Abschnitt "Ticket URL
       base"). Reine Doku fuer Menschen — /commit bekommt die vollstaendige URL als
       Argument und liest den Abschnitt nicht.
     Die Baseline macht dazu bewusst keine Vorgabe. -->
