---
id: 000-000-0015
title: Defekte im SystemController beheben
status: done
depends_on: [008-004-0003]
---

# Defekte im SystemController beheben

## Context
`008-004-0003` hat `POST /system/do` charakterisiert und dabei fünf Befunde festgehalten —
als Test, nicht als Reparatur, weil Epic `008` ausdrücklich beschreibt statt zu ändern. Die
Tests in `tests/Integration/Api/SystemControllerApiTest.php` sichern heute das **defekte**
Verhalten ab; wer diesen Task umsetzt, dreht sie um.

Der schwerste Punkt ist der erste: **API-Token lassen sich derzeit über die API nicht
löschen.** Das ist auth-relevant und berührt Story `013-003` (JWT und Widerruf).

## Umfang

### 1 — `deleteToken` sucht im falschen Namensraum
```php
$token = $this->em->getRepository('Areanet\\Contently\\Entity\\Token')->find($id);
```
„Contently" statt „PIM" — die einzige Stelle im ganzen Baum mit diesem Namen. Doctrine kennt
die Klasse nicht, die Methode endet vor ihrer ersten fachlichen Zeile. `deleteToken` liefert
heute immer `500`, die Zeile bleibt stehen.

Der Rest der Methode ist unerprobt: Nach der Korrektur läuft erstmals Code, den nie jemand
ausgeführt hat. Er gehört mitgeprüft, nicht nur der Repository-Aufruf.

### 2 — `validateORM` steht im Notschloss, existiert aber nicht
Der `before`-Hook in `SystemControllerProvider` lässt bei einer `InvalidFieldNameException`
zwei Methoden **ohne Token und ohne Adminrecht** durch:

```php
if($request->get('method') == 'validateORM' || $request->get('method') == 'updateDatabase'){
```

`validateORM` gibt es im Controller nicht mehr. Die Ausnahme führt ins Leere — sie öffnet die
Tür für eine Methode, die dahinter ohnehin an `method_exists` scheitert.

Zwei Wege: die tote Bedingung streichen, oder die Methode wiederherstellen (Doctrine bringt
mit `SchemaValidator` alles mit; der Import steht sogar noch oben in der Datei). **Die
Entscheidung gehört begründet**, denn beides verändert, was bei kaputtem Schema erreichbar
ist.

### 3 — Deutsche Modus-Strings statt der `Log`-Konstanten
`addToken` schreibt `$log->setMode('Erstellt')`, `deleteToken` `setMode('Gelöscht')`. Die
Konstanten heissen `Log::INSERTED` (`'INS'`) und `Log::DELETED` (`'DEL'`). Damit stehen zwei
Vokabulare in `pim_log.mode`, und wer nach `Log::INSERTED` filtert, findet die Token-Vorgänge
nicht.

Beim Umstellen ist zu entscheiden, was mit **vorhandenen** Zeilen geschieht — eine Migration,
oder eine bewusste Duldung des Altbestands.

### 4 — `doAction` ruft sich selbst auf
`doAction` ist public und besteht damit `method_exists($this, $method)`. `method: "doAction"`
schickt den Controller in eine Endlosrekursion, die erst am `memory_limit` endet — mit dem
Standardwert nach etwa 0,2 s in einem Fatal Error, **ohne** Limit gar nicht.

Erreichbar nur als Administrator, deshalb keine offene Tür. Es zeigt aber, dass das Tor
`method_exists` ist und keine Erlaubnisliste: Auch `setEM` und `__construct` aus
`BaseController` werden aufgerufen und scheitern erst an ihrer Typprüfung.

Naheliegend ist eine ausdrückliche Liste der erlaubten Methoden. Das ist zugleich die Stelle,
an der Epic `009` ohnehin ansetzt — der dynamische Dispatch ist kein Muster, das ein
Symfony-Controller übernimmt.

### 5 — `pim_token` wächst unbegrenzt
Jede Anmeldung legt eine Zeile an. Aufgeräumt wird nur träge, in
`BaseControllerProvider::checkToken()`: Wird ein abgelaufener Token noch einmal vorgezeigt,
verschwindet er. Ein Token, den niemand wieder benutzt — der Normalfall beim Schliessen des
Browsers — bleibt für immer. Es gibt keinen Aufräumlauf, kein Console-Command und keinen
Endpunkt dafür.

**Überschneidet sich mit `013-003`.** Ersetzt jene Story die Tokentabelle durch JWT, erledigt
sich der Punkt; bleibt sie, braucht es einen Aufräumweg. Vor der Umsetzung ist zu klären,
welcher Fall gilt — sonst wird hier etwas gebaut, das `013-003` gleich wieder abräumt.

## Abgrenzung
Kein Umbau des Dispatch-Musters und keine Vereinheitlichung der Antwortform — das eine gehört
zu Epic `009`, das andere zu `000-000-0014`. Auch der Nebenbefund, dass der `before`-Hook
`401` als Exception-Code an `AccessDeniedHttpException` übergibt und beim Client `403`
ankommt, bleibt hier aussen vor: Dasselbe Missverständnis steckt an mehreren Stellen des
Frameworks und gehört einheitlich behandelt, zusammen mit `000-000-0006`.

## Acceptance criteria
- [x] `deleteToken` entfernt eine Zeile aus `pim_token` und schreibt einen Logeintrag; der
      Erfolgsfall und der Fall „Token unbekannt" sind je durch einen Test belegt.
- [x] Die tote `validateORM`-Bedingung ist aufgelöst — gestrichen oder durch eine
      wiederhergestellte Methode gedeckt; die Entscheidung steht begründet im Commit.
- [x] `addToken` und `deleteToken` schreiben `Log::INSERTED` bzw. `Log::DELETED`; der Umgang
      mit dem Altbestand in `pim_log` ist entschieden und festgehalten.
- [x] `method: "doAction"` führt nicht mehr in die Rekursion.
- [x] Für Punkt 5 ist entweder ein Aufräumweg vorhanden oder begründet auf `013-003` verwiesen.
- [x] Die Tests aus `008-004-0003`, die das defekte Verhalten festhalten, sind umgedreht —
      keiner davon bleibt als „so ist es nun mal" stehen.

## Verification
`custom/vendor/bin/phpunit` läuft vollständig grün; `pim_token` und `pim_log` sind nach dem
Lauf auf dem Ausgangsstand. Zusätzlich von Hand: einen API-Token über `addToken` anlegen,
über `listTokens` sehen, über `deleteToken` entfernen und die Tabelle prüfen — der Ablauf, der
heute nicht durchführbar ist.

## Ergebnis
**Alle fünf Punkte gelöst.** Der interessanteste ist der fünfte: Die Frage, ob `013-003` die
Tokentabelle ohnehin ersetzt, lässt sich beantworten — und die Antwort ist nein.

### 1 — `deleteToken`, und was danach kam
`Areanet\Contently\Entity\Token` → `Areanet\PIM\Entity\Token`. Ein Wort, und **ein
API-Token liess sich über die API nicht löschen**.

Der Task warnte: *„Der Rest der Methode ist unerprobt — nach der Korrektur läuft erstmals Code,
den nie jemand ausgeführt hat."* Er läuft. Belegt sind beide Fälle, wie gefordert: Die Zeile
verschwindet und ein Logeintrag mit `Log::DELETED` entsteht; ein unbekannter Token endet mit
`Token ungültig`.

### 2 — `validateORM`: gestrichen, nicht wiederhergestellt
Das Notschloss liess `validateORM` und `updateDatabase` **ohne Token und ohne Adminrecht**
durch — und die erste der beiden gab es nicht.

Doctrine brächte mit `SchemaValidator` alles mit, und der Import steht noch oben in der Datei.
Trotzdem gestrichen: **Eine wiederhergestellte Methode wäre ein zweiter Endpunkt ohne Token und
ohne Adminrecht.** Ein Notschloss soll so klein sein wie möglich. Wer den Schemazustand prüfen
will, kann das mit einem Console-Command tun, der keine offene Tür braucht.

`updateDatabase` bleibt: Ein kaputtes Schema muss reparierbar sein, ohne dass man sich anmelden
kann — das ist der Sinn des Zweigs.

### 3 — `Log`-Konstanten, Altbestand bleibt
`'Erstellt'` → `Log::INSERTED`, `'Gelöscht'` → `Log::DELETED`.

**Der Altbestand wird nicht migriert, und das ist die Entscheidung, nicht die Bequemlichkeit:**
`pim_log` ist ein Protokoll. Alte Zeilen nachträglich umzuschreiben hiesse, die Aufzeichnung zu
ändern — und zwar rückwirkend eine Aussage darüber, was das System damals getan hat. Eine
Migration wäre technisch einfach und fachlich falsch. Der Umgang steht in
`breaking-changes.md`, samt dem Hinweis, dass sich der Stichtag aus `pim_log.created` ablesen
lässt.

### 4 — Erlaubnisliste statt `method_exists`
Sechs Methoden, ausgeschrieben. **Bewusst nicht aus Reflection abgeleitet:** Was dort steht, ist
eine Entscheidung, keine Eigenschaft der Klasse — sonst wäre jede neue Methode wieder
automatisch ein Endpunkt.

Damit fällt auch der Rekursionsfall. Der alte Test prüfte die **Ursache** statt der Wirkung
(`doAction` ist public), weil ein Aufruf ohne `memory_limit` den Testserver mitgenommen hätte.
Jetzt lässt sich die Wirkung gefahrlos prüfen: `method: "doAction"` wird abgewiesen.
`doAction` bleibt public — Silex ruft es als Route auf.

### 5 — Die Tokentabelle: `013-003` löst es nicht
Der Task liess offen, welcher Fall gilt, und warnte vor Arbeit, die `013-003` gleich wieder
abräumt. Nachgelesen — die Story sagt es deutlich:

> **Refresh-Token als opaques DB-Token** — also genau der Mechanismus, der ohnehin existiert.

Die Tabelle bleibt also, und mit ihr das Wachstum. Deshalb ein Aufräumweg:
**`appcms:token:cleanup`**, mit `--dry-run`.

Er rechnet **dieselbe Rechnung wie `checkToken()`**, damit nichts fällt, was dort noch gültig
wäre: `modified` gegen das Zeitlimit, Limit aus der Gruppe vor `APP_TOKEN_TIMEOUT`, Token mit
`referrer` bleiben (API-Token verfallen nicht über die Zeit), und bei ausgeschaltetem
`APP_CHECK_TOKEN_TIMEOUT` räumt er gar nichts weg — sonst löschte er gültige Sitzungen.

`--dry-run` ist **nicht** der Vorgabewert: Wer das Command in einen Cron hängt, soll nicht
feststellen, dass es nie etwas getan hat.

### Die Tests sind umgedreht, keiner blieb stehen
| vorher | jetzt |
|---|---|
| `testDasTorIstMethodExistsUndNichtEineErlaubnisliste` | `testDasTorIstEineErlaubnislisteUndNichtMethodExists` |
| `testDoActionRuftSichSelbstAufUndWirdDeshalbNichtScharfGeprueft` | `testDoActionRuftSichNichtMehrSelbstAuf` |
| `testAddTokenSchreibtDenLogeintragMitEinemDeutschenModusStattDerKonstanten` | `testAddTokenSchreibtDenLogeintragMitDerKonstanten` |
| `testDeleteTokenIstDurchEinenFalschenNamensraumUnbrauchbar` | `testDeleteTokenEntferntDieZeileUndProtokolliertEs` |
| `testValidateORMStehtInDerAusnahmelisteExistiertAberNicht` | `testDasNotschlossKenntNurNochUpdateDatabase` |

Dazu **zwei neue**: `testDeleteTokenMeldetEinenUnbekanntenToken()` und
`testAbgelaufeneAnmeldetokenLassenSichAufraeumen()`.

### Ein Fehlgriff beim Testaufbau
Mein erster Aufräum-Test legte Token mit selbstgebauten Zeichenketten-Ids an und scheiterte an
`Incorrect integer value`. **`pim_token.id` ist eine Integer-Spalte mit Auto-Increment** —
anders als die Entities, die von `Base` erben und eine GUID tragen. Ich hatte von der
Id-Strategie der einen auf die andere geschlossen. Jetzt vergibt MySQL die Id.

### Verification
| Prüfung | Ergebnis |
|---|---|
| `SystemControllerApiTest` | **OK (27 tests, 81 assertions)** |
| volle Suite mit `CI=true` | **OK (239 tests, 594 assertions)**, 0 übersprungen |
| Deprecation-Gate | grün, 1 Paar, 1 ausgenommen |

Drei Einträge in `breaking-changes.md`: die Erlaubnisliste, die `Log`-Konstanten samt Umgang mit
dem Altbestand, und — als Gegenteil eines Breaking Change, aber erwähnenswert — dass
`deleteToken` jetzt funktioniert.
