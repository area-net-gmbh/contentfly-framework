---
id: 000-000-0087
title: Contentfly mit gewöhnlichem Composer beziehbar machen — ohne Schlüssel, Sonderpfade und Token-Kollisionen
status: done
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
- [x] **Entschieden und begründet**, wie Contentfly künftig bezogen wird, mit den verworfenen
      Alternativen, in `an_project/docs/architecture.md` unter *Key decisions*. Zur Wahl stehen
      mindestens: `contentfly-framework-dist` öffentlich machen · auf Packagist veröffentlichen
      (dann entfällt der `repositories`-Eintrag ganz) · Private Packagist · privat bleiben und den
      Zugang so einrichten, dass er ohne Sonderfälle trägt.
- [x] Ein Projekt richtet sich nach der Entscheidung **ohne** repo-lokale Git-Konfiguration, **ohne**
      `GIT_SSH_COMMAND` und **ohne** isoliertes `COMPOSER_HOME` ein.
- [x] Ein vorhandenes `github-oauth`-Token in `~/.composer/auth.json` bricht die Installation nicht
      mehr — das ist der Fall, der heute am schwersten zu erkennen ist.
- [x] `tools/ci/bezugsweg-pruefen.sh` läuft **lokal** ohne Sondervariablen grün, nicht nur im
      CI-Job mit Deploy Key.
- [x] Bleibt ein Sonderfall unvermeidbar, meldet er sich mit einer Meldung, die die Ursache nennt —
      nicht mit `Repository not found`. Dasselbe Muster wie `000-000-0029`, `007-001-0002` und die
      Gegenprobe in `011-002-0004`: Eine unterdrückte oder irreführende Diagnose kostet dieselbe
      Stunde wie gar keine.
- [x] `an_project/docs/runbook.md` und der Abschnitt *Phase 2 — Bezugsweg* in `migration.md` geben
      den neuen Weg wieder; die heutige `dist`/`source`-Erklärung wird angepasst oder entfällt.
- [x] Registereintrag, falls sich für Bestandsprojekte der `repositories`-Eintrag oder die
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

## Ergebnis (2026-09-23)
**`contentfly-framework-dist` ist öffentlich, und der Bezugsweg braucht keinen Zugang mehr.**
Ein Projekt trägt eine HTTPS-URL ein und ruft `composer require` — kein Deploy Key, kein
Organisationskonto, kein SSH-Schlüssel, kein `preferred-install`.

### Vorher geprüft, ob das Repository öffentlich werden darf
Tag `v2.2.1` ausgecheckt und gegen `lib/contentfly` im ohnehin öffentlichen Repo verglichen:
**161 Dateien, identisch**; gesucht nach `*.env`, `*.key`, `*.pem`, `*secret*`, `*credential*` —
nichts. Es gab nichts zu schützen.

### Geändert
| Datei | Was |
|---|---|
| `runbook.md` | „Voraussetzung: Lesezugriff" → kein Zugang nötig; URL auf HTTPS; `preferred-install` aus der Vorlage; der erklärende Block sagt jetzt, warum es ihn gab und warum er schädlich wäre |
| `migration.md` | Phase 2 auf HTTPS ohne `config`-Block, mit Hinweis für Bestandsprojekte, was in ihrer `composer.json` noch steht |
| `lib/contentfly/README.md` | HTTPS — diese Datei wandert ins Paket-Repository und ist das, was dort jemand liest |
| `tools/ci/bezugsweg-pruefen.sh` | `PAKET_REPO_URL` auf HTTPS, `preferred-install` aus dem erzeugten Projekt, Kopf und Kommentare nachgezogen |
| `.github/workflows/pipeline.yml` | Die drei Deploy-Key-Stufen im Job *Bezugsweg von aussen* entfallen (**35 Zeilen**): Schlüssel hinlegen, `ssh-keyscan` mit Gegenprobe, Schlüssel wegräumen |
| `architecture.md` | Entscheidung mit den vier erwogenen Alternativen unter *Key decisions* |
| `breaking-changes.md` | Registereintrag; Register jetzt **135 Einträge**, `migration.md` nachgezogen |

**Nicht geändert:** `paket.yml` schiebt den Split weiterhin mit dem Deploy Key ins
Paket-Repository. Schreibzugriff braucht ihn auch bei einem öffentlichen Repository.

**Die Dependabot-Ausnahme bleibt, ihre Begründung schrumpft.** Sie stand auf zwei Beinen: Der Job
brauchte `PAKET_REPO_DEPLOY_KEY`, den ein Dependabot-PR nicht bekommt — das ist entfallen — und er
prüft bei einem Lock-Bump ohnehin nichts, was die Änderung betrifft, weil er das *veröffentlichte*
Paket bezieht. Das zweite Bein trägt allein, also bleibt die Bedingung und der Kommentar sagt,
welcher Grund weggefallen ist.

### Belegt
`tools/ci/bezugsweg-pruefen.sh` **lokal, ohne eine einzige Sondervariable**, in genau der Umgebung,
in der die Abnahme von `000-000-0086` zweimal scheiterte — `github-oauth`-Token weiterhin in
`~/.composer/auth.json`, kein `GIT_SSH_COMMAND`, kein isoliertes `COMPOSER_HOME`, Projekt
ausserhalb des Repos:

```
→ composer install gegen https://github.com/area-net-gmbh/contentfly-framework-dist.git
    Version  v2.2.1
  ✓ getaggte Version aus einem entfernten Repository
  ✓ data / errors / meta — Version 2.2.1
✓ Der Bezugsweg trägt
```

Dazu: Klonen mit `GIT_SSH_COMMAND=/bin/false` gelingt über HTTPS — also nachweislich ohne jeden
Schlüssel. Unit-Suite 327 Tests grün, PHPStan `[OK] No errors`.

### Offen — eine eigene Entscheidung
**Packagist.** Damit entfiele der `repositories`-Eintrag ganz und es bliebe
`composer require areanet/contentfly`. Öffentlichkeit war die Voraussetzung; der Schritt ist jetzt
möglich. Festgehalten in `architecture.md` als nicht verworfen, nur nicht gemacht.
