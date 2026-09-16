---
id: 011-002-0000
title: Das Framework aus Projektsicht beziehbar machen
status: todo
depends_on: []
---

# Das Framework aus Projektsicht beziehbar machen

## Goal
**Ein Projekt kann `areanet/contentfly` installieren, ohne dass jemand einen Pfad auf dieser
Maschine kennt.** Epic `007` hat den Bezugsweg entschieden (Composer-Paket statt kopiertem
`lib/`-Baum) und ihn an der Vorlage durchgespielt. Was fehlt, ist der Weg von aussen.

**Der Beleg, dass das noch nicht trägt, liegt vor:** Beim Probelauf am Bestandsprojekt UFP
(`007-005-0003`) musste dessen `composer.json` ein `path`-Repository auf
`/contentfly-framework/lib/contentfly` bekommen — ein absoluter Pfad, den es nur im
Probe-Container gibt. Auf einem Entwicklerrechner scheitert `composer install`. Genau deshalb
ist die UFP-Migration bis zu diesem Epic vertagt.

## Entschieden am 2026-09-16

**Eine Quelle, und die liegt auf GitHub.** Das Repository zieht von
`gitlab.in.area-net.de` nach `github.com/area-net-gmbh/contentfly-framework` (privat,
Company-Account); das GitLab-Repository wird danach abgeschaltet.

**Warum nicht das interne GitLab, und warum nicht beides:** Contentfly muss künftig einer externen
Corporation für eine Security- oder Codebase-Prüfung zugänglich gemacht werden können — ein Zugang,
der sich vergeben und wieder entziehen lässt. Ein internes GitLab kann das nur über VPN-Konten oder
Netzwerkausnahmen. Zwei Remotes parallel wären billig gewesen (ein Push-Ziel mehr), aber genau eines
muss kanonisch sein: Zögen zwei Entwickler dasselbe Paket aus verschiedenen Quellen, trügen ihre
`composer.lock` unterschiedliche `source.url`. Also eines, nicht zwei.

**Der Bezugsweg ist ein `vcs`-Repository auf ein Split-Repo, keine Registry.** Geprüft: GitHub
Packages unterstützt npm, RubyGems, Maven, Gradle, NuGet und Docker — **kein Composer**. Die
GitLab-Registry könnte es, verlangt aber „a valid `composer.json` file at the project root
directory" und fällt mit dem GitLab weg. Composers GitHub-Treiber liest die `composer.json` je Tag
über die API und lädt ein Zipball, statt zu klonen; auf der Projektseite steht schlicht
`{"type":"vcs", …}` plus `"areanet/contentfly": "^2.0"`.

**Das Split-Repo ist keine zweite Quelle, sondern ein Erzeugnis.** Composer liest die
`composer.json` aus der *Wurzel*; unsere liegt in `lib/contentfly`. In das Split-Repo schreibt
niemand von Hand — es entsteht bei jedem Tag neu. Die Alternative, den Framework-Baum in die
Repo-Wurzel zu ziehen, kippt die Zwei-Manifest-Entscheidung aus `007-001-0004` und ist ein eigenes
Vorhaben.

**Belegt wird der Weg doppelt:** einmal von Hand von aussen gefahren und im Runbook beschrieben,
und als Gate, das ihn bei jedem CI-Lauf wiederholt. Ein Weg, den niemand fährt, verrottet.

**Was der Umzug mitbringt** und deshalb in dieser Story steckt, obwohl es nicht nach „beziehbar aus
Projektsicht" klingt: Die Pipeline muss von GitLab CI auf GitHub Actions, und 133 Erwähnungen des
alten Hosts im Baum ziehen mit. Ohne beides wird die Story nicht fertig.

**Fertig, wenn** ein Projekt ohne Kenntnis dieses Arbeitsverzeichnisses installiert werden kann
und der Weg im Runbook steht.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. Geschnitten beim Start der Story. -->
- [x] 011-002-0001 — Das Repository nach GitHub umziehen
- [ ] 011-002-0002 — Die Pipeline auf GitHub Actions bringen
- [ ] 011-002-0003 — Das Paket per Subtree-Split ausliefern
- [ ] 011-002-0004 — Den Weg von aussen fahren, ins Runbook schreiben und als Gate festnageln
