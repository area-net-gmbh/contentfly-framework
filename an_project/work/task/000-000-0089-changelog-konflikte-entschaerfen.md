---
id: 000-000-0089
title: Merge-Konflikte in der Buchführung entschärfen — CHANGELOG per union mergen, Versandfalle ignorieren
status: done
depends_on: []
---

# Merge-Konflikte in der Buchführung entschärfen — CHANGELOG per union mergen, Versandfalle ignorieren

## Context
**Dreimal hat eine Konfliktauflösung die Buchführung beschädigt:**
- Beim Merge von `0068` ging der Registereintrag aus `0067` verloren (2026-09-21).
- Bei #43 und #46 gingen die Einträge aus `0079`, `0080` und `0081` verloren (2026-09-22).
- Bei #52 liess die Auflösung im GitHub-Web-Editor die Registerzahl in `migration.md` auf 135
  stehen, obwohl es 136 waren. `MigrationGuideTest` wurde rot und mit ihm die CI (2026-09-23).

**Die Konflikte im `CHANGELOG.md` sind immer von derselben Art:** Zwei Branches hängen im selben
Tagesabschnitt Zeilen an dieselbe Stelle, und die richtige Auflösung ist jedes Mal „beide
behalten“. Zuletzt waren es drei in Folge: der Merge von `master` in #52 (2026-09-23), dann #51
und der `0086`-Abschluss (2026-09-24). Gits eingebauter Merge-Treiber `union` macht genau das, ohne Rückfrage.

**Nebenbei:** `.ci-mailtrap/` bleibt nach einem lokalen Lauf der Integration-Suite unversioniert im
Projektwurzelverzeichnis liegen. Die Pipeline setzt `CONTENTFLY_TEST_MAIL_TRAP` auf
`${{ github.workspace }}/.ci-mailtrap` (`pipeline.yml`). `tests/README.md` nimmt lokal
`/tmp/contentfly-mailtrap`. Der Ordner entsteht also nur, wenn jemand den CI-Pfad lokal nachstellt.
Ein Eintrag in `.gitignore` verhindert, dass er einmal mitcommittet wird.

## Was zu klären ist
- **Ob GitHub die Regel beachtet.** Lokale Merges tun es. Ob der Merge eines Pull Requests und
  „Update branch“ im Web sie auch beachten, ist nicht belegt. Das wird mit einem Probe-PR geprüft,
  nicht vermutet.
- **Nur für den `CHANGELOG.md`.** Nicht für `breaking-changes.md`: Dessen Einträge sind mehrzeilige
  Abschnitte, und `union` würde sie ineinanderschieben. Nicht für `migration.md`: Die Zahl dort ist
  abgeleitet, und `MigrationGuideTest` wacht über sie.
- **Was `union` in Kauf nimmt:** Die Zeilen kommen in der Reihenfolge „unsere, dann ihre“, nicht
  zeitlich. Fügen beide Seiten dieselbe Zeile an, steht sie zweimal da. Beides ist im Changelog
  sichtbar und harmlos, eine verlorene Zeile ist es nicht.

## Acceptance criteria
- [x] `.gitattributes` enthält `an_project/CHANGELOG.md merge=union`, mit Begründung als Kommentar.
- [x] Belegt mit einem lokalen Merge zweier Branches, die im selben Tagesabschnitt Zeilen anhängen: kein Konflikt, beide Zeilen vorhanden.
- [x] Geprüft und in `an_project/docs/git.md` festgehalten, ob GitHub die Regel beim Merge eines Pull Requests beachtet.
- [x] In `an_project/docs/git.md` steht die Regel: Konflikte in `CHANGELOG.md`, `breaking-changes.md` und `migration.md` werden lokal aufgelöst und mit der Unit-Suite geprüft, nicht im Web-Editor.
- [x] `/.ci-mailtrap/` steht in `.gitignore`.

## Verification
- `git check-attr merge an_project/CHANGELOG.md` meldet `union`.
- Probe-Merge wie oben.
- Nach einem lokalen Lauf der Integration-Suite mit dem CI-Pfad zeigt `git status` keinen unversionierten Ordner.

## Ergebnis (2026-09-24)
**Der CHANGELOG mergt per `union`, die Regel für die Buchführung steht in `git.md`, die
Versandfalle ist ignoriert.**

- `.gitattributes` (neu): `an_project/CHANGELOG.md merge=union`, mit Begründung und der Grenze zu
  Register und Leitfaden.
- `an_project/docs/git.md`: neuer Abschnitt *Konflikte in der Buchführung*, dazu eine Zeile unter
  *Deviations from the baseline*.
- `.gitignore`: `/.ci-mailtrap/`.

### Belegt
In einem Wegwerf-Worktree mit dem Stand dieses Branches:
- Zwei Branches hängen je eine Zeile im selben Tagesabschnitt an: **kein Konflikt**, beide Zeilen
  da. **Gegenprobe** mit neutralisiertem Attribut (`!merge`): derselbe Merge ergibt den Konflikt.
- Der echte Branch `security/000-000-0088-verdeckte-gruppenrechte` in diesen Stand: **kein
  Konflikt**. Beide legen einen Abschnitt `2026-09-24` an; er steht danach einmal da, mit den
  Zeilen beider Tasks.
- `git check-attr merge` meldet `union` für den CHANGELOG und `unspecified` für
  `breaking-changes.md`.
- `git check-ignore` greift für beide Dateien der Versandfalle; `git status` zeigt sie nicht mehr.
- Unit-Suite 327 grün.

### Offen: GitHub
**Ob GitHub `union` beim Merge eines Pull Requests beachtet, ist nicht belegt.** Das Kriterium
bleibt deshalb offen. Belegen lässt es sich erst, wenn das Attribut auf GitHub liegt: am
Merge-Stand des PR von `0088` gegen einen `master`, der `0089` schon enthält. Beide Branches legen
einen Abschnitt `2026-09-24` an. Zeigt GitHub dort keinen Konflikt, beachtet es die Regel. Das
Ergebnis kommt in `git.md`; bis dahin gilt die Regel „lokal auflösen“ ohne Ausnahme.

### GitHub — belegt (2026-09-25)
**GitHub beachtet `union`.** Der Probe-PR war nicht nötig. Die Historie seit #54 enthält den Fall
bereits.

Die sechs Merges, die GitHub seit #54 erzeugt hat, habe ich lokal nachgerechnet, jeweils ohne das
Attribut (`an_project/CHANGELOG.md !merge` in `.git/info/attributes` eines Wegwerf-Klons):

| Merge | ohne `union` | auf GitHub |
|---|---|---|
| `9ee7e92d` (#55), `71990b72` (#56), `bb1b5a2a` (#57), `b0d10035` (#58), `1ccf8e63` (#59) | kein Konflikt | kein Konflikt |
| `aad3fae7` — *Update branch* auf #59 | **Konflikt im `CHANGELOG.md`** | **kein Konflikt** |

`aad3fae7` hat **GitHub selbst erzeugt**: Committer `GitHub <noreply@github.com>`, web-flow-signiert.
Mit dem Attribut ergibt derselbe Merge lokal keinen Konflikt, und sein `CHANGELOG.md` ist
**byte-gleich** mit dem von GitHub. Beide Seiten hatten im Abschnitt `2026-09-24` Zeilen angehängt,
genau der Fall, für den die Regel gemacht ist.

**Der Fall `0088`, mit dem der Beleg geplant war, taugte nicht dazu.** #55 kommt ohne das Attribut
konfliktfrei durch. Die beiden Branches berührten den `CHANGELOG.md` also nicht an derselben Stelle.

**Die Grenze des Belegs:** Belegt ist *Update branch*. Für den Merge-Knopf eines Pull Requests gibt
es seit #54 keinen Fall mit Konflikt. Beide führen den Merge auf GitHubs Servern aus. Auch die Anzeige
„This branch has conflicts“, die einen Merge sperrt, stammt von dort. Ein Konflikt, den *Update branch*
auflöst, blockiert den Merge-Knopf deshalb nicht.

### Korrektur (2026-09-25, `000-000-0099`)
**Der letzte Absatz oben schliesst zu weit.** Belegt war, dass *Update branch* `union` beachtet — nicht,
dass der Merge-Button es tut. #65 hat das Gegenteil gezeigt: Derselbe CHANGELOG-Konflikt, den
*Update branch* ohne Rückfrage auflöst, sperrt den Merge-Button (`mergeable_state: dirty`), und die CI
startet nicht. `git.md` ist korrigiert und sagt, was dann zu tun ist.
