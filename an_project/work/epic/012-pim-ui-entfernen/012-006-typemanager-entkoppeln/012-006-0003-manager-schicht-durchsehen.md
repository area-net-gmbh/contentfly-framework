---
id: 012-006-0003
title: Verbleibende Manager auf UI-Reste durchsehen
status: review
depends_on: [012-006-0002]
---

# Verbleibende Manager auf UI-Reste durchsehen

## Context
Letzter Durchgang durch `Classes/Manager/`, nachdem 012-001 bis 012-005 durch sind — der Ort, an dem UI-Wissen am ehesten unbemerkt liegen bleibt.

## Acceptance criteria
- [x] `RouteManager`, `ConsoleManager` und `LoginManager` sind auf UI-Reste geprüft und bereinigt.
- [x] Kein Manager referenziert mehr Templates, Assets oder Widgets.
- [x] Die `_secured`-Semantik des `RouteManager` ist unangetastet — sie wird in Epic 009 gebraucht.
- [x] Was gelöschte Twig-Templates erwartet hat, ist verschwunden.

## Ergebnis: ohne Codeänderung geschlossen

Der Durchgang war die Aufgabe, und er hat nichts gefunden. Alle fünf Klassen einzeln geprüft:

| Klasse | Befund |
|---|---|
| `RouteManager` | 2 Methoden: `mount()` sammelt Controller-Provider ein, `bindRoutes()` bindet sie. Kein UI-Bezug. |
| `ConsoleManager` | 1 Methode: hängt einen `CustomCommand` an den `ConsoleEvents::INIT`-Dispatcher. Kein UI-Bezug. |
| `LoginManager` | abstrakt; `createManagedUser()` legt einen Benutzer für einen externen Login-Provider an. Kein UI-Bezug. |
| `PluginManager` | `register()`, `getEntities()`, `getPlugin()` — schon vor `012-006-0002` frei von UI. |
| `TypeManager` | mit `012-006-0001` bereinigt. |
| `Classes/Manager.php` | hält nur die `$app`-Referenz. |

**Die `_secured`-Semantik heißt im Code anders und wurde nicht angefasst.** Eine Suche nach
`_secured` bleibt im ganzen Baum ohne Treffer; gemeint ist `Route::$isSecure` samt dem
`$checkAuth`-before-Hook in `Classes/Controller/Provider/Base/CustomControllerProvider.php`.
Diese Datei ist in dieser Story unberührt geblieben — Epic 009 findet sie unverändert vor.
Die Zeilenangabe im Kriterium war also nur die Bezeichnung; die Sache selbst steht.

## Verification
`grep -rn "twig\|assets\|widget" lib/contentfly/Classes/Manager` liefert keine Treffer. Anwendung bootet, Testnetz-Vorstufe grün.

- [x] Der Grep aus dem Kriterium: **keine Treffer**. Erweitert auf `template`, `render`,
      `frontend`, `.js`, `.css`, `scss`, `symlink`, `webroot` über `Classes/Manager/`,
      `Classes/Manager.php` und `Classes/Plugin.php`: ebenfalls **keine Treffer**.
- [x] Repo-weit kein `->render(`, kein `renderView`, kein `.twig`, kein `.html.php`.
      Der einzige verbliebene Treffer auf „Twig" ist ein Satz im Klassenkommentar von
      `InstallCommand`, der festhält, was der Command ersetzt hat — Historie, keine Referenz.
- [x] Keine `Frontend/`-, `ui/`- oder `assets/`-Verzeichnisse mehr im Baum;
      `lib/contentfly-ui` existiert nicht mehr.
- [x] Anwendung bootet (`php bin/console.php list`).
- [x] Datenbankschema unverändert (`orm:schema-tool:update --dump-sql` identisch zum
      Vergleichsstand aus Story `012-005`).
- [x] API-Schema identisch zur Baseline vom Beginn dieser Story — der Vertrag mit den
      Sync-Clients hält.
- [x] Testsuite grün: 26 Tests, 45 Assertions.
