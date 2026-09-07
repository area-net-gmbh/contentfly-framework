---
id: 000-000-0015
title: Defekte im SystemController beheben
status: todo
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
- [ ] `deleteToken` entfernt eine Zeile aus `pim_token` und schreibt einen Logeintrag; der
      Erfolgsfall und der Fall „Token unbekannt" sind je durch einen Test belegt.
- [ ] Die tote `validateORM`-Bedingung ist aufgelöst — gestrichen oder durch eine
      wiederhergestellte Methode gedeckt; die Entscheidung steht begründet im Commit.
- [ ] `addToken` und `deleteToken` schreiben `Log::INSERTED` bzw. `Log::DELETED`; der Umgang
      mit dem Altbestand in `pim_log` ist entschieden und festgehalten.
- [ ] `method: "doAction"` führt nicht mehr in die Rekursion.
- [ ] Für Punkt 5 ist entweder ein Aufräumweg vorhanden oder begründet auf `013-003` verwiesen.
- [ ] Die Tests aus `008-004-0003`, die das defekte Verhalten festhalten, sind umgedreht —
      keiner davon bleibt als „so ist es nun mal" stehen.

## Verification
`custom/vendor/bin/phpunit` läuft vollständig grün; `pim_token` und `pim_log` sind nach dem
Lauf auf dem Ausgangsstand. Zusätzlich von Hand: einen API-Token über `addToken` anlegen,
über `listTokens` sehen, über `deleteToken` entfernen und die Tabelle prüfen — der Ablauf, der
heute nicht durchführbar ist.
