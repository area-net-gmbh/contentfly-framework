---
id: 009-002-0001
title: Das Manifest auf Symfony 7.4 umstellen
status: done
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
- [x] `composer.json` fordert die Symfony-7.4-Komponenten an, die der Kernel braucht, jeweils
      auf `^7.4`; `silex/silex` und `knplabs/console-service-provider` sind entfernt.
- [x] `symfony/validator` und `symfony/translation` sind entfernt, und der Nachweis ihrer
      Ungenutztheit steht im Ergebnis — nicht die Behauptung.
- [x] `composer update` läuft durch; im Lock steht **keine** Symfony-4-Komponente mehr und kein
      `pimple/pimple`.
- [x] `composer audit --locked` ist sauber, oder jede verbleibende Ausnahme steht mit Grund in
      `config.audit.ignore` — die Liste aus `006-005-0001` schrumpft, weil ihre einzige Ursache
      Symfony 4.4 war.
- [x] Die PHP-Anforderung passt: Symfony 7.4 verlangt `>= 8.2`, das Manifest steht auf `^8.3`.

## Verification
`composer update`, danach `composer audit --locked` und eine Auswertung von `composer.lock`:
Kein Paket mit `symfony/*` unter Version 7, kein `silex`, kein `pimple`, kein `knplabs`.
`php -l` über den Baum läuft weiter durch — mehr ist an dieser Stelle nicht zu erwarten.

## Ergebnis

**Silex, Pimple und knplabs sind aus dem Baum. `composer audit --locked` meldet nichts mehr —
und die Ausnahmeliste ist leer.**

| | vorher | nachher |
|---|---|---|
| Pakete gesamt | 78 | 71 |
| `symfony/*` (ohne Polyfills) | 4.4 / 5.4 gemischt | durchgehend 7.4 |
| Ignorierte CVEs in `config.audit.ignore` | 5 | **0** |
| `composer audit --locked` | 5 Meldungen, alle ignoriert | „No security vulnerability advisories found" |

Die fünf Ausnahmen aus `006-005-0001` betrafen ausnahmslos Symfony-4-Pakete ohne Fix — zwei in
`http-foundation`, zwei in `routing`, eine in `validator`. Jede trug den Vermerk „Fällt mit Epic
009". Sie sind gefallen; die Liste steht auf `{}`, nicht auf „vorerst leer".

### Angefordert wird jetzt genau das, was der Kernel braucht

`symfony/console`, `-event-dispatcher`, `-http-foundation`, `-http-kernel` und `-routing`, alle
auf `^7.4`. **Nicht** aufgenommen ist `symfony/dependency-injection`: Der Container wird nach der
Entscheidung vom 2026-09-09 ein eigener, und ein Paket anzufordern, das niemand benutzt, wäre
genau der Ballast, den dieser Task gerade abgeworfen hat. Braucht `009-002-0002` es doch, kommt
es dort dazu — mit dem Aufrufer daneben.

### Der Nachweis für die beiden gestrichenen Pakete

Nicht behauptet, sondern gezählt:

| | `symfony/validator` | `symfony/translation` |
|---|---|---|
| Container-Schlüssel gelesen | 0 | 0 |
| Namensraum im Baum | 0 Dateien | 0 Dateien |
| `@Assert`-Annotationen | 0 Dateien | — |
| `->trans(`-Aufrufe | — | 0 |
| Von einem anderen Paket angefordert | nein | nein |

Der `ValidatorServiceProvider` wurde in `bootstrap.php` registriert, und `$app['validator']` hat
ihn nie jemand abgeholt. `symfony/translation` stand nur im Manifest.

### Was jetzt gilt

**Der Baum bootet nicht.** `php bin/console.php list` stirbt an
`Class "Silex\Application" not found` in `Kernel\Application.php:27` — der Fuge, die
`009-001-0001` genau dafür gebaut hat, dass sie hier die Vererbung verliert. Das ist die Form
des Schnitts: Silex und Symfony 7.4 können nicht nebeneinander liegen, also gibt es zwischen
diesem Task und `009-002-0006` keinen lauffähigen Zustand.

Syntaktisch ist der Baum in Ordnung — `php -l` über jede Datei in `lib/contentfly`, `custom`,
`bin` und `tests` läuft durch.

## Verification — durchgeführt

`composer update --with-all-dependencies`, Auswertung von `composer.lock` (kein Paket mit
`symfony/*` unter 7, kein `silex`, kein `pimple`, kein `knplabs`, kein `validator`, kein
`translation`), `composer audit --locked` ohne Meldung, `php -l` über den eigenen Baum.
