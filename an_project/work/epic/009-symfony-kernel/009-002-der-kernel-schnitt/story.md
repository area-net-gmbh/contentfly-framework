---
id: 009-002-0000
title: Der Kernel-Schnitt — Symfony 7.4 statt Silex
status: todo
depends_on: [009-001-0000, 009-005-0000]
---

# Der Kernel-Schnitt — Symfony 7.4 statt Silex

## Goal
Der Tausch selbst. Nach dieser Story bootet das Framework über einen Symfony-7.4-Kernel,
`silex/silex`, `pimple/pimple` und `knplabs/console-service-provider` sind aus `composer.lock`
verschwunden, und die Suite aus Epic `008` ist grün — **ohne inhaltliche Änderung**.

**Blockiert gewesen und wieder frei, sobald `009-005` steht.** Der erste Versuch von
`009-002-0001` scheiterte sofort an einem harten Konflikt: `symfony/http-foundation` verträgt
sich seit v7.1.7 nicht mit `doctrine/dbal <3.6`. Der Kernel-Wechsel erzwingt also DBAL 3 — was
die Abgrenzung des Epics ausgeschlossen hatte. Der Schritt ist zu `009-005` geworden und läuft
vorher, weil er sich als einziger Teil des Umbaus mit der vollen grünen Suite prüfen lässt.

**Diese Story ist bewusst eine und nicht fünf.** Silex 2.3 fordert die Symfony-Komponenten auf
`^4.0`; ein Zwischenstand, in dem Routing schon auf 7.4 und Middleware noch auf Silex läuft,
existiert nicht. Ein feinerer Schnitt in eigenständige Stories würde bei jedem `/done` einen
roten Stand nach `master` mergen. Die Tasks sind je ein Commit auf dem Story-Branch; grün ist
der Branch als Ganzes.

Was in ihr steckt, in dieser Reihenfolge:

- **Manifest und Container.** Symfony-7.4-Komponenten statt Silex, DI-Container statt Pimple,
  und der `ArrayAccess`-Bridge, der die bestehenden `$app['…']`-Schlüssel am Leben hält —
  einschließlich der Factory-Closures, die `custom/app.php` registriert.
- **Routing.** `RouteManager::mount()` und `bindRoutes()` bleiben als Schnittstelle; darunter
  Symfony Routing. `Route::$isSecure` überlebt als Authentifizierungsentscheidung pro Route.
- **Middleware und Fehler.** before/after zu `kernel.request`/`kernel.response`-Listenern, in
  **nachgewiesener** Reihenfolge, nicht in geratener. `$app->error()` zu einem
  Exception-Listener, der die Antwort aus `000-000-0006` unverändert ausgibt.
- **Console.** Symfony Console 7 ohne `knplabs/console-service-provider`; `ConsoleManager` und
  `bin/console.php` funktional. Dabei zu messen: ob die DBAL-2-Console-Commands unter Console 7
  noch laufen.
- **Einstiegspunkte.** `index.php`, `bootstrap.php`, `bootstrap-web.php`, `tests/router.php`.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [ ] 009-002-0001 — Das Manifest auf Symfony 7.4 umstellen
- [ ] 009-002-0002 — Container und Anwendung ohne Pimple
- [ ] 009-002-0003 — Routing auf Symfony Routing
- [ ] 009-002-0004 — Middleware und Fehlerbehandlung als Listener
- [ ] 009-002-0005 — Console ohne knplabs
- [ ] 009-002-0006 — Die Einstiegspunkte und der Nachweis
