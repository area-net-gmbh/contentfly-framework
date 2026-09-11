---
id: 007-003-0003
title: Die Grenze der Zusicherung sagen
status: todo
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
- [ ] Die vier Grenzen stehen im `dev-guide.md` bei der Liste, nicht an vier Stellen.
- [ ] Die Falle mit der Closure ist ausdrücklich als solche benannt — sie steht heute nur im Container.
- [ ] `breaking-changes.md` und der Docblock von `ApplicationInterface` zeigen auf die Festlegung, statt sie zu wiederholen.
- [ ] Ein Test hält fest, was der Container bei einem unbekannten Schlüssel tut — das ist eine Zusicherung wie jede andere.
- [ ] Die volle Suite bleibt grün.

## Verification
Ein Leser, der die Liste aus `007-003-0002` gelesen hat, kann sagen: was er benutzen darf, was
er selbst verantwortet, und woran er sich die Finger verbrennt.
