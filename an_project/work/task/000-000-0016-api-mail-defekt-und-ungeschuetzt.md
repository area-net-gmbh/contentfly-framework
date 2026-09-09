---
id: 000-000-0016
title: /api/mail reparieren — aber nicht ohne Schutz
status: done
depends_on: [008-004-0004]
---

# /api/mail reparieren — aber nicht ohne Schutz

## Context
`008-004-0004` hat `/api/mail` charakterisiert und dabei festgestellt: **Der Endpunkt kann
keine Mail verschicken.** Er ist damit heute funktionslos — und genau deshalb ungefährlich.

Der Titel dieses Tasks ist die Warnung: Der Fehler ist in einer Zeile behoben, und wer das
tut, schaltet in derselben Zeile ein **Mail-Relais hinter einem Token** frei. Reparatur und
Schutz gehören zusammen; getrennt umgesetzt ist der Zwischenstand schlechter als der heutige.

## Umfang

### 1 — Der Fehler: `APP_MAILFROM` ist keine Konstante
```php
mail($mailto, $subject, $body, 'From: '.APP_MAILFROM);
```

`APP_MAILFROM` ist nirgends per `define()` gesetzt. Es gibt ein gleichnamiges
**Konfigurationsfeld** in `lib/contentfly/Classes/Config.php`, erreichbar über
`Adapter::getConfig()->APP_MAILFROM` — wie jede andere Einstellung auch. Unter PHP 8 ist eine
undefinierte Konstante ein `Error`:

```
Error: Undefined constant "Areanet\PIM\Controller\APP_MAILFROM"
```

Unter PHP 7 wäre derselbe Ausdruck noch als Zeichenkette `"APP_MAILFROM"` durchgegangen (mit
einer Notice) und hätte einen unsinnigen, aber funktionierenden `From`-Kopf ergeben. **Der
Endpunkt ist mit dem Sprung auf PHP 8 gestorben, ohne dass es jemandem auffiel** — ein
Hinweis darauf, wie viel er benutzt wurde.

Zu klären ist deshalb zuerst: Wird der Endpunkt überhaupt gebraucht? Ein funktionsloser
Endpunkt, den seit dem PHP-Sprung niemand vermisst hat, ist auch ein Kandidat zum Streichen.
**Streichen ist eine vollwertige Antwort auf diesen Task** und die einfachere.

### 2 — Die Warnung: eine Reparatur allein öffnet ein Relais
`mailAction()` nimmt Empfänger, Betreff und Inhalt unverändert vom Aufrufer entgegen:

- `mailto` — keine Validierung, kein `filter_var`, keine Positivliste. Die einzige Prüfung ist
  `if(!$mailto)`, also nur „nicht leer".
- `subject` — frei wählbar, Standardwert `"Anfrage über PIM-API"`.
- `data` — frei wählbar, wird zeilenweise als `name` + drei Tabulatoren + `value` in den Rumpf
  geschrieben. Keine Kodierung, keine Längenbegrenzung.

Die einzige Hürde ist ein gültiger Token. **Jeder angemeldete Benutzer kann damit an jede
Adresse mit beliebigem Inhalt senden**, und die Mail trägt die Absenderdomäne der Installation.
Es gibt keine Rechteprüfung darüber hinaus, keine Rate-Begrenzung und kein Protokoll — die
Vorgänge tauchen nicht einmal in `pim_log` auf.

Wer den Endpunkt behält, braucht deshalb mindestens: eine Empfängerprüfung (Positivliste oder
Konfigurationsfeld statt freier Wahl), eine Begrenzung der Aufrufe, und einen Protokolleintrag.

### 3 — Die doppelt kodierte Erfolgsantwort
```php
$data = json_encode($return);
return $this->renderResponse(array('data' => $data));
```

Der Rückgabewert wird erst zu JSON kodiert und dann als *Zeichenkette* in das `data`-Feld
gelegt. Ein Client bekäme `{"data":"{\"mailto\":…}"}` und müsste zweimal dekodieren; jeder
andere Endpunkt legt sein Objekt direkt in `data`. Heute nicht beobachtbar, weil der Aufruf
vorher abbricht. Gehört fachlich zu `000-000-0014`, ist hier aber mitzuerledigen, wenn die
Methode ohnehin angefasst wird.

## Abgrenzung
Der Statuscode `500` für den fehlenden `mailto` — ein Eingabefehler, der `400` verdiente —
bleibt aussen vor: Das ist dasselbe Muster wie überall im Framework und gehört einheitlich zu
`000-000-0006`.

## Wichtig für die Umsetzung
`tests/Integration/Api/MailApiTest.php` hält den heutigen Zustand fest und **sichert den
Testlauf gegen echten Versand ab** — über ein Fangskript als `sendmail_path` des Testservers,
dessen Wirksamkeit der Test selbst nachprüft (`testDieVersandfalleIstScharf()`). Diese
Sicherung hängt bewusst nicht am hier zu behebenden Fehler.

**Sie darf beim Reparieren nicht entfernt oder aufgeweicht werden.** Im Gegenteil: Sobald der
Endpunkt wieder sendet, ist sie das Einzige, was einen Testlauf davon abhält, echte Mail zu
verschicken. Einrichtung in `tests/README.md`.

## Acceptance criteria
- [x] Entschieden und begründet: Der Endpunkt bleibt oder fällt.
- [x] **Fällt er:** Route, Methode und die zugehörigen Tests sind entfernt; `APP_MAILFROM`
      ist als Konfigurationsfeld mit gestrichen, falls es keinen anderen Leser hat.
- [~] **Bleibt er:** entfällt — der Endpunkt fällt. Damit erübrigen sich Empfängerprüfung,
      Rate-Begrenzung, Protokolleintrag und die doppelte Kodierung der Antwort.
- [x] Die Versandfalle aus `008-004-0004` ist unverändert wirksam — nachweislich, nicht
      angenommen.
- [x] Die Tests aus `008-004-0004`, die das defekte Verhalten festhalten, sind umgedreht oder
      mit dem Endpunkt entfernt.

## Verification
`custom/vendor/bin/phpunit` läuft vollständig grün, mit gesetztem
`CONTENTFLY_TEST_MAIL_TRAP`; der Postausgang der Falle ist nach dem Lauf leer. Bleibt der
Endpunkt, kommt ein Nachweis dazu, dass ein Versand an eine **nicht** erlaubte Adresse
abgewiesen wird.

## Ergebnis
**Der Endpunkt fällt.** Entschieden von dir, und der Task hatte diesen Ausgang ausdrücklich als
vollwertige Antwort vorgesehen — als die einfachere. Sie ist es auch aus einem zweiten Grund:
Die Reparatur hätte in derselben Zeile ein **offenes Mail-Relais hinter einem Token**
freigeschaltet.

### Was entfernt wurde
| | |
|---|---|
| Route | `$controllers->post('/mail', …)` in `ApiControllerProvider` |
| Methode | `ApiController::mailAction()` |
| Konfiguration | `APP_MAILFROM` — nachgeprüft: `mailAction()` war der **einzige** Leser |
| Tests | neun aus `MailApiTest`, die das defekte Verhalten festhielten |

Kein verwaister apidoc-Block, keine sonstige Fundstelle: `grep` über `lib/`, `custom/` und
`bin/` nach `mailAction` und `APP_MAILFROM` liefert nichts mehr.

### Was **nicht** entfernt wurde — und warum das der wichtigere Teil ist
**`$app['mailer']` bleibt.** Der PHPMailer-Dienst aus `bootstrap.php:156` samt allen
`MAILER_*`-Konfigurationsfeldern ist von diesem Task nicht berührt. Er ist der Weg, auf dem ein
Projekt Mail verschickt — im Projektcode, wo die Empfängerprüfung hingehört, statt hinter einem
generischen Endpunkt.

**Die Versandfalle bleibt, und ihr Selbsttest mit ihr.** Das war die Stelle, an der dieser Task
hätte schiefgehen können: Die Sicherung war für `/api/mail` gebaut, also läge es nahe, sie mit
ihm zu entfernen. Genau davor warnt der Task-Text — *„Wer den Fehler behebt, soll nicht
gleichzeitig den Schutz entfernen."* Das gilt beim Entfernen genauso.

`MailApiTest` ist deshalb nicht gelöscht, sondern **aufgelöst**: Der eine Test, der die Falle
selbst prüft, ist nach `tests/Integration/VersandfalleTest.php` gewandert, samt seiner drei
Hilfsmittel und der Herkunftsnotiz. Die neun Tests über den Endpunkt sind mit ihm gegangen.

Der Grund steht dort: `$app['mailer']` existiert weiter, `custom/app.php` ist die Vorlage, in
die ein Projekt den Versand einbaut, und **eine Sicherung, die man mit ihrem ersten Anlass
abbaut, fehlt beim zweiten**.

### Vier Dokumente nachgezogen
| Datei | was |
|---|---|
| `tests/router.php` | Der Diagnosepfad begründet sich jetzt aus `VersandfalleTest`, nicht aus `/api/mail` |
| `tests/README.md` | Abschnitt heisst *Die Versandfalle* statt *…für `/api/mail`*; der Hinweis, die Falle sei „streng genommen unnötig", ist durch die Begründung ersetzt, warum sie bleibt |
| `tests/Integration/UmgebungsWaechterTest.php` | Die Begründungstexte für `CONTENTFLY_TEST_MAIL_TRAP` |
| `tools/ci/prepare-test-environment.sh` | Der Kommentar über der Falle |

Nach dem Umbau findet `grep` über `tests/`, `tools/` und `an_project/docs/` **keinen**
`MailApiTest`-Verweis mehr ausser dem in der Herkunftsnotiz.

### Breaking Change
`an_project/docs/breaking-changes.md` hat einen eigenen Abschnitt bekommen. Er sagt, dass
praktisch niemand betroffen ist — der Endpunkt verschickte seit dem PHP-8-Sprung nichts —, was
stattdessen zu benutzen ist (`$app['mailer']`), und **warum entfernt statt repariert**: Der
Fehler wäre eine Zeile gewesen, das Relais dahinter nicht.

### Verification
| Prüfung | Ergebnis |
|---|---|
| volle Suite mit `CI=true` | **OK (236 tests, 580 assertions)**, 0 übersprungen |
| davon `VersandfalleTest` | läuft, nicht übersprungen |
| Postausgang der Versandfalle | 0 Byte |
| Deprecation-Gate | grün, 1 Paar, 1 ausgenommen |

Die Suite geht von 249 auf 236 Tests. Die 13 sind die neun entfernten Testmethoden plus die
Datensätze des `@dataProvider` von `testJederFalsyWertGiltAlsFehlendeAdresse()`.
