---
id: 000-000-0087
title: Contentfly mit gewöhnlichem Composer beziehbar machen — ohne Schlüssel, Sonderpfade und Token-Kollisionen
status: todo
depends_on: []
---

# Contentfly mit gewöhnlichem Composer beziehbar machen — ohne Schlüssel, Sonderpfade und Token-Kollisionen

## Context
**Gefunden bei der Abnahme von `000-000-0086` (2026-09-23).** Der Bezugsweg funktioniert — aber nur
mit Vorwissen, das nirgends vollständig steht. Zwei Anläufe scheiterten hintereinander, und **beide
meldeten dasselbe: `Repository not found`.** Die Meldung zeigt auf ein Paket, das es angeblich nicht
gibt, und nie auf die wirkliche Ursache.

1. **Ein GitHub-Token entführt die URL.** Steht in `~/.composer/auth.json` ein `github-oauth`-Token
   und `github-protocols` auf `["https","ssh"]` — die Voreinstellung —, schreibt Composer die
   SSH-URL auf HTTPS um und authentifiziert sich mit diesem Token. Hat es keinen Zugriff auf das
   Paket-Repository, ist das Ergebnis `Repository not found`. Der Entwickler hat einen gültigen
   SSH-Schlüssel und kommt trotzdem nicht ans Paket.
2. **Der SSH-Schlüssel gilt nur im Repo.** Im Entwicklungs-Repo steht `core.sshCommand` mit
   `id_ed25519_areanet` — **lokal**, also nur dort. Ein Projekt liegt woanders, und dort greift die
   Zeile nicht. Der CI-Job setzt deshalb `GIT_SSH_COMMAND` ausdrücklich; im Skriptkopf von
   `tools/ci/bezugsweg-pruefen.sh` steht es als Voraussetzung, weil git es aus der Umgebung liest.

Dazu die Falle, die der Runbook bereits beschreibt: Ohne
`"preferred-install": {"areanet/contentfly": "source"}` holt Composer das Paket als Zip über die
GitHub-REST-API, die einen SSH-Deploy-Key nicht kennt — und antwortet mit `404`.

**Drei Stolpersteine für eine Installation, und jeder meldet „gibt es nicht".** Das ist kein
Dokumentationsproblem, das man wegschreibt. Ein Framework, das ein Projekt beziehen soll, muss sich
mit `composer require` beziehen lassen.

### Die Asymmetrie, an der das hängt
Gemessen am 2026-09-23 über die GitHub-API, ohne Anmeldung:

| Repository | Inhalt | Sichtbarkeit |
|---|---|---|
| `contentfly-framework` | der gesamte Quellcode, das Backlog, die Tickets, die die geschlossenen Lücken beschreiben | **öffentlich** (200) |
| `contentfly-framework-dist` | dasselbe als Auslieferungspaket | **privat** (404) |

**Der Quellcode ist längst öffentlich** — `git.md` hält ausdrücklich fest, dass die Schutzregeln
erst mit der Veröffentlichung des Repositories wirksam wurden, und `000-000-0084` nennt die Lücken
„im öffentlichen Repository beschrieben". Das private Paket-Repository schützt also nichts, was
nicht ohnehin einsehbar ist. Es kostet dafür bei jeder Installation einen Schlüssel und drei
Sonderfälle.

Das ist die erste Frage, die dieser Task beantwortet — nicht stillschweigend, sondern als
festgehaltene Entscheidung.

## Acceptance criteria
- [ ] **Entschieden und begründet**, wie Contentfly künftig bezogen wird, mit den verworfenen
      Alternativen, in `an_project/docs/architecture.md` unter *Key decisions*. Zur Wahl stehen
      mindestens: `contentfly-framework-dist` öffentlich machen · auf Packagist veröffentlichen
      (dann entfällt der `repositories`-Eintrag ganz) · Private Packagist · privat bleiben und den
      Zugang so einrichten, dass er ohne Sonderfälle trägt.
- [ ] Ein Projekt richtet sich nach der Entscheidung **ohne** repo-lokale Git-Konfiguration, **ohne**
      `GIT_SSH_COMMAND` und **ohne** isoliertes `COMPOSER_HOME` ein.
- [ ] Ein vorhandenes `github-oauth`-Token in `~/.composer/auth.json` bricht die Installation nicht
      mehr — das ist der Fall, der heute am schwersten zu erkennen ist.
- [ ] `tools/ci/bezugsweg-pruefen.sh` läuft **lokal** ohne Sondervariablen grün, nicht nur im
      CI-Job mit Deploy Key.
- [ ] Bleibt ein Sonderfall unvermeidbar, meldet er sich mit einer Meldung, die die Ursache nennt —
      nicht mit `Repository not found`. Dasselbe Muster wie `000-000-0029`, `007-001-0002` und die
      Gegenprobe in `011-002-0004`: Eine unterdrückte oder irreführende Diagnose kostet dieselbe
      Stunde wie gar keine.
- [ ] `an_project/docs/runbook.md` und der Abschnitt *Phase 2 — Bezugsweg* in `migration.md` geben
      den neuen Weg wieder; die heutige `dist`/`source`-Erklärung wird angepasst oder entfällt.
- [ ] Registereintrag, falls sich für Bestandsprojekte der `repositories`-Eintrag oder die
      Constraint ändert.

## Verification
`tools/ci/bezugsweg-pruefen.sh` auf einer Maschine, die **nicht** dieses Entwicklungs-Repo kennt —
oder ersatzweise mit einer Umgebung, in der `core.sshCommand` nachweislich nicht greift und ein
`github-oauth`-Token gesetzt ist. Das ist genau die Lage, in der die Abnahme von `000-000-0086`
zweimal scheiterte; sie ist der Prüfstein.

Dazu die Gegenprobe des Tages: `composer require areanet/contentfly` in einem frischen Verzeichnis,
mit nichts als der Anleitung aus dem Runbook.

## Nicht Teil dieses Tasks
Der Weg, auf dem das Paket **entsteht** — `paket.yml` baut den Split und schreibt ihn ins
Paket-Repository. Hier geht es nur darum, wie ein Projekt es von dort bezieht.
