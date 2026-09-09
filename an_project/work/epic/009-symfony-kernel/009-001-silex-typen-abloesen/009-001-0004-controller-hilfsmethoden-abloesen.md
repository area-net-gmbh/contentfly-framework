---
id: 009-001-0004
title: Die Silex-Hilfsmethoden in den Controllern ablösen
status: review
depends_on: [009-001-0001]
---

# Die Silex-Hilfsmethoden in den Controllern ablösen

## Context
Drei Aufrufe im `FileController` und `ApiController` benutzen Bequemlichkeiten von Silex, die
nichts weiter tun, als eine `Response` zu bauen:

- `$this->app->redirect($uri, 301)` — die Dateiauslieferung (`000-000-0006`).
- `$this->app->stream($fn, 200, $headers)` — die Auslieferung im `readfile`-Modus. Das
  Ergebnis ist eine `StreamedResponse`, und die ist der Grund, warum `bootstrap.php` bewusst
  **kein** `ob_start()` setzt (`000-000-0018`).
- `$this->app->handle($subRequest, HttpKernelInterface::SUB_REQUEST)` — zweimal in
  `ApiController::replaceAction()`. Das ist kein Silex-Aufruf, sondern `HttpKernelInterface`;
  er bleibt, aber er ist die Stelle, an der der Kernel-Wechsel am ehesten spürbar wird und
  gehört deshalb benannt.

## Acceptance criteria
- [x] `redirect()` und `stream()` sind durch die Symfony-Klassen ersetzt, die sie ohnehin
      zurückgeben (`RedirectResponse`, `StreamedResponse`) — kein Umweg über die Anwendung.
- [x] Der Zusammenhang zwischen `StreamedResponse` und dem fehlenden `ob_start()` steht an der
      Stelle, damit ihn niemand beim Aufräumen zerschneidet.
- [x] Die beiden Sub-Requests sind erklärt: was sie tun, warum `replace` sie braucht, und was
      beim Kernel-Wechsel an ihnen zu prüfen ist.
- [x] `FileApiTest` bleibt grün, einschliesslich `testAuslieferungLiefertDenInhalt()`, das der
      Umleitung end-to-end folgt.

## Verification
`./vendor/bin/phpunit --filter 'FileApiTest|UpdateReplaceApiTest'` grün, danach die volle Suite.

## Ergebnis

`redirect()` und `stream()` sind ersetzt durch die Klassen, die sie ohnehin zurückgaben —
Silex' Rümpfe lauten wörtlich `return new RedirectResponse($url, $status)` und
`return new StreamedResponse($callback, $status, $headers)`. Der Umweg über die Anwendung
brachte nichts und hätte `009-002` zwei Methoden mehr zum Nachbauen gegeben.

**Damit hatte die Schnittstelle aus `009-001-0001` keine Aufrufer mehr für diese beiden, und
sie sind wieder daraus verschwunden.** So war es dort angekündigt. Was bleibt, steht als
Vermerk im Klassenkommentar — eine Zusicherung ohne Aufrufer ist Ballast.

### Was an den beiden Sub-Requests wirklich zu prüfen ist

`replaceAction()` entscheidet nicht selbst zwischen Anlegen und Ändern, sondern schickt die
Anfrage **noch einmal durch die Anwendung** — an `/api/insert` oder `/api/update`. Das ist kein
Methodenaufruf, sondern ein vollständiger Request durch den Kernel: before- und after-Hooks
laufen ein zweites Mal, und der Ereignisname, den `BaseControllerProvider` daraus bildet, lautet
dann `pim.controller.before.api.insertaction`.

`handle()` kommt aus Symfonys `HttpKernelInterface` und bleibt wörtlich stehen. **Zu prüfen ist
etwas anderes:** ob `SUB_REQUEST` beim neuen Kernel dieselben Listener durchläuft. Symfony
unterscheidet Haupt- und Unteranfrage in `kernel.request`; ein Listener, der heute mitläuft, kann
dort übersprungen werden. Das wäre ein Verhaltenswechsel, den allein `UpdateReplaceApiTest`
sichtbar macht — deshalb steht der Hinweis jetzt im Code und nicht nur hier.

### Nachweis

| | |
|---|---|
| Volle Suite | `OK (247 tests, 614 assertions)`, 0 übersprungen |
| `FileApiTest` und `UpdateReplaceApiTest` | `OK (18 tests, 42 assertions)` — darunter die Auslieferung, die der Umleitung end-to-end folgt |
