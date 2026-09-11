---
id: 007-001-0000
title: Das Framework als Composer-Paket beziehbar machen
status: todo
depends_on: []
---

# Das Framework als Composer-Paket beziehbar machen

## Goal
Ein Bestandsprojekt bezieht das Framework über `composer require` statt über einen kopierten
`lib/`-Baum. **Das ist die Vorbedingung dafür, dass es überhaupt updaten kann** — wer den
Frameworkcode in seinem eigenen Repo liegen hat, bekommt eine neue Version nur durch
Hineinkopieren und verliert dabei jede Änderung, die er selbst vorgenommen hat.

Mit derselben Arbeit wird die Grenze zwischen Framework und Projekt gezogen, und `custom/` zeigt
danach den Zielzustand: so sieht ein Projekt auf der neuen Version aus.

## Ausgangslage

Nachgesehen am 2026-09-11 in `composer.json`:

| | Ist-Stand |
|---|---|
| `name` | `areanet/contentfly-framework` |
| `type` | `project` — **kein `library`** |
| `autoload` | `Areanet\PIM\` → `lib/contentfly/` · `Custom\` → `custom/` · `Plugins\` → `plugins/` |

Ein Manifest vom Typ `project` beschreibt eine Anwendung, die man klont, kein Paket, das man
einbindet. Alle drei Namensräume — Framework, Projektcode und Plugins — hängen an **einem**
Manifest; das ist genau die Vermischung, die das Update unmöglich macht.

## Zu entscheiden

- **Ein Paket oder zwei?** Ein Bibliothekspaket allein, oder zusätzlich ein Skeleton, mit dem ein
  neues Projekt anfängt. Beides ist üblich, und die Wahl bestimmt, wie viel ein Bestandsprojekt
  von Hand nachbauen muss.
- **Was ist Framework, was ist Projekt?** `index.php`, `bin/console.php`, `custom/`, `plugins/`,
  `data/`, `tools/` und `tests/` müssen einzeln zugeordnet werden. Die Trennlinie ist die
  eigentliche Arbeit dieser Story, nicht das Umstellen des Manifests.
- **Wie findet das Paket die Konfiguration des Projekts?** `custom/config.php` liegt heute im
  selben Baum. Liegt es künftig im Projekt, muss das Paket es suchen statt annehmen.
- **Wie überschreibt ein Projekt etwas?** Heute durch Danebenlegen im selben Baum. Das geht nicht
  mehr, sobald der Frameworkcode in `vendor/` liegt und bei jedem Update überschrieben wird.

## Abnahme

Eine frische Installation entsteht aus dem Paket, nicht aus einer Kopie, und die volle Suite
läuft gegen sie. Was ein Bestandsprojekt zu tun hat, um von Kopie auf Paket zu wechseln, ist
beschrieben — der Text davon geht in den Leitfaden aus `007-004`.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [ ] 007-001-0001 — Die Trennlinie zwischen Framework und Projekt ziehen und festschreiben
- [ ] 007-001-0002 — ROOT_DIR aufgeben — das Projektverzeichnis wird übergeben, nicht geraten
- [ ] 007-001-0003 — Der Einstiegspunkt lädt den Autoloader, nicht das Framework
- [ ] 007-001-0004 — Das Manifest teilen — Bibliothekspaket und Projekt getrennt
- [ ] 007-001-0005 — Eine Installation aus dem Paket bauen und die Vorlage nachziehen
