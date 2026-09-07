---
id: 008-004-0003
title: SystemController und die Token-Verwaltung
status: done
depends_on: []
---

# SystemController und die Token-Verwaltung

## Context
Der `SystemController` hat **eine** Route — `POST /system/do` — und verteilt von dort dynamisch:

```php
$method = $request->get('method');

if(!method_exists($this, $method)){
    throw new \Exception("Methode $method nicht verfügbar.");
}

return new JsonResponse(array('method' => $method, 'datetime' => …, 'message' => $this->$method($request)));
```

Der **Request bestimmt, welche Methode läuft**. Das ist ein Muster, das man festhalten will,
bevor jemand den Kernel darunter austauscht.

## Umfang

### A — Die Absicherung
Der `before`-Hook verlangt einen gültigen Token **und** `isAdmin`. Beides ist festzuhalten:
ein Nicht-Admin mit gültigem Token wird abgewiesen.

### B — Der Dispatch und sein Umfang
Erreichbar ist alles, was `method_exists($this, …)` bejaht — also die sechs eigenen Methoden
(`flushSchemaCache`, `updateDatabase`, `deleteToken`, `generateToken`, `listTokens`,
`addToken`), dazu `doAction` selbst und was `BaseController` mitbringt (`__construct`,
`setEM`).

Festzuhalten:
- Eine unbekannte `method` führt zu einer Ausnahme (heute HTTP 500).
- Die Form der Antwort: `method`, `datetime`, `message`.
- Was bei `method: "doAction"` passiert — der Dispatch auf sich selbst.

Das ist **Charakterisierung, keine Bewertung.** Ob dieses Muster bleiben soll, ist eine Frage
für Epic `009`; fällt beim Schreiben etwas wirklich Bedenkliches auf, wird es als eigener Task
notiert.

### C — Die Token-Verwaltung
Vier der sechs Methoden verwalten API-Token: `addToken`, `deleteToken`, `listTokens`,
`generateToken`. Das ist auth-relevante Oberfläche und hängt mit Story `013-003` (JWT und
Widerruf) zusammen — was dort ersetzt wird, muss hier zuerst festgehalten sein.

Je Methode: Erfolgsfall, Form der Antwort, und was mit `pim_token` in der Datenbank geschieht.

### D — Das Notschloss
Der `before`-Hook fängt eine `InvalidFieldNameException` ab und lässt in diesem Fall
`validateORM` und `updateDatabase` **ohne jede Prüfung** durch:

```php
}catch(InvalidFieldNameException $e){
    if($request->get('method') == 'validateORM' || $request->get('method') == 'updateDatabase'){
        // durchlassen
    }else{
        throw $e;
    }
}
```

Der Sinn ist erkennbar: Ist das Datenbankschema kaputt, scheitert schon das Laden des
Benutzers — und dann muss man es reparieren können, ohne sich anmelden zu können. Der Preis
ist, dass diese beiden Methoden bei kaputtem Schema **ohne Token** erreichbar sind.

Ein Test dafür bräuchte ein absichtlich kaputtes Schema. Ob sich das im Testaufbau herstellen
lässt, ohne die Umgebung für andere Tests zu beschädigen, ist beim Umsetzen zu entscheiden.
Lässt es sich nicht, wird das Verhalten **im Testkommentar dokumentiert** statt mit einem Test
ins Leere kaschiert — und als eigener Task notiert, falls es bedenklich erscheint.

> `validateORM` steht übrigens in der Ausnahmeliste, existiert als Methode aber **nicht mehr**
> im Controller. Auch das gehört geprüft und festgehalten.

## Acceptance criteria
- [x] Ein Nicht-Admin mit gültigem Token wird von `/system/do` abgewiesen.
- [x] Ohne Token wird abgewiesen.
- [x] Eine unbekannte `method` führt zum dokumentierten Fehlerverhalten.
- [x] Die Form der Antwort (`method`, `datetime`, `message`) ist festgehalten.
- [x] Für die vier Token-Methoden ist je der Erfolgsfall und die Wirkung auf `pim_token`
      festgehalten.
- [x] `flushSchemaCache` ist abgedeckt.
- [x] Das Notschloss ist geprüft oder begründet dokumentiert; dass `validateORM` in der
      Ausnahmeliste steht, aber nicht existiert, ist verifiziert.

## Verification
Mehrere vollständige Läufe; `pim_token` ist danach auf dem Ausgangsstand. Die Token-Tests
dürfen den Token des Testlaufs nicht entwerten — sonst brechen die folgenden Tests.

## Ergebnis
`tests/Integration/Api/SystemControllerApiTest.php` — 25 Tests, 72 Assertions. Gesamtsuite
206 Tests / 480 Assertions (vorher 181 / 408). Fünf Läufe der Datei und zwei Gesamtläufe;
`pim_token` und `pim_log` sind danach unverändert.

### Was festgehalten ist
- **Absicherung** — ohne Token, mit ungültigem Token und als Nicht-Admin: je `403`. Der Code
  übergibt `401` als drittes Argument von `AccessDeniedHttpException` — das ist der
  Exception-Code, nicht der Statuscode. Die Absicht kommt nie beim Client an.
- **Dispatch** — das Tor ist `method_exists`, keine Erlaubnisliste. Auch `setEM` und
  `__construct` aus `BaseController` werden aufgerufen; sie scheitern erst an ihrer
  Typprüfung, nicht am Endpunkt.
- **Antwortform** — `method`, `datetime` (`Y-m-d H:i:s`), `message`. Eine eigene Form neben
  dem `data`/`totalItems`/`version`/`hash` der `/api`-Endpunkte (`000-000-0014`).
- **Fehlerfall** — unbekannte oder fehlende `method` ergibt `500` und eine **HTML**-Seite,
  obwohl JSON angefordert wurde.
- **Token-Methoden** — `generateToken` (128 Hex-Zeichen, schreibt nichts), `addToken`
  (Zeile plus Logeintrag, Token im Klartext in beiden), `listTokens` (nur Einträge mit
  Referrer — der Anmeldetoken des Laufs bleibt deshalb unsichtbar), `deleteToken` (defekt).
- **`flushSchemaCache`** — meldet Erfolg auch dann, wenn es nichts zu löschen gab: die Datei
  `data/cache/schema.cache` entsteht nur bei `APP_ENABLE_SCHEMA_CACHE`, und die Vorlage
  schaltet das aus.

### Das Notschloss
Nicht scharf getestet, mit Begründung im Testkommentar: Der Zweig verlangt ein absichtlich
beschädigtes Schema der **gemeinsamen** Testdatenbank und die Hoffnung, dass `updateDatabase`
es vollständig wiederherstellt. Scheitert das auf halbem Weg, ist die Datenbank für jeden
folgenden Test kaputt. Festgehalten sind stattdessen die Bedingungen: nur
`InvalidFieldNameException` öffnet das Schloss, jede andere Methode fliegt weiter — und
`validateORM` existiert nicht.

### Befunde → `000-000-0015`
1. `deleteToken` sucht in `Areanet\Contently\Entity\Token` — die einzige Stelle im Baum mit
   diesem Namen. Es gibt heute **keinen Weg, einen API-Token über die API zu löschen.**
2. `validateORM` steht in der Ausnahmeliste des `before`-Hooks, existiert aber nicht.
3. `addToken`/`deleteToken` schreiben `mode` als `'Erstellt'`/`'Gelöscht'` statt
   `Log::INSERTED`/`Log::DELETED` — zwei Vokabulare in derselben Spalte.
4. `doAction` ist public und besteht `method_exists` — `method: "doAction"` schickt den
   Controller in eine Endlosrekursion bis zum `memory_limit`. Bewusst nicht ausgelöst: ohne
   Limit liefe sie unbegrenzt und nähme den Testserver mit.
5. `pim_token` wächst unbegrenzt. Aufgeräumt wird nur träge in `checkToken()` — ein Token,
   den niemand wieder vorzeigt, bleibt für immer. Überschneidet sich mit `013-003`.
