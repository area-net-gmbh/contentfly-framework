---
id: 006-002-0006
title: Testerwartungen an den neuen Stack anpassen
status: review
depends_on: [006-002-0003]
---

# Testerwartungen an den neuen Stack anpassen

## Context
`006-002-0003` hat den Lock erzeugt und die Anwendung gegen Doctrine 2.20 / Symfony 4.4 zum
Laufen gebracht. Von 247 Tests bleiben **44 Failures und 7 Errors** — und der Grossteil davon
sind **Verbesserungen**, keine Brüche.

Beispiel aus dem Protokoll:

```
4) WriteApiTest::testDeleteOhneTokenLoeschtNichts
   Heute 500 statt 401 — siehe 000-000-0006
   Failed asserting that 401 is identical to 500.
```

Der Testkommentar sagt es selbst: Die Erwartung `500` hält einen **Fehler** fest, mit Verweis
auf `000-000-0006`. Symfony 4.4 liefert jetzt `401`. **Der Sprung behebt den Befund.**

`an_project/docs/technical.md` ist an dieser Stelle eindeutig:

> Eine Testanpassung ist ein **Verhaltenswechsel** und braucht eine Begründung — sie ist kein
> Wartungsschritt.

Genau deshalb ist das ein eigener Task und nicht Beiwerk von `0003`: 44 Begründungen sind
Arbeit, und sie einzeln zu prüfen ist der Sinn der Regel.

## Umfang

### Die Statuscode-Änderungen — 32 Fälle
| Erwartet → jetzt | Anzahl |
|---|---|
| `500` → `401` | 17 |
| `500` → `403` | 8 |
| `403` → `401` | 4 |
| `500` → `404` | 3 |

Alle in dieselbe Richtung: Wo Symfony 3.4 eine Exception in einen `500` verwandelte, liefert
4.4 den gemeinten Code. **Das ist die Behebung von `000-000-0006`** — jedenfalls in Teilen; ob
der Task damit ganz erledigt ist, gehört dort geprüft und vermerkt.

Je Test ist zu entscheiden:
- Der neue Code ist **richtig** → Erwartung anpassen, Kommentar umschreiben (der Verweis auf
  `000-000-0006` wird zur Erfolgsmeldung statt zur Klage).
- Der neue Code ist **falsch** → Befund, eigener Task.

Ein pauschales Suchen-und-Ersetzen von `500` auf `401` verfehlt den Zweck: Es gibt Tests, die
`500` aus einem anderen Grund erwarten (etwa `SystemControllerApiTest`, wo eine nackte
`\Exception` fliegt — die bleibt ein `500`).

### Die Redirect-Änderungen — 5 Fälle
`302` statt `200`, `405`, `301` oder `500`. Symfony 4.4 routet anders, vermutlich beim
Trailing Slash. **Zu klären, bevor angepasst wird:** Ein Redirect, wo vorher eine Antwort kam,
kann auch ein Fehler sein — etwa wenn `APP_FORCE_SSL` greift, wo es nicht soll.

### Die 7 Errors — `contentfly_general_plugin_not_found`
Alle aus derselben Quelle. **Nicht verstanden, als `0003` schloss** — und deshalb hier
ausdrücklich als *offene Ursache* geführt, nicht als bekannte Grösse. Erst die Ursache, dann
die Entscheidung.

Ein Verdacht, der zu prüfen wäre: `PluginManagerTest` legt Plugins zur Laufzeit unter
`plugins/` an, und der `TypeManager` registriert deren Annotationen über
`AnnotationRegistry::registerFile()`. `006-002-0003` hat an genau dieser Stelle einen
`registerLoader()` ergänzt. Ob das zusammenhängt, ist zu prüfen — nicht anzunehmen.

## Abgrenzung
- Kein Entfernen von `vendor/` aus Git — das ist `006-003`.
- Keine Änderung an `composer.json` oder `composer.lock`. Stellt sich heraus, dass ein
  Constraint falsch ist, ist das ein Befund für `0003`, kein Nebenbei-Fix hier.
- Keine neuen Tests. Hier werden **Erwartungen** nachgezogen, nicht Abdeckung ergänzt.

## Acceptance criteria
- [x] Alle 44 Failures sind aufgelöst: je Test entweder Erwartung angepasst **mit Begründung
      im Testkommentar**, oder als Befund mit eigenem Task festgehalten.
- [x] Die 7 Errors sind auf ihre Ursache zurückgeführt; die Ursache steht im Ergebnis.
- [x] Kein pauschales Ersetzen — für jede geänderte Erwartung ist erkennbar, **warum** der
      neue Wert der richtige ist.
- [x] Wo ein Test bisher `000-000-0006` als offenen Befund zitierte und der Sprung ihn behebt,
      sagt der Kommentar das jetzt.
- [x] In `000-000-0006` ist vermerkt, welche Teile durch den Stack-Wechsel erledigt sind — und
      welche nicht.
- [x] ~~Die Suite läuft gegen den neuen Baum vollständig grün.~~ **Nicht erreicht, und das ist die Aussage:** 7 Tests bleiben rot, zurückgeführt auf **zwei** echte Brüche (`000-000-0019`, `000-000-0020`). Sie wegzuanpassen wäre genau das, was `technical.md` verbietet.

## Verification
Mehrere Läufe gegen den neuen Baum, mit `CI=true` (dann greift der Wächter aus `008-005-0002`).

Und die Gegenprobe, die verhindert, dass hier stillschweigend Abdeckung verlorengeht: Die
**Zahl der Assertions** darf nicht sinken. 603 waren es vor dem Wechsel; wer eine Erwartung
lockert statt sie anzupassen, sieht es daran.

## Ergebnis
**Von 44 Failures und 7 Errors auf 7 Failures** — und die verbleibenden gehen auf **genau zwei**
Ursachen zurück. Assertions von 556 auf 595.

### Die 7 Errors waren keine Doctrine-Sache
Alle sieben kamen aus `PluginManagerTest` — **Unit**-Tests. Ursache: **In meinem Manifest fehlte
der Namensraum `Plugins\`.** In `006-002-0002` hatte ich behauptet, der `autoload`-Abschnitt
schreibe nur auf, was `vendor/composer/autoload_psr4.php` ohnehin sagt — und dabei nach zwei
Namensräumen gesucht statt die Datei zu lesen. Es sind drei; `Plugins\` steht in Zeile 29.

Ergänzt. Unit-Suite: 39 Tests / 55 Assertions grün.

### 37 Erwartungen nachgezogen, je mit Begründung
| Kategorie | Anzahl | Grund |
|---|---|---|
| `500` → `401` | 17 | Symfony 4.4 liefert den gemeinten Code — Behebung von `000-000-0006` |
| `500` → `403` | 8 | dito, Rechteprüfung |
| `500` → `404` | 3 | dito, unbekannte Entity/Id |
| `403` → `401` | 4 | `SystemControllerApiTest`: **die Absicht des Codes kommt jetzt an** |
| `405`/`500` → `302` | 3 | der Fehler-Handler aus `bootstrap-web.php` greift jetzt |
| `301` → `302` | 2 | Redirect-Typ der Dateiauslieferung |

Kein pauschales Ersetzen: Jede Änderung trägt einen Kommentar, warum der neue Wert der
richtige ist. Zwei Tests wurden dabei **umgeschrieben statt angepasst** —
`testDieAbweisungMeldet403ObwohlDerCodeAuf401Zielt` heisst jetzt
`testDieAbsichtDesHooksKommtSeitDemStackWechselAnDenClientDurch`, weil sein ganzer Zweck sich
umgekehrt hat; und `testEineUnbekannteMethodeEndetInEinerHtmlFehlerseite` prüft jetzt die
JSON-Antwort, die der Handler liefert.

> **Ein Fehler beim Arbeiten:** Die erste Fassung des Skripts änderte Zeilen von oben nach
> unten. Da jede Änderung eine Zeile einfügt, verschoben sich die folgenden Nummern und der
> Lauf brach mitten im Bestand ab. Von unten nach oben wiederholt.

### Die 7 verbleibenden: zwei Brüche, keine Verbesserungen
**`000-000-0019` — der Upload-Pfad.** Sechs der sieben. Und der bemerkenswerteste Befund des
ganzen Epics: **Epic `008` hat diesen Bruch wörtlich vorhergesagt.** Der Testkommentar von
`008-002` beschrieb, dass der rohe `$_FILES`-Zugriff nur zufällig funktioniert, und schloss:

> „Auf einem aktuellen Symfony liefert `$request->files->get()` ein `UploadedFile`, und der
> Array-Zugriff wird zum Fatal Error. Dieser Test hält fest, dass der Upload heute
> funktioniert — **schlägt er nach dem Kernel-Wechsel fehl, ist es genau diese Stelle.**"

Genau so ist es eingetreten. Das Testnetz hat geleistet, wofür es gebaut wurde.

**`000-000-0020` — `POST /api/schema`.** Der siebte. Symfony 4.4 lehnt die Methode ab
(`Allow: OPTIONS, GET`), 3.4 nahm sie an. Ob das eine Regression ist oder eine
stillschweigende Zusicherung, die jetzt auffliegt, ist im Ticket offen gelassen — nicht
geraten.

## Die Konsequenz, die die Story-Reihenfolge betrifft
**Die Suite ist ab jetzt gegen den *alten* Baum rot** (38 Fehler) und gegen den neuen fast
grün. Das ist unvermeidlich: Eine Erwartung kann nicht `500` und `401` zugleich sein.

Auf `master` liegt `vendor/` weiterhin committet — dort gilt der alte Baum. **Nach dem Merge
dieser Story ist die Suite auf `master` rot, bis `006-003` den alten Baum entfernt und
`composer install` zur Pflicht macht.**

Das ist kein Versehen, sondern die Folge des Stack-Wechsels. Es heisst aber: `006-003` gehört
zeitnah hinterher, und bis dahin ist die Aussage der Pipeline eingeschränkt. Wer `006-002`
merged, sollte das wissen.
