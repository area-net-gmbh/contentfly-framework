---
id: 009-002-0001
title: Das Manifest auf Symfony 7.4 umstellen
status: todo
depends_on: []
---

# Das Manifest auf Symfony 7.4 umstellen

## Context
Der erste Schnitt, und er gehört an den Anfang: Gegen Symfony-7-APIs lässt sich nicht bauen,
solange Symfony 4 installiert ist. `silex/silex` 2.3.0 fordert `symfony/http-kernel`,
`-foundation`, `-routing` und `-event-dispatcher` jeweils auf `^4.0`;
`knplabs/console-service-provider` deckelt `symfony/console` auf `^4`. Beide müssen weg, bevor
irgendeine neue Zeile geschrieben werden kann.

**Nach diesem Task bootet der Baum nicht.** Das ist kein Versehen, sondern die Form des
Schnitts — siehe den Kopf der Story. Bis `009-002-0006` prüfen die Tasks mit Unit-Tests,
`php -l` und gezielten Proben; die volle Suite ist die Abnahme des letzten.

## Zwei Pakete gehen ersatzlos
`symfony/validator` wird in `bootstrap.php` registriert, aber `$app['validator']` liest im
ganzen Baum **niemand**. `symfony/translation` steht im Manifest und wird nirgends benutzt —
nicht einmal registriert. Beide werden gestrichen statt auf 7.4 gehoben: weniger
Abhängigkeiten heisst weniger CVE-Fläche und ein kleinerer Deployment-Baum. Ein Projekt, das
sie braucht, fordert sie in seinem eigenen Manifest an. Entschieden am 2026-09-09.

## Acceptance criteria
- [ ] `composer.json` fordert die Symfony-7.4-Komponenten an, die der Kernel braucht, jeweils
      auf `^7.4`; `silex/silex` und `knplabs/console-service-provider` sind entfernt.
- [ ] `symfony/validator` und `symfony/translation` sind entfernt, und der Nachweis ihrer
      Ungenutztheit steht im Ergebnis — nicht die Behauptung.
- [ ] `composer update` läuft durch; im Lock steht **keine** Symfony-4-Komponente mehr und kein
      `pimple/pimple`.
- [ ] `composer audit --locked` ist sauber, oder jede verbleibende Ausnahme steht mit Grund in
      `config.audit.ignore` — die Liste aus `006-005-0001` schrumpft, weil ihre einzige Ursache
      Symfony 4.4 war.
- [ ] Die PHP-Anforderung passt: Symfony 7.4 verlangt `>= 8.2`, das Manifest steht auf `^8.3`.

## Verification
`composer update`, danach `composer audit --locked` und eine Auswertung von `composer.lock`:
Kein Paket mit `symfony/*` unter Version 7, kein `silex`, kein `pimple`, kein `knplabs`.
`php -l` über den Baum läuft weiter durch — mehr ist an dieser Stelle nicht zu erwarten.
