---
id: 009-002-0006
title: Die Einstiegspunkte und der Nachweis
status: todo
depends_on: [009-002-0003, 009-002-0004, 009-002-0005]
---

# Die Einstiegspunkte und der Nachweis

## Context
Der letzte Task der Story, und der einzige, an dem die volle Suite wieder läuft. Bis hierher war
der Baum nicht lauffähig — das ist die Form des Schnitts, nicht sein Fehler.

Zusammenzuführen ist, was die vier Tasks davor gebaut haben: `index.php`, `bootstrap.php`,
`bootstrap-web.php` und `tests/router.php`. Der Bootstrap trägt heute Dinge, die mit dem Kernel
zusammenhängen und einzeln zu prüfen sind — die `AnnotationRegistry`-Falle, der SSL-Zwang, die
Ableitung von `WEB_ROOT` und die Produktionseinstellung aus `000-000-0018`.

**Der Nachweis ist das eigentliche Ergebnis.** `an_project/docs/technical.md`:

> Der Kernel-Tausch gilt als gelungen, wenn diese Suite ohne inhaltliche Änderung grün bleibt.

Eine Erwartung, die angepasst werden muss, ist ein Befund und braucht eine Begründung — keine
Anpassung. Der Stand vor dem Schnitt: **249 Tests, 616 Assertions, 0 übersprungen.**

## Acceptance criteria
- [ ] Die vier Einstiegspunkte booten den neuen Kernel; die Reihenfolge im Bootstrap ist
      erhalten, wo sie Absicht war (`custom/app.php` vor `bindRoutes()`).
- [ ] `tests/Unit/Kernel/KeineSilexTypenTest.php` hat eine **leere** Ausnahmeliste, und die
      Gegenprüfung — eine Ausnahme, die nichts mehr abdeckt — ist damit erfüllt.
- [ ] Die Suite aus Epic `008` ist grün, ohne inhaltliche Änderung an einer Zusicherung. Muss
      eine geändert werden, steht die Begründung im Ergebnis, und zwar je Zusicherung.
- [ ] `composer audit --locked` sauber; die Ausnahmeliste aus `006-005-0001` ist leer oder ihr
      Rest ist begründet.
- [ ] Deprecation-Gate grün, und die Ausnahme für Silex ist aus
      `tools/ci/deprecations-ausnahmen.txt` verschwunden — sie hat keinen Anlass mehr.
- [ ] Die Versandfalle bleibt stumm: Postausgang 0 Byte.

## Verification
Volle Suite gegen eine frisch installierte Wegwerf-Datenbank, `composer audit --locked`,
`sh tools/ci/deprecations-pruefen.sh` und `sh tools/ci/audit-ausnahmen-pruefen.sh`. Dazu ein
Durchgang durch `an_project/docs/runbook.md` von einem frischen Klon aus — die Anleitung war
schon einmal nicht durchführbar (`006-003-0003`), und ein Kernel-Wechsel ist der wahrscheinlichste
Anlass, dass sie es wieder wird.
