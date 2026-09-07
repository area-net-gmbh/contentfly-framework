---
id: 008-004-0003
title: SystemController und die Token-Verwaltung
status: todo
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
- [ ] Ein Nicht-Admin mit gültigem Token wird von `/system/do` abgewiesen.
- [ ] Ohne Token wird abgewiesen.
- [ ] Eine unbekannte `method` führt zum dokumentierten Fehlerverhalten.
- [ ] Die Form der Antwort (`method`, `datetime`, `message`) ist festgehalten.
- [ ] Für die vier Token-Methoden ist je der Erfolgsfall und die Wirkung auf `pim_token`
      festgehalten.
- [ ] `flushSchemaCache` ist abgedeckt.
- [ ] Das Notschloss ist geprüft oder begründet dokumentiert; dass `validateORM` in der
      Ausnahmeliste steht, aber nicht existiert, ist verifiziert.

## Verification
Mehrere vollständige Läufe; `pim_token` ist danach auf dem Ausgangsstand. Die Token-Tests
dürfen den Token des Testlaufs nicht entwerten — sonst brechen die folgenden Tests.
