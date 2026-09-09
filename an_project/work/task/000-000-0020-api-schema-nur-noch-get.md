---
id: 000-000-0020
title: POST /api/schema ist mit Symfony 4 nicht mehr erreichbar
status: done
depends_on: [006-002-0003]
---

# POST /api/schema ist mit Symfony 4 nicht mehr erreichbar

## Context
Gefunden mit `006-002-0003` beim Stack-Wechsel:

```
No route found for "POST /api/schema": Method Not Allowed (Allow: OPTIONS, GET)
```

Unter Symfony 3.4 nahm die Route auch POST an, unter 4.4 nicht mehr. `RouteSecurityApiTest`
scheitert daran; weitere Aufrufer sind wahrscheinlich.

## Umfang
Zu klären ist zuerst, **welches Verhalten das gemeinte ist**:

- War POST je beabsichtigt? `/api/schema` liest nur — GET ist die passende Methode, und die
  Suite ruft es überwiegend per GET auf.
- Oder verlassen sich Clients darauf? Das Schema ist die Datei, an der jeder Client hängt;
  eine Methodenänderung ist für sie ein Bruch.

Erst danach die Entscheidung: Route um POST erweitern, oder POST als nie unterstützt
dokumentieren und die Aufrufer nachziehen.

**Ein Verdacht, der zu prüfen ist:** Unter Symfony 3.4 war das Routing toleranter. Wenn POST
dort nie definiert war und nur durchrutschte, ist 4.4 im Recht — dann ist das keine
Regression, sondern eine stillschweigende Zusicherung, die jetzt auffliegt.

## Abgrenzung
Keine Änderung am Inhalt des Schemas. Nur die Frage, über welche Methode es erreichbar ist.

## Acceptance criteria
- [x] Geklärt und begründet, ob POST unterstützt sein soll.
- [x] Alle Aufrufer im Repo (Tests eingeschlossen) benutzen die entschiedene Methode.
- [x] Ist POST nicht mehr unterstützt, steht es in `an_project/docs/breaking-changes.md`.
- [x] `RouteSecurityApiTest::testEineGesicherteRouteAntwortetMitToken` ist grün.

## Verification
Die Suite gegen den neuen Baum. Zusätzlich `grep` über `api/schema` im ganzen Repo — jede
Fundstelle benutzt die entschiedene Methode.

## Ergebnis
**Die Suite ist zum ersten Mal vollständig grün: `OK (249 tests, 605 assertions)`.**

Und der Verdacht aus dem Task-Text war richtig, in der schärferen Fassung: **POST war nie Teil
des Vertrags.** Es ist keine Regression, sondern eine stillschweigende Annahme, die aufgeflogen
ist.

### Der Beleg
| | |
|---|---|
| Routendefinition | `$controllers->get('/schema', "api.controller:schemaAction")` — **die einzige**, und so seit `b928409`, dem initialen Import |
| `git log -S "'/schema'"` | genau ein Commit: der initiale Import. POST wurde nie definiert und nie entfernt |
| apidoc-Annotation in `ApiController.php:789` | `@api {get} /api/schema` |
| Aufrufer im Repo | **20**, alle `$this->get(...)` — bis auf die eine Testzeile, um die es hier ging |

`/schema` und `/config` sind die einzigen beiden GET-Routen des Providers; die übrigen 17 sind
`post()`. Die Ausnahme ist also gewollt und konsistent: Das Schema wird gelesen.

### Warum es so lange durchging
Nicht, weil Symfony 3.4 toleranter war — sondern **weil die Zusicherung zu schwach war**:

```php
$this->assertNotSame(500, $status, 'Mit Token laeuft die Pruefung durch');
```

Ein „Method Not Allowed" ist **405**, und 405 ist nicht 500. Der Test war also grün, obwohl er
eine Route aufrief, die es unter dieser Methode nie gab. Er hat nichts charakterisiert.

Aufgefallen ist es erst, weil derselbe Fall seit dem Stack-Wechsel als **500** herauskommt.
Live nachgemessen:

```
GET  /api/schema  + Token  ->  HTTP 200
POST /api/schema  + Token  ->  HTTP 500
     {"message":"No route found for \"POST /api/schema\": Method Not Allowed (Allow: OPTIONS, GET)",
      "type":"Symfony\\Component\\HttpKernel\\Exception\\MethodNotAllowedHttpException"}
```

**Der Ausnahmetyp stimmt, nur der Statuscode wird überschrieben.** Das ist nicht dieser Task,
sondern `000-000-0006` — dort steht dieselbe Ursache schon zweimal beschrieben (404 statt 500,
401 statt 500). Sobald `0006` behoben ist, antwortet dieser Aufruf wieder mit 405.

> **Damit ist auch der Context dieses Tasks korrigiert.** Er sagt: *„Unter Symfony 3.4 nahm die
> Route auch POST an."* Das war eine Schlussfolgerung aus dem grünen Test, keine Beobachtung.
> Die Route nahm POST nie an; unter 3.4 wurde daraus 405, und 405 kam durch die Zusicherung.

### Was geändert wurde
Eine Zeile im Test — von `postJson` auf `get`. Und die Zusicherung von der Verneinung eines
einzelnen Fehlercodes auf das tatsächliche Ergebnis:

```php
[$status] = $this->get('/api/schema', $this->token());
$this->assertSame(200, $status, 'Mit Token laeuft die Pruefung durch');
```

**Die Verschärfung ist kein Beiwerk.** Der Klassenkommentar sagt, wozu es diese Tests gibt:
*„Auf diese Semantik verlässt sich Epic `009` beim Kernel-Tausch."* Eine Zusicherung, die jedes
Ergebnis ausser 500 durchlässt, belegt die `isSecure`-Semantik nicht — sie hat es ja gerade
nicht getan. Die Begründung steht als Kommentar am Test.

**Kein Eingriff in die Anwendung.** Die Route bleibt, wie sie ist; die Abgrenzung des Tasks ist
gewahrt.

### `breaking-changes.md`: kein Eintrag, und warum
Das Kriterium lautet *„Ist POST **nicht mehr** unterstützt …"*. Die Voraussetzung trifft nicht
zu: POST war nie unterstützt, auch nicht unter Symfony 3.4. Ein Bestandsprojekt kann sich also
nicht darauf verlassen haben — ein solcher Aufruf hat dort 405 bekommen.

Was ein Client heute anders sieht, ist der **Statuscode** dieses Fehlerfalls (405 → 500). Das
ist ein Defekt mit eigenem Ticket (`000-000-0006`), keine bewusste Änderung — und nach dessen
Behebung wieder 405. In eine Datei, aus der Epic `007` den Migrationsleitfaden baut, gehört das
nicht: Sie sammelt, was ein Projekt **anfassen** muss, und hier ist nichts anzufassen.

### Verification
| Prüfung | Ergebnis |
|---|---|
| volle Suite mit `CI=true` | **OK (249 tests, 605 assertions)** — 0 Failures, 0 übersprungen |
| `grep` über `api/schema` im ganzen Repo | 20 Aufrufer, **alle GET**; kein `postJson` mehr |
| Deprecation-Gate gegen das Serverlog | grün, 4 Paare, 4 ausgenommen |
| PHPStan | unverändert 47 Treffer |

Damit sind die sieben bekannten Failures Geschichte: sechs mit `000-000-0019`, die siebte hier.
