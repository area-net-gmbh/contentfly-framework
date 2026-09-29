---
id: 000-000-0108
title: PHPStan von Level 0 auf Level 2 heben
status: review
depends_on: []
---

# PHPStan von Level 0 auf Level 2 heben

## Context
**`phpstan.neon.dist` nennt diesen Task selbst:** „Ihn anzuheben ist eine eigene Entscheidung mit
eigenem Aufwand, und dieses Gate hat einen anderen Zweck. Wer PHPStan als echte statische Analyse
will, hebt den Level in einem eigenen Ticket." `000-000-0056` sagt dasselbe in seiner Abgrenzung:
„Sonar ersetzt das nicht — beide sehen Verschiedenes."

Level 0 findet praktisch nur, was ohnehin beim Laufen auffiele. Der Level steht dort seit
`006-005-0004`, ursprünglich weil ein höherer Level auf einem Silex-2-Baum in Typfehlern
ersoffen wäre. **Silex ist seit Epic `009` weg, und die Rechnung sieht anders aus.**

**Gemessen am 2026-09-29, bevor dieser Task geschrieben wurde:**

| Level | Meldungen |
|---|---|
| 0 | 0 (der heutige Stand, blockierend) |
| 1 | 25 |
| 2 | **37** |

Das ist keine Mauer. Und die 37 zerfallen in drei sehr ungleiche Gruppen:

**Gruppe A — 21 × `Variable $app might not be defined`** (`bootstrap-web.php` 13,
`custom/app.php` 7, `Start.php` 1). Kein Fehler, sondern eine fehlende Zusage: Das sind Dateien,
die in einen Scope hinein eingebunden werden, in dem `$app` existiert. Ein `@var` am Dateikopf
schreibt genau das auf — und dokumentiert nebenbei den Vertrag der Datei.

**Gruppe B — 9 Doku-Defekte.** Vier kaputte `@var`-Tags (`@var $user User` statt `@var User $user`
in `I18nPermission`, ein doppeltes `@var` in `Api.php`), zwei `@param`, die dem nativen Typ
widersprechen, ein `@var` ohne Variablenname, zwei Zugriffe auf eine dynamische Property im Test.
Alles falsche Dokumentation — sie beschreibt etwas anderes, als dasteht.

**Gruppe C — 5 echte Funde, und das ist der Ertrag dieses Tasks:**

| Stelle | Meldung | Warum das zählt |
|---|---|---|
| `FileController.php:751` | `$fileInfo` might not be defined | Die Schleifenvariable wird **nach** der Schleife benutzt. Leeres Quellverzeichnis → undefinierte Variable → Fatal mitten im Überschreiben |
| `Api.php:706` | `$property`, `$value` might not be defined | Beide stehen in der Fehlermeldung eines `catch`, stammen aber aus einer Schleife weit darüber |
| `Api.php:439` | `$lang in empty() always exists and is always falsy` | Wenn das stimmt, feuert der `throw` darunter **immer** — ein Zweig, der nie etwas anderes tut |
| `InstallCommand.php:285` | `Call to an undefined method Closure::registerType()` | Auf einer Closure wird eine Methode gerufen |
| `bootstrap.php:562` | `Call to an undefined method ArrayAccess&PHPMailer::extend()` | Typverwechslung im Container-Zugriff |

**Vier dieser fünf sind latente Fehler, die niemand gemeldet hat, weil der Pfad selten ist.**
Genau dafür gibt es statische Analyse.

## Acceptance criteria
- [x] `phpstan.neon.dist` steht auf `level: 2`, der Lauf ist grün und der Job bleibt blockierend.
- [x] **Keine Baseline.** Was nicht behoben wird, steht als benannter Eintrag in `ignoreErrors` — mit Grund und, wo es eine Verhaltensfrage ist, mit dem auflösenden Ticket. Derselbe Aufbau wie die bestehenden Einträge und wie `deprecations-ausnahmen.txt`.
- [x] ~~**Gruppe A** ist über `@var` am Dateikopf gelöst, nicht über Ausnahmen~~ — **so nicht erfüllbar, gemessen beim Umsetzen.** `might not be defined` fragt nach der *Definiertheit*, `@var` gibt nur einen *Typ*; weder am Dateikopf noch über der ersten Verwendung ändert es etwas. Gelöst als benannte Ausnahme **mit Pfad**, und der Vertrag steht trotzdem im Kopf der Dateien — da gehört er hin, unabhängig davon, ob ein Werkzeug ihn liest.
- [x] **Gruppe B** ist behoben: Die Dokumentation sagt, was der Code tut.
- [x] **Gruppe C:** Jeder der fünf Funde ist einzeln entschieden — behoben, oder mit eigenem Ticket und Ausnahme mit Verweis darauf. Ein Fund, der klaglos verschwindet, ohne dass jemand ihn angesehen hat, ist kein Ergebnis.
- [x] Der Umweg über Level 1 ist **nicht** nötig: Level 2 enthält Level 1. Falls er doch gefahren wird, ist begründet warum.
- [x] `phpstan.neon.dist` erklärt den neuen Level so, wie es heute Level 0 erklärt — mit der Rechnung, die dahintersteht.
- [x] `deployment.md` (Gate-Tabelle) nennt den Level.

## Verification
`vendor/bin/phpstan analyse --no-progress --memory-limit=1G` grün auf Level 2, **ohne Baseline**.

**Gegenprobe für die Ausnahmen:** Eine Ausnahme, die nichts mehr trifft, macht den Lauf rot — das
ist seit `006-005-0004` die Eigenschaft dieser Liste und gilt für jeden neuen Eintrag. Einmal eine
Ausnahme versuchsweise entfernen und zeigen, dass der Lauf rot wird; sonst ist nicht belegt, dass
der Eintrag noch etwas tut.

**Für jeden behobenen Fund aus Gruppe C:** ein Test, der ohne den Fix rot ist — oder, wo ein Test
den Aufwand nicht wert ist, eine Notiz im Code, warum nicht. Die volle Suite bleibt grün.

## Abgrenzung
**Level 3 und höher ist ein eigener Task.** Ab Level 3 kommen die Rückgabetypen dazu, und das ist
eine andere Grössenordnung — die Zahl dafür ist in diesem Task nicht gemessen worden.

## Ergebnis (2026-09-29)
**Level 2, grün, ohne Baseline.** Von 37 Meldungen sind 5 als Ausnahme benannt, der Rest ist
behoben.

### Gruppe C — die fünf echten Funde, einzeln entschieden

| Fund | Entscheidung |
|---|---|
| `FileController:751` `$fileInfo` | **Behoben.** Die Laufvariable wurde hinter der Schleife gelesen — leeres Quellverzeichnis hiess undefinierte Variable und Fatal mitten im Überschreiben; war sie definiert, trug sie den *zuletzt* verschobenen Eintrag, bei Original plus Thumbnails also einen beliebigen. Jetzt `$fileDest->getName()`, was ohnehin gemeint war |
| `Api.php:706` `$property`/`$value` | **Behoben.** Beide werden im `catch` für die Meldung gelesen, die Schleife darüber läuft bei leerem `$data` nie. Vorbelegt — sonst ein zweiter Fehler während der Behandlung des ersten |
| `Api.php:439` `$lang` | **Behoben, und es war ein Doku-Defekt:** Der Docblock deklarierte `@param null $lang` wörtlich. PHPStan glaubte ihm zu Recht. Kein Laufzeitfehler, aber eine Zusage, die das Gegenteil sagte |
| `bootstrap.php:562` `extend()` | **Behoben** — verursacht durch ein `@var` ohne Variablenname, das PHPStan auf `$app` bezog. Mit dem Doku-Fix verschwunden |
| `InstallCommand:285` `Closure::registerType()` | **Ausnahme.** Der Container löst Factories erst beim Zugriff auf; PHPStan verfolgt die Zuweisung. Dieselbe blinde Stelle, die die Liste für `$this->app['orm.em']` schon beschreibt |

### Gegenprobe
Eine Ausnahme versuchsweise entfernt (`$app` in `custom/app.php`) → **7 Fehler**, Lauf rot. Wieder
eingesetzt → grün. Die Einträge tun also etwas; `reportUnmatchedIgnoredErrors` hält die
Gegenrichtung.

### Ein eigener Fehler beim Umsetzen
Die neuen Code-Kommentare waren zuerst auf **Deutsch**. `EnglishOnlyTest` hat sechs Dateien
gemeldet — Code, Kommentare und Meldungen sind seit Epic `014` englisch. Übersetzt. Es ist heute
das zweite Mal (nach `000-000-0020`), und beide Male hat der Wächter es gefangen und nicht ich.

**Gemessen:** volle Suite **1214 grün** (3 Skips), PHPStan Level 2 `[OK] No errors`,
Deprecation-Gate 0 Zeilen, `check-lock-format` grün.
