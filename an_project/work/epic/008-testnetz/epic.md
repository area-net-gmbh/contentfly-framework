---
id: 008-000-0000
title: Testnetz für das Framework vor dem Umbau
status: in-progress
depends_on: [012-000-0000]
---

# Testnetz für das Framework vor dem Umbau

## Goal
Bevor Silex durch Symfony ersetzt wird, existiert ein Netz, das eine Verhaltensänderung sichtbar
macht. Einen Kernel auszutauschen, ohne dass irgendetwas anschlägt, wenn er sich anders verhält,
ist Blindflug.

> **Stand korrigiert am 2026-09-07.** Dieser Absatz behauptete, es gebe „keinen einzigen HTTP-,
> Routing- oder Middleware-Test". Das stimmt seit den Stories `012-003-0003` und `012-004-0003`
> nicht mehr: 26 Tests laufen, davon 20 Integrationstests über HTTP für die Datei-API und die
> Token-Authentifizierung. Das Loch liegt woanders und ist dafür größer — von den 17 Routen des
> `ApiController` hat genau **eine** Abdeckung (`/api/schema`). Die gesamte Datenschnittstelle,
> lesend wie schreibend, ist ungetestet.

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
- **Die Manager-Schicht als Vertrag**: `RouteManager`, `TypeManager`, `ConsoleManager`,
  `LoginManager` — hier hängt das Verhalten, das der neue Kernel reproduzieren muss. Die hier
  ursprünglich „`_secured`-Semantik" genannte Absicherung heißt im Code **`Route::$isSecure`** und
  liegt in `Classes/Controller/Provider/Base/CustomControllerProvider.php`, nicht im
  `RouteManager`; die Bezeichnung `_secured` kommt im Baum nicht vor (festgestellt in
  `012-006-0003`).
- **Die Vorlage `custom/` läuft mit**: Die Example-Artefakte werden vom Testnetz miterfasst, damit
  „ein Projekt auf Basis dieses Frameworks funktioniert" ebenfalls abgesichert ist.
- Die Suite läuft in der CI und ist grün gegen den heutigen Stand.

## Abgrenzung
Charakterisierung, keine Qualitätsverbesserung: Die Tests halten fest, **was ist** — auch wenn es
fragwürdig ist. Wünschenswertes Verhalten zu testen, würde das Netz wertlos machen.

## Stories
<!-- Die Stories dieses Epics. Wird von /new-story synchron gehalten. -->
- [ ] 008-001-0000 — Lesende API-Endpunkte charakterisieren
- [ ] 008-002-0000 — Schreibende API-Endpunkte charakterisieren
- [ ] 008-003-0000 — Auth, Berechtigungen und Routen-Absicherung
- [ ] 008-004-0000 — Manager-Schicht, Systemendpunkte und die Vorlage
- [ ] 008-005-0000 — Suite in der CI verankern und als Abnahmegrundlage festschreiben
