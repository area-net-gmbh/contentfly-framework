---
id: 011-002-0003
title: Das Paket per Subtree-Split ausliefern
status: todo
depends_on: [011-002-0002]
---

# Das Paket per Subtree-Split ausliefern

## Context
Composer liest die `composer.json` aus der **Wurzel** eines Repositories; unsere liegt in
`lib/contentfly`, die Wurzel trägt das Skeleton (`areanet/contentfly-skeleton`). Ein Projekt kann
`areanet/contentfly` deshalb heute nicht beziehen — beim UFP-Probelauf (`007-005-0003`) brauchte
dessen `composer.json` ein `path`-Repository auf einen absoluten Pfad, den es nur im Container gab.

**Das Split-Repository ist keine zweite Quelle, sondern ein Erzeugnis.** Niemand schreibt dort von
Hand hinein; es entsteht bei jedem Tag neu aus `lib/contentfly`. Die Alternative — den
Framework-Baum in die Repo-Wurzel ziehen — kippt die Zwei-Manifest-Entscheidung aus `007-001-0004`
und ist ein eigenes Vorhaben.

**Für Bestandsprojekte ist das der eigentliche Gewinn:** Sie tragen einmal eine URL ein und fassen
den Framework-Baum nie wieder an. Neue Version = neuer Tag = `composer update areanet/contentfly`.

## Acceptance criteria
- [ ] Ein Tag `v*` auf dem Hauptrepo erzeugt im Paket-Repository denselben Tag mit dem Inhalt von `lib/contentfly` in der Wurzel.
- [ ] Tag, `version` in `lib/contentfly/composer.json` und `APP_VERSION` in `version.php` werden gegeneinander geprüft; eine Abweichung bricht ab, statt eine falsche Version zu veröffentlichen.
- [ ] Der Split enthält **nur** das Paket — kein `tests/`, kein `an_project/`, kein `tools/`, keine Gate-Konfiguration.
- [ ] Ein Projekt kann `{"type":"vcs","url":"…"}` plus `"areanet/contentfly": "^2.0"` schreiben und bekommt die getaggte Version, nicht `dev-master`.
- [ ] Der Weg steht in `deployment.md`: Wie eine Version herauskommt und was dabei schiefgehen kann.

## Verification
Testtag auf einem Nebenzweig setzen, den Lauf beobachten, das Paket-Repo prüfen: richtiger Inhalt
in der Wurzel, richtiger Tag. Danach in einem leeren Verzeichnis ein `composer require
areanet/contentfly:^2.0` gegen dieses Repo — und der Testtag wird wieder entfernt.
