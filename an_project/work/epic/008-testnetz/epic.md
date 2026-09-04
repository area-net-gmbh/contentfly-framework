---
id: 008-000-0000
title: Testnetz für das Framework vor dem Umbau
status: todo
depends_on: [012-000-0000]
---

# Testnetz für das Framework vor dem Umbau

## Goal
Bevor Silex durch Symfony ersetzt wird, existiert ein Netz, das eine Verhaltensänderung sichtbar
macht. Heute gibt es Unit-Tests auf Service-Ebene und **keinen einzigen HTTP-, Routing- oder
Middleware-Test** — einen Kernel auszutauschen, ohne dass irgendetwas anschlägt, wenn er sich
anders verhält, ist Blindflug.

Der Prüfstein ist der **heutige Silex-Stand**: Was die Suite dort zeigt, muss sie auf dem
Symfony-Kernel identisch zeigen. Damit wird sie zur Abnahmegrundlage für 009 und 011 — und
zugleich zur Referenz, mit der ein Bestandsprojekt (007) prüfen kann, ob sein eigener Umstieg
geglückt ist.

## Erfolgskriterien
- **HTTP-Ebene abgedeckt** für die Controller, die 012 überleben: `ApiController` (die Sync-/
  Datenschnittstelle, das eigentliche Produkt), `AuthController`, `SystemController` und der
  verbliebene API-Teil des `FileController`. `UiController`, `InstallController` und
  `ExportController` werden **nicht** getestet — sie sind mit 012 gelöscht. Deshalb hängt dieses
  Epic an 012: kein Testnetz für Code, der wegfällt.
- **Auth und Berechtigungen**: Zugriff ohne gültige Anmeldung, Rollen- und
  `Permission`/`I18nPermission`-Gating in ihrem heutigen Verhalten festgehalten.
- **Die Manager-Schicht als Vertrag**: `RouteManager` (inkl. der `_secured`-Semantik),
  `TypeManager`, `ConsoleManager`, `LoginManager` — hier hängt das Verhalten, das der neue Kernel
  reproduzieren muss.
- **Die Vorlage `custom/` läuft mit**: Die Example-Artefakte werden vom Testnetz miterfasst, damit
  „ein Projekt auf Basis dieses Frameworks funktioniert" ebenfalls abgesichert ist.
- Die Suite läuft in der CI und ist grün gegen den heutigen Stand.

## Abgrenzung
Charakterisierung, keine Qualitätsverbesserung: Die Tests halten fest, **was ist** — auch wenn es
fragwürdig ist. Wünschenswertes Verhalten zu testen, würde das Netz wertlos machen.

## Stories
<!-- Die Stories dieses Epics. Wird von /new-story synchron gehalten. -->
