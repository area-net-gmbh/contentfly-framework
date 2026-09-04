---
id: 009-000-0000
title: Silex durch Symfony 7.4 ersetzen
status: todo
depends_on: [006-000-0000, 008-000-0000, 012-000-0000]
---

# Silex durch Symfony 7.4 ersetzen

## Goal
Das Framework läuft auf einem **Symfony-7.4-LTS-Kernel**; Silex und Pimple sind aus dem Baum
verschwunden. Silex ist seit 2018 EOL und zieht Symfony-2/3-Komponenten herein, die nie für
PHP 8 freigegeben wurden — das ist der Kern des Updates. Kernel-Entscheidung und Begründung:
`an_project/docs/architecture.md`, *Key decisions*.

## Erfolgskriterien
- **Kernel und Container:** Symfony-7.4-Kernel mit DI-Container ersetzt
  `Silex\Application` + Pimple. Die Service-Provider-Pakete (`dflydev/doctrine-orm-service-provider`,
  `knplabs/console-service-provider`) entfallen ersatzlos.
- **`$app['…']` bleibt vorerst nutzbar:** Ein `ArrayAccess`-Bridge auf den Symfony-Container hält
  die bestehenden String-Keys am Leben, damit Bestandsprojekte ihre Controller nicht anfassen
  müssen. Ob das dauerhafte API oder befristete Migrationshilfe ist, entscheidet 007 — die
  Entscheidung wird dort getroffen und hier umgesetzt.
- **Routing:** `RouteManager` und die `mount()`-Struktur laufen über Symfony Routing; die
  `_secured`-Semantik bleibt erhalten. Die Routen der Vorlage `custom/app.php` werden als Daten
  eingelesen, nicht einzeln umgeschrieben.
- **Middleware und Fehler:** before/after-Hooks werden zu
  `kernel.request`/`kernel.response`-Listenern **in unveränderter Reihenfolge**; `$app->error()`
  wird zu einem Exception-Listener, der die bestehende Fehler-Envelope-Form unverändert ausgibt.
  CORS und Security-Header werden mit portiert; der Session-Bootstrap entfällt (012).
- **Console:** Die Commands laufen unter dem Symfony-Kernel; `ConsoleManager` bleibt funktional.
- **CI-Gate „0 Deprecations"** ist aktiv (Deprecation-Log + PHPStan) — Voraussetzung dafür, dass
  der spätere Sprung auf Symfony 8.4 LTS ein Constraint-Bump bleibt.
- Das Testnetz aus 008 ist grün.

## Abgrenzung
Die PIM-CMS-Oberfläche wird **nicht** portiert — sie ist mit 012 ersatzlos gestrichen. Was hier
auf Symfony landet, ist ausschließlich die API-Seite: Routing, Middleware, Fehlerbehandlung,
Auth und Console. Kein Twig, keine Session-Auth.

## Stories
<!-- Die Stories dieses Epics. Wird von /new-story synchron gehalten. -->
