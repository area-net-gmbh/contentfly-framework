---
id: 006-005-0000
title: composer audit als CI-Gate
status: done
depends_on: [006-003-0000, 006-004-0000]
---

# composer audit als CI-Gate

## Goal
Ein Manifest, das niemand prüft, verfällt genauso still wie der eingefrorene Vendor-Baum, den es
ersetzt. Diese Story verankert `composer audit --locked` als Gate in der CI — es ist der Grund,
aus dem sich der ganze Aufwand von Epic `006` später auszahlt.

## Umfang

### A — Das Gate
`composer audit --locked` prüft den committeten Lock gegen die Advisory-Datenbank. `--locked`
statt eines Laufs gegen `vendor/` ist wesentlich: Es funktioniert ohne installierte
Abhängigkeiten und prüft genau das, was auch deployt wird.

Das Gate bricht den Lauf ab, statt zu warnen. Ein Sicherheitshinweis, den man wegklicken kann,
ist keiner.

### B — Warum das erst hier kommt
Auf dem heutigen Stand wäre das Gate sinnlos: Der Root-Baum hat keinen Lock, und die Alt-Pakete
sind durchgehend EOL — Silex 2.2.2, Symfony 3.4, Doctrine ORM auf einem Dev-Branch. Ein Audit
gegen diesen Stand meldet eine Liste, die niemand abarbeiten kann, und wird nach zwei Läufen
abgeschaltet. Nach `006-002` bis `006-004` ist der Baum dagegen auf gepflegten Releases, und die
Meldungen sind wieder handhabbar.

### C — Zusammen mit dem Deprecation-Gate
`an_project/docs/tech-stack.md` fordert ein zweites Gate: **„0 Deprecations"** aus
Deprecation-Log und PHPStan, „von Tag 1 an, nicht erst vor dem Upgrade". Beide Gates gehören in
denselben CI-Schritt und sind zusammen zu verdrahten — es ist derselbe Handgriff und dieselbe
Begründung: Der Sprung auf Symfony 8.4 LTS soll später ein reiner Constraint-Bump sein.

### D — Was zu klären ist
- **Wo die CI läuft.** Das Repo hat heute keine Pipeline-Definition. Ob GitHub Actions, GitLab CI
  oder etwas anderes, ist beim Umsetzen zu entscheiden und in `an_project/docs/deployment.md`
  festzuhalten.
- **Umgang mit einem Advisory ohne Fix.** Wenn ein Paket eine offene Meldung hat, für die es kein
  Release gibt, braucht es einen benannten Weg — `composer audit --ignore` mit Begründung und
  Ablaufdatum, nicht ein dauerhaft abgeschaltetes Gate.
- **PHP-Version im CI-Lauf.** Das Manifest löst gegen `platform.php: 8.5` auf; der CI-Container
  muss dazu passen, sonst prüft das Gate etwas anderes als das Deployment.

## Fertig, wenn
- `composer audit --locked` läuft in der CI und bricht bei einem Fund ab.
- Das Gate ist auf dem neuen Root-Manifest **sauber** — oder jede verbleibende Meldung ist
  begründet und befristet ausgenommen.
- Das „0 Deprecations"-Gate aus `tech-stack.md` läuft im selben Schritt.
- Die Pipeline-Definition liegt im Repo, und `an_project/docs/deployment.md` beschreibt, was sie
  prüft und wie man einen Fund behandelt.
- Der CI-Lauf verwendet dieselbe PHP-Version, gegen die das Manifest auflöst.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [ ] 006-005-0001 — composer audit als blockierendes Gate mit begründeter Ausnahmeliste
- [ ] 006-005-0002 — Eine Ausnahme, die nicht mehr greift, macht den Lauf rot
- [ ] 006-005-0003 — Das „0 Deprecations"-Gate aus dem Laufzeit-Log
- [ ] 006-005-0004 — PHPStan-Grundgerüst mit Deprecation-Regeln, zunächst nicht blockierend
- [ ] 006-005-0005 — deployment.md beschreibt die Gates und den Umgang mit einem Fund
