<!-- PURPOSE: Framework-Baseline für die Integration — die zwei Wege (lokaler /done-Merge oder Push + PR) und wann ein Item review-reif ist. Gelesen von /done. Branch-Schema: git-branching.md. Commit-Format: git-conventions.md. -->

# Git Integration — Framework-Baseline

Verbindliche Grundlage dafür, **wie** fertige Arbeit in den Integrationsbranch kommt, und wann
sie dafür reif ist. Gelesen von `.an_framework/commands/done.md`.

Branch-Schema und Branch-Ebene stehen in `.an_framework/docs/git-branching.md` (gelesen von
`/implement`), das Commit-Format in `.an_framework/docs/git-conventions.md` (gelesen von
`/commit`). Diese Datei wiederholt beides nicht.

**Overlay-Regel:** verengt in `an_project/docs/git.md` — dieselbe Overlay-Datei verengt alle
drei Baselines, vor allem den *Integration branch* und die Merge-Strategie. Bei Konflikt
gewinnt die Projekt-Datei.

---

## Merge / Integration (`/done`)

Es gibt **zwei** Integrationswege; das Projekt wählt einen:

- **Solo / intern — lokal über `/done`:** Steht ein Task auf `status: review`, schreibt
  `/done` den `done`-Status auf dem Task-Branch fest (über `/commit`) und führt den Branch
  dann lokal zusammen — `git merge --no-ff --no-edit` in den Integrationsbranch, **kein
  Push**. Passt für Repos ohne geschützten Branch / ohne PR-Pflicht.
- **Team — Push + PR:** Bei geschütztem Integrationsbranch **kein** `/done` — stattdessen
  den Task-Branch pushen und einen Pull/Merge Request stellen; gemergt wird über
  Review/CI. So bleibt „kein direkter Push aufs Remote" gewahrt, und der PR ersetzt das
  `/done`.

Für den `/done`-Weg gilt:

- `--no-ff` hält den Task als **eine revertierbare Einheit** (ein Merge-Commit, den man am
  Stück zurücknehmen kann). `--no-edit` nimmt die Standard-Merge-Message von git — kein
  `-m`, also kein Quoting-Risiko. Der `done`-Status ist **vor** dem Merge committet, der
  Merge trägt ihn hinein.
- Der Merge läuft **nicht** über `/commit` — dessen Preflight bricht bei `MERGE_HEAD` ab.
- Der Branch-Wechsel (`git checkout <branch>`) ist bewusst **nicht** vorab erlaubt (er
  würde auch das dateiverwerfende `git checkout -- <pfad>` zulassen) und fragt einmal nach.
- Ein Merge-Konflikt stoppt den Lauf; der Mensch löst ihn (`git merge --abort` ist seine
  Entscheidung), der Status bleibt unverändert.

---

## Pull Requests

- `master` ist jederzeit deploybar — **kein direkter Push aufs Remote**. Zwei
  Integrationswege (siehe *Merge / Integration*): lokal über `/done` (solo/intern)
  **oder** Push + PR von einem Branch nach dem Schema in
  `.an_framework/docs/git-branching.md` (Team). Im Team-Weg ersetzt der
  PR das `/done`; beides zusammen wäre doppelte Integration.
- Ein PR = ein Work-Item. PR-Titel folgt dem Commit-Format, der PR-Body enthält
  `Ref: #<EEE-SSS-TTTT>`.
- Ein PR ist erst review-fähig, wenn das Work-Item auf `status: review` steht,
  die Acceptance Criteria erfüllt und verifiziert sind und der Eintrag in
  `an_project/CHANGELOG.md` gesetzt ist.
- `wip`-Commits sind nicht merge-fähig — vor dem Merge aufräumen.

<!-- ERGÄNZUNGSBEDARF (projektspezifisch in an_project/docs/git.md festlegen):
     - Name des Integrationsbranch (master / main / develop) — die Baseline sagt `master`.
       Wird von /commit und /done gelesen: Abschnitt "Integration branch"
     - Merge-Strategie (squash / merge commit / rebase)
     - Anzahl erforderlicher Reviewer / Approvals
     - Pflicht-Checks (CI, Lint, Tests) vor dem Merge
     - Branch-Löschung nach Merge
     Die Baseline macht dazu bewusst keine Vorgabe. -->
