---
id: 007-002-0005
title: Die Grenzen benennen und den Aufruf dokumentieren
status: review
depends_on: [007-002-0004]
---

# Die Grenzen benennen und den Aufruf dokumentieren

## Context
**Die Abnahme der Story verlangt es ausdrücklich:** „Was die Regel **nicht** kann, steht dabei.
Eine Automatik, deren Grenzen niemand kennt, ist gefährlicher als gar keine."

Ein eigener Task, weil das Aufschreiben sonst als letzte halbe Stunde des vorigen mitläuft — und
dann steht dort, was gerade noch erinnert wird, statt was gemessen wurde.

**Was zusammenkommt:**

- Der Aufruf, den ein Bestandsprojekt tatsächlich eintippt, mit `--dry-run` zuerst.
- Was die Regel abdeckt und was nicht — aus den vier vorigen Tasks eingesammelt, nicht neu
  erfunden.
- Was **ausserhalb** von `Entity/` zu tun bleibt: die drei Type-Klassen aus `APP_SYSTEM_TYPES`
  bzw. `APP_CUSTOM_TYPES` (`RteType`, `PasswordType`, `EntitySelectorType`), die entfallene
  Plugin-Schnittstelle und die `FRONTEND_*`-Konfiguration. Die Regel läuft über Entities; alles
  andere ist Handarbeit und muss benannt sein, sonst hält ein Projekt den Lauf für vollständig.
- Der Hinweis, dass ein Lauf gegen ein Verzeichnis mit bereits umgestellten Entities **nichts**
  tun darf — das ist zugleich die Probe, ob jemand die Regel versehentlich zu breit gefasst hat.

**Wohin es gehört:** `an_project/docs/breaking-changes.md` trägt schon den Abschnitt
*Paketgrenze* aus `007-001`; die Annotationen haben ihren eigenen Platz in
`pim-annotationen-migration.md`. Der Leitfaden aus `007-004` sammelt beides ein — dieser Task
liefert ihm den Text, er schreibt ihn nicht zweimal.

## Acceptance criteria
- [x] Der Aufruf steht als Befehl da, den man kopieren kann, mit `--dry-run` als erstem Schritt.
- [x] Was die Regel abdeckt und was nicht, steht vollständig — je Punkt aus einem der vier vorigen Tasks belegt.
- [x] Was ausserhalb von `Entity/` von Hand zu tun bleibt, ist aufgeführt; ein Projekt kann den Lauf nicht für vollständig halten.
- [x] `007-004` kann den Text übernehmen, ohne ihn neu zu schreiben — er steht an einer Stelle, nicht an drei.
- [x] Die volle Suite bleibt grün.

## Verification
Ein Leser, der Contentfly kennt und diese Story nicht verfolgt hat, kommt mit dem Text durch
einen Lauf — und weiss danach, was er noch selbst anfassen muss.

## Ergebnis

**Alles steht in `an_project/docs/pim-annotationen-migration.md`, Abschnitt 7** — und nur dort.
Die Datei war ohnehin die Quelle der Listen, aus denen die Regel gebaut ist; zwei Beschreibungen
desselben Laufs liefen auseinander. `breaking-changes.md` und `rector.php` verweisen darauf,
statt es zu wiederholen, und der Kopf der Datei sagt jetzt, dass die Regel gebaut ist.

**Der Aufruf steht als vier Zeilen da, die man kopieren kann** — Trockenlauf, zwei Läufe,
Trockenlauf. Die letzte ist die Abnahme: Solange sie noch etwas vorschlägt, ist die Migration
nicht fertig.

**Was die Regel abdeckt, steht als Tabelle mit Beleg je Zeile** — vier Punkte, jeder mit dem
Task, der ihn gemessen hat. Dazu drei Eigenschaften, die keine Zeile der Tabelle sind und
trotzdem zählen: Sie ist unabhängig vom Alias, sie räumt keine Importe um, und ein zweiter Lauf
gegen bereits umgestellte Entities tut nichts.

**Was sie nicht abdeckt, steht in zwei getrennten Listen, und die Trennung ist die Aussage:**

- **Handarbeit** — vier Punkte, allen voran die drei Type-Klassen in `custom/config.php`. Ein
  Projekt, das eine davon stehen lässt, bricht beim Start mit
  `contentfly_type_class_not_found` ab. Die Regel läuft über `Entity/` und kommt dort nie
  vorbei; sie zu erweitern hiesse, die Konfiguration eines Projekts umzuschreiben.
- **Absichtlich nicht** — drei Punkte: `targetEntity` bleibt eine Zeichenkette, die Reihenfolge
  der Attribute ist Rectors, und Kommentare bleiben stehen. **Das ist die Liste, die eine
  Automatik gefährlich macht, wenn sie fehlt:** Wer nicht weiss, dass etwas absichtlich
  unangetastet bleibt, hält es für einen Fehler und „korrigiert" es.

**Und drei Schritte, wie man prüft, dass es geklappt hat** — der letzte Trockenlauf, der Start
der Anwendung, und ein Blick ins Schema. Der zweite ist der wichtigste: Ein Attribut mit einem
Feld, das der Konstruktor nicht kennt, ist syntaktisch einwandfrei und wirft erst beim Laden.

## Eine Zahl richtiggestellt

`007-002-0004` gab die Suite mit `513 tests` an. Ich hatte gemessen, **bevor** ich den
Instanziierungs-Test ergänzt habe, der zu genau diesem Task gehört — es sind 514. Im Task und
im Changelog korrigiert, sichtbar und mit dem Grund. Eine Zahl, die nicht mehr stimmt, ist
derselbe Defekt wie ein Kommentar, der nicht mehr stimmt.

**Zahlen:** Volle Suite `OK (514 tests, 1610 assertions)`, 0 Deprecations bei 0 Ausnahmen,
0 Byte Postausgang. PHPStan `[OK] No errors`.
