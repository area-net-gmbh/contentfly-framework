---
id: 007-003-0003
title: Die Grenze der Zusicherung sagen
status: review
depends_on: [007-003-0002]
---

# Die Grenze der Zusicherung sagen

## Context
**Der Task, der die Liste erst brauchbar macht.** Eine Liste sagt, worauf man sich verlassen
darf. Was sie nicht sagt, ist, was *ausserhalb* davon gilt — und wer das nicht weiss, verlässt
sich auf mehr, als zugesichert ist.

**Vier Dinge gehören benannt, und drei davon stehen heute nur im Code:**

1. **Was ein Projekt selbst setzt, sichert niemand zu.** `$app['meine.service'] = …` ist
   erlaubt und bleibt es — aber es ist Sache des Projekts, nicht des Frameworks. Die Vorlage
   zeigt es vor.
2. **Ein unbekannter Schlüssel wirft.** `Container::offsetGet()` meldet
   `Der Container kennt "x" nicht.` Das ist gut so und gehört gesagt: Ein Projekt kann sich
   darauf verlassen, dass ein Tippfehler laut auffällt und nicht `null` liefert.
3. **Eine Closure gilt als Factory, nicht als Wert.** Das ist Pimples Regel, und sie hat eine
   Kehrseite: Wer einen Callback ablegen will, bekommt ihn aufgelöst. Pimple hat dafür
   `protect()`, hier fehlt es — der Kommentar im Container sagt, dass im Baum niemand eine
   Closure als Wert ablegt. **Für ein Projekt ist das eine Falle**, und sie steht bisher nur
   dort.
4. **Ein einmal gelesener Dienst ist eingefroren.** Wer ihn danach überschreiben will, kommt zu
   spät — `extend()` wirft dann. Auch das ist heute nur im Code sichtbar.

**Dazu gehört das Nachziehen der bestehenden Texte:** `breaking-changes.md` nennt die Bridge
seit Epic `009` „Zugesichertes API", und der Docblock von `ApplicationInterface` beschreibt sie
als Mittel, den Silex-Zugriff zu erhalten. Beides bleibt richtig, aber beides sollte auf die
Festlegung zeigen, statt sie zu wiederholen.

## Acceptance criteria
- [x] Die vier Grenzen stehen im `dev-guide.md` bei der Liste, nicht an vier Stellen.
- [x] Die Falle mit der Closure ist ausdrücklich als solche benannt — sie steht heute nur im Container.
- [x] `breaking-changes.md` und der Docblock von `ApplicationInterface` zeigen auf die Festlegung, statt sie zu wiederholen.
- [x] Ein Test hält fest, was der Container bei einem unbekannten Schlüssel tut — das ist eine Zusicherung wie jede andere.
- [x] Die volle Suite bleibt grün.

## Verification
Ein Leser, der die Liste aus `007-003-0002` gelesen hat, kann sagen: was er benutzen darf, was
er selbst verantwortet, und woran er sich die Finger verbrennt.

## Ergebnis

**Die vier Grenzen stehen im `dev-guide.md` bei der Liste** — was ein Projekt selbst setzt,
was ein unbekannter Schlüssel tut, die Closure-Falle und das Einfrieren. Dazu eine fünfte
Gruppe, die erst beim Messen auftauchte (siehe unten).

**Geprüft waren sie schon.** `tests/Unit/Kernel/ContainerTest.php` deckt alle vier seit
`008-004` ab — unbekannter Schlüssel wirft, Closure ist Factory, `extend()` nach dem ersten
Zugriff wirft, `isset`/`unset` wirken wie erwartet. **Was fehlte, war nicht der Test, sondern
dass es jemand aufschreibt, der kein Framework-Entwickler ist.** Der Task hat deshalb keinen
neuen Test für die vier gebaut, sondern auf die vorhandenen gezeigt — ein zweiter Test für
dieselbe Zusicherung wäre Doppelung, kein Gewinn.

**`breaking-changes.md` und der Docblock von `ApplicationInterface` zeigen jetzt auf die
Festlegung**, statt sie zu wiederholen. Im Interface steht ausdrücklich, warum dort keine zweite
Liste steht: Zwei Listen laufen auseinander.

## Zwei Funde, die die Liste aus `007-003-0002` korrigieren

**1. `orm.em` war falsch einsortiert — und das ist eine Falle, keine Formalie.**

Er stand bei den dreien, die es erst nach der Installation gibt. Das stimmt nicht:
`bootstrap.php` hat einen `else`-Zweig, der ihn auf `null` setzt. Er ist also **immer da**.

Der Unterschied zählt für ein Projekt:

| | Meldung |
|---|---|
| Schlüssel fehlt | `Der Container kennt "db" nicht.` — nennt den Namen |
| Schlüssel ist `null` | `Call to a member function createQueryBuilder() on null` |

**Die zweite handelt von der Methode und nicht davon, dass nichts installiert ist.** Wer `orm.em`
vor der Installation benutzen könnte, prüft `$app['is_installed']`. Ein eigener Test hält den
Vertrag in beide Richtungen fest.

**2. Eine Gruppe, die gar keine Liste haben kann.** Jeder gemountete Controller bekommt einen
Eintrag `<präfix>.controller`; das Framework legt vier an, und ein Projekt legt für jede eigene
Route einen weiteren an — die Vorlage erzeugt `api/v1/example/.controller`. Sie sind Verdrahtung
des `ControllerResolver`, kein Dienst, den jemand liest, und ihre Namen hängen an den Routen des
Projekts.

## Und eine Korrektur an meinem eigenen Werkzeug

**`007-003-0002` hat den Quelltext geparst, obwohl der Container sich selbst aufzählen kann.**
`Container::keys()` gibt es seit `008-004`, und `ContainerTest` prüft die Methode. Mein Parser
war ein Suchausdruck über drei Dateien — funktionierend, aber die schlechtere Quelle.

**`keys()` hat den Unterschied sofort gezeigt:** Es fand `api/v1/example/.controller`, den der
Parser nie sah, weil der Eintrag zur Laufzeit entsteht. Der Quelltext bleibt daneben, aber nur
noch für eine Frage, die `keys()` nicht beantworten kann: `auth.token` wird erst gesetzt, wenn
ein Request sich ausgewiesen hat.

## Drei eigene Fehlgriffe

1. **Der Test hing daran, dass zufällig eine installierte Konfiguration danebenlag.** Er
   behauptete schlicht, `db` und `dbs` seien da — was nur stimmt, wenn `custom/config.php`
   gerade Testzugangsdaten trägt. Jetzt fragt er den Zustand und prüft **die passende Hälfte**;
   die zweite ist die eigentliche Zusicherung: Ohne Installation gibt es sie *nicht*.
2. **`istInstalliert()` verglich gegen `'1'`.** `var_export(true, true)` liefert `'true'` — der
   Test hielt damit jeden Zustand für „nicht installiert" und war im installierten Fall rot, im
   uninstallierten grün. Genau verkehrt herum.
3. **Und davor: `istInstalliert()` fragte nach `orm.em`.** Der ist wegen des `else`-Zweigs
   immer da — der Indikator hätte nie „nicht installiert" gesagt. Beide Fehlgriffe hingen an
   derselben falschen Annahme, die Fund 1 korrigiert.

**Zahlen:** Volle Suite `OK (521 tests, 1673 assertions)` (vorher 519), 0 Deprecations bei 0
Ausnahmen, 0 Byte Postausgang. PHPStan `[OK] No errors`. Der Test ist in **beiden** Zuständen
gefahren: installiert sieben Tests grün, uninstalliert grün mit zwei bewussten Übersprüngen.
