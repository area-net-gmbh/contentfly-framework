---
id: 000-000-0092
title: Die falsche Kennung sim <sim@local> per .mailmap der richtigen zuordnen
status: review
depends_on: []
---

# Die falsche Kennung sim <sim@local> per .mailmap der richtigen zuordnen

## Context
**Aus `000-000-0091`.** Sieben Commits auf `master` und der Tagger von `v2.3.0` tragen
`sim <sim@local>` statt `areanet_foschmid <fs@area-net.de>`: `675cd653`, `70c74c84`, `b165a058`,
`b19cf675`, `07cc80b6`, `02bb0895`, `b53aa084`. Eine Merge-Simulation in einem Wegwerf-Worktree
setzte `user.name`/`user.email` per `git config` — in einem Worktree schreibt das in die gemeinsame
Konfiguration des Repos. Die Konfiguration ist bereinigt.

**Umschreiben geht nicht:** `master` ist geschützt, Force-Push verboten (`git.md`). Den Tag neu zu
setzen hiesse, einen veröffentlichten Tag zu löschen und die Veröffentlichung ein zweites Mal
auszulösen. Was bleibt, ist die Zuordnung: `.mailmap` sagt Git, welche Kennung zu welcher Person
gehört.

## Was `.mailmap` erreicht und was nicht
- **Erreicht:** `git shortlog` und `git blame` benutzen sie von sich aus, `git log` mit
  `--use-mailmap` oder `log.mailmap = true`. Ab Git 2.29 ist `log.mailmap` von sich aus an; lokal
  läuft hier Git 2.21, dort muss man es einschalten.
- **Nicht erreicht:** die Oberfläche von GitHub. Sie ordnet Commits über die E-Mail-Adresse einem
  Konto zu, `sim@local` gehört keinem — dort bleibt „sim" stehen.
- **Zu prüfen:** ob Git den Tagger eines annotierten Tags über `.mailmap` umschreibt, und mit
  welchem Befehl.

## Acceptance criteria
- [x] `.mailmap` im Wurzelverzeichnis ordnet `sim <sim@local>` der Kennung
      `areanet_foschmid <fs@area-net.de>` zu, mit einem Kommentar, warum der Eintrag existiert.
- [x] `git shortlog -se origin/master` zeigt keinen Eintrag `sim` mehr; `git log --use-mailmap`
      zeigt die sieben Commits unter der richtigen Kennung.
- [x] Festgehalten, was `.mailmap` nicht erreicht: die GitHub-Oberfläche und — je nach Ergebnis der
      Prüfung — der Tagger von `v2.3.0`.

## Verification
`git shortlog -se origin/master` vorher und nachher; `git log --use-mailmap --format='%h %an <%ae>'`
für die sieben Commits; für den Tag `git for-each-ref refs/tags/v2.3.0` mit dem passenden Format.

## Ergebnis (2026-09-24)
**`.mailmap` ordnet `sim <sim@local>` der Kennung `areanet_foschmid <fs@area-net.de>` zu** — nur
diese eine Kennung, mit Begründung als Kommentar.

### Belegt, Git 2.21
| | vorher | nachher |
|---|---|---|
| `git shortlog -se origin/master` | `7 sim <sim@local>`, `648 areanet_foschmid` | kein `sim`, `655 areanet_foschmid` |
| `git log --use-mailmap --format='%aN <%aE>'` für die sieben Commits | — | alle `areanet_foschmid <fs@area-net.de>`, Autor und Committer |
| `git log` ohne Schalter | `Author: sim <sim@local>` | unverändert — Git 2.21 braucht `--use-mailmap` oder `log.mailmap = true` |
| `git blame` | — | `areanet_foschmid <fs@area-net.de>` |

**Eine Falle beim Prüfen:** `%an`/`%ae` zeigen immer den rohen Wert, auch mit `--use-mailmap`. Die
zugeordneten Platzhalter heissen `%aN`/`%aE` (und `%cN`/`%cE`).

### Was `.mailmap` nicht erreicht
- **Den Tagger von `v2.3.0`.** `git show --use-mailmap v2.3.0` zeigt mit Git 2.21 weiter
  `Tagger: sim <sim@local>`; `git for-each-ref` ebenso. Neuere Git-Versionen kennen
  `%(taggername:mailmap)` — hier nicht prüfbar.
- **Die GitHub-Oberfläche.** Sie ordnet Commits über die E-Mail-Adresse einem Konto zu; `sim@local`
  gehört keinem.

### Nebenbefund, nicht Teil dieses Tasks
Dieselbe Adresse `fs@area-net.de` läuft unter zwei Schreibweisen: `areanet-foschmid` (69 Commits,
die Merges über GitHub) und `areanet_foschmid` (lokal). `.mailmap` könnte beide zusammenführen —
welche die richtige ist, ist eine Entscheidung, keine Korrektur.
