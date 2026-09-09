---
id: 009-001-0004
title: Die Silex-Hilfsmethoden in den Controllern ablösen
status: todo
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
- [ ] `redirect()` und `stream()` sind durch die Symfony-Klassen ersetzt, die sie ohnehin
      zurückgeben (`RedirectResponse`, `StreamedResponse`) — kein Umweg über die Anwendung.
- [ ] Der Zusammenhang zwischen `StreamedResponse` und dem fehlenden `ob_start()` steht an der
      Stelle, damit ihn niemand beim Aufräumen zerschneidet.
- [ ] Die beiden Sub-Requests sind erklärt: was sie tun, warum `replace` sie braucht, und was
      beim Kernel-Wechsel an ihnen zu prüfen ist.
- [ ] `FileApiTest` bleibt grün, einschliesslich `testAuslieferungLiefertDenInhalt()`, das der
      Umleitung end-to-end folgt.

## Verification
`./vendor/bin/phpunit --filter 'FileApiTest|UpdateReplaceApiTest'` grün, danach die volle Suite.
