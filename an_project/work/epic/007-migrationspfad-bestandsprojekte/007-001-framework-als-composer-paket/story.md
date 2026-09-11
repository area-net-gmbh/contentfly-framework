---
id: 007-001-0000
title: Das Framework als Composer-Paket beziehbar machen
status: done
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
- [x] 007-001-0001 — Die Trennlinie zwischen Framework und Projekt ziehen und festschreiben
- [x] 007-001-0002 — ROOT_DIR aufgeben — das Projektverzeichnis wird übergeben, nicht geraten
- [x] 007-001-0003 — Der Einstiegspunkt lädt den Autoloader, nicht das Framework
- [x] 007-001-0004 — Das Manifest teilen — Bibliothekspaket und Projekt getrennt
- [x] 007-001-0005 — Eine Installation aus dem Paket bauen und die Vorlage nachziehen

## Ergebnis

**Ein Bestandsprojekt kann das Framework jetzt beziehen statt es zu kopieren.** Nachgewiesen,
nicht behauptet: In einem leeren Verzeichnis steht ein Projekt ohne eine Zeile Frameworkcode, es
holt `areanet/contentfly` über Composer als echte Kopie, `appcms:install` läuft, und die volle
Suite ist dagegen grün.

**Die Sperre war nicht das Manifest.** Sie waren zwei, und beide sassen tiefer:

1. **`ROOT_DIR`** rechnete das Projektverzeichnis aus der Lage des Frameworks. 80 Vorkommen in
   18 Dateien, 40 davon in der Suite. Heute wird es übergeben; `Pfade` weist einen fehlenden
   oder falschen Wert ab, statt einen zu erfinden.
2. **Die umgekehrte Zuständigkeit.** Der Bootstrap lud den Autoloader, den man gebraucht hätte,
   um ihn zu finden. Heute lädt ihn der Einstiegspunkt und ruft `Start`; in keinem
   Einstiegspunkt steht mehr ein Pfad in den Frameworkcode.

Erst danach war das Manifest eine Formalität.

**Vier Befunde sind erst beim Bauen aufgetaucht, und drei davon waren still:**

- **Das Framework verwies auf zwei Projektklassen, die es nicht gibt** — tote Importe, die PHP
  nie auswertet. Hätte auch nur einer etwas getroffen, wäre die Grenze nicht zu ziehen gewesen,
  ohne vorher eine Abhängigkeit umzudrehen.
- **Jedes Plugin bringt seinen eigenen Composer-Baum mit.** Die Entscheidung sprach von „einem
  Baum je Projekt" und hatte das nicht bedacht. Der Baum bleibt, mit ausgesprochenem Preis — und
  ungeprüft, weil `plugins/` leer ist.
- **`doctrine/persistence` sprang beim Neuauflösen ungefragt auf 4.2** und machte das
  Deprecation-Gate rot. Gedeckelt, nicht mitgenommen: Der Sprung verlangt eine eigene
  `ClassMetadataFactory` und ist eine eigene Aufgabe. **Ein Task dafür fehlt noch.**
- **Die Suite selbst nahm an, im selben Baum zu liegen** — 127 Tests rot beim ersten Lauf gegen
  ein fremdes Projekt, danach noch vier. Beides hängt jetzt an einer Angabe.

**Drei eigene Fehlgriffe, alle von den eigenen Prüfungen gefangen:** `fwrite(STDERR, …)` in der
Web-SAPI, ein zu grober Detektor, der die Prüfung gegen sich selbst richtete, und ein
Fehlalarm auf einen Beispieltext. Der letzte brachte nebenbei eine Variable ans Licht, die im
doppelt gequoteten String interpoliert worden wäre.

**Zahlen:** Die Suite wächst von 471 auf **495** Tests. Zwei volle Läufe grün — einer gegen das
Entwicklungs-Repo, einer gegen die Paketinstallation im Fremdprojekt —, je 0 Deprecations bei 0
Ausnahmen und 0 Byte Postausgang. PHPStan `[OK] No errors`. `composer validate` auf beiden
Manifesten, `composer install` von Null im `php:8.3-cli`. Die Audit-Gates im CI-Image ohne
Advisories. Fünf Bruchstellen stehen in `an_project/docs/breaking-changes.md` unter
*Paketgrenze*, mit dem gemessenen Fünf-Schritte-Ablauf für `007-004`.
