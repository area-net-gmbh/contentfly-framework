---
id: 008-003-0004
title: Sprachrechte und die Routen-Absicherung
status: done
depends_on: [008-003-0001]
---

# Sprachrechte und die Routen-Absicherung

## Context
Zwei Absicherungen, die nichts miteinander zu tun haben, aber beide klein sind: die Sprachrechte
einer Gruppe und der Schalter, der eine Route hinter den Token stellt. Der zweite ist der, auf
den sich Epic `009` beim Kernel-Tausch verlässt.

## Umfang

### A — Sprachrechte
`Group::getLanguages()` liefert eine Zuordnung Sprache → Recht; darauf setzen drei Methoden auf:

| Methode | liefert `true`, wenn |
|---|---|
| `langIsWritable($lang)` | keine Sprachrechte gesetzt sind **oder** für diese Sprache kein Eintrag existiert |
| `langIsTranslatable($lang)` | dito, sonst wenn der Eintrag `IS_TRANSLATABALE` ist |
| `langisOnlyReadable($lang)` | weder schreibbar noch übersetzbar |

Durchgesetzt wird das an genau einer Stelle: `Api.php:122` prüft
`I18nPermission::isWritable()` beim Löschen.

**Zu erwarten ist, dass sich das heute nicht auslösen lässt.** `008-001-0005` hat festgestellt:
`APP_LANGUAGES` ist in der Vorlage leer, und es gibt keine konkrete `BaseI18n`-Entity — nur die
abstrakten Basisklassen. Ohne Sprachen gibt es keine Sprachrechte zu prüfen.

Dann gilt dasselbe Vorgehen wie bei den anderen Lücken dieses Epics: ein Test auf die
**Vorbedingung**, der anschlägt, sobald Mehrsprachigkeit eingeschaltet wird. Die drei
`Group::lang*`-Methoden lassen sich zusätzlich als **Unit-Test** abdecken — sie hängen an
nichts als der Gruppe selbst und brauchen weder Datenbank noch HTTP. Das wäre der erste
Unit-Test dieses Epics und die ehrlichere Abdeckung als ein Integrationstest ins Leere.

> Beachten: `langIsWritable()` liefert `true`, wenn für eine Sprache **kein** Eintrag existiert
> — die Vorgabe ist also „erlaubt", nicht „verboten". Ob das beabsichtigt ist, steht hier nicht
> zur Debatte; es ist festzuhalten.

### B — Die Routen-Absicherung
Der Schalter heißt `Route::$isSecure` und liegt in
`Classes/Controller/Provider/Base/CustomControllerProvider.php` — **nicht** `_secured` im
`RouteManager`, wie der Epic-Text ursprünglich annahm (korrigiert in `012-006-0003`).

Zu prüfen sind beide Richtungen:
- Eine mit `isSecure = true` gebundene Route weist ohne gültigen Token ab. Belegt schon durch
  die vorhandenen Tests, hier aber als **Semantik** festgehalten.
- Eine mit `isSecure = false` gebundene Route **antwortet ohne Token**. Der Beleg dafür steht
  in der Vorlage bereit: `POST api/v1/example/bootstrap` aus `custom/app.php`.

Das ist der Vertrag, den Epic `009` reproduzieren muss: Ein Projekt baut damit seine
öffentlichen Endpunkte.

Dazu die `before`/`after`-Hooks aus `custom/app.php` — der `Referrer-Policy`-Header ist der
einfachste Nachweis, dass sie greifen.

## Acceptance criteria
- [x] Die drei `Group::lang*`-Methoden sind abgedeckt — als Unit-Test, wenn ein
      Integrationstest mangels konfigurierter Sprachen ins Leere liefe.
- [x] Die Vorgabe „kein Eintrag heißt erlaubt" ist festgehalten.
- [x] Lässt sich `I18nPermission::isWritable()` über die API nicht auslösen, ist das durch
      einen Test auf die Vorbedingung belegt und im Kommentar begründet.
- [x] Eine gesicherte Route weist ohne Token ab — als Semantik festgehalten, mit Verweis auf
      `Route::$isSecure`.
- [x] `POST api/v1/example/bootstrap` antwortet **ohne** Token.
- [x] Die `after`-Hooks aus `custom/app.php` greifen (`Referrer-Policy`).

## Verification
Mehrere vollständige Läufe. Der Unit-Test-Anteil muss auch **ohne** laufende Umgebung grün sein
— das ist der Punkt der Suite-Trennung aus `tests/README.md`.

## Ergebnis — 9 Unit-Tests und 9 Integrationstests

Gesamtsuite: **147 Tests, 350 Assertions**, vier Läufe grün. Die Unit-Suite wächst von 6 auf
**15 Tests** und läuft weiterhin ohne Datenbank und ohne Server.

### Der erste Unit-Test des Epics
`tests/Unit/Entity/GroupLanguagePermissionTest.php` deckt die drei `Group::lang*`-Methoden ab.
Ein Integrationstest liefe hier ins Leere — `APP_LANGUAGES` ist leer, es gibt keine konkrete
`BaseI18n`-Entity. Die Logik hängt aber an nichts als der Gruppe selbst, also ist ein
Unit-Test die ehrlichere Abdeckung.

Festgehalten, weil es überrascht: **Die Vorgabe ist „erlaubt", nicht „verboten".** Sind
Sprachrechte gesetzt, eine Sprache aber nicht darunter aufgeführt, gilt sie als schreibbar. Wer
eine Sprache sperren will, muss sie ausdrücklich nennen. Dazu ein zweites Detail:
`setLanguages()` ignoriert falsy Werte — ein leeres Array **löscht** die Rechte nicht, es lässt
sie unberührt.

### Die Routen-Absicherung, beide Richtungen
- Gesicherte Route ohne Token: keine Daten.
- **Ungesicherte Route ohne Token: antwortet** — `POST api/v1/example/bootstrap` aus
  `custom/app.php`, gebunden mit `isSecure = false`. Das ist der Vertrag, auf den sich Epic
  `009` verlässt; ein Projekt baut damit seine öffentlichen Endpunkte.
- `isSecure = false` heißt „Token nicht nötig", nicht „Token verboten" — mit Token antwortet
  sie ebenso.
- Die `after`-Hooks der Vorlage greifen auf gesicherten wie ungesicherten Routen
  (`Referrer-Policy`).

### Drei Befunde, die aus meinen eigenen Fehlannahmen entstanden
Der erste Lauf hatte drei Fehlschläge — alle drei waren falsche Erwartungen von mir, und alle
drei sind es wert:

1. **`/api/list` auf eine leere Menge antwortet mit HTTP 404 `{"message":"Not found"}`** — nicht
   mit einer leeren Liste. In `008-001-0002` nicht aufgefallen, weil dort immer Testdaten
   vorhanden waren. Für einen Sync-Client sind „keine Treffer" und „Route gibt es nicht" damit
   **nicht unterscheidbar**.
2. **`api/v1/example/bootstrap` antwortet im eigenen Envelope der Vorlage** — `success`,
   `status`, `i18n`, `data`, `errors`, `meta`, `timestamp` — nicht im Format des Frameworks. Ein
   Projekt ist an dessen Form also nicht gebunden. Das ist eher eine gute Nachricht.
3. **`/api/config` trägt den siebten eigenen Envelope** (`frontend`, `devmode`, `version`,
   `hash`) und bewirbt in `frontend` weiterhin `customLogo` — einen Rest der gelöschten
   Oberfläche, auf dem einzigen öffentlich erreichbaren Endpunkt.

## Verification
- [x] Vier vollständige Läufe grün bei zufälliger Ausführungsreihenfolge.
- [x] Die Unit-Suite läuft **ohne** Umgebung: 15 Tests, 20 Assertions.
- [x] Der Leere-Liste-Test ist zerstörungsfrei — er nutzt `PIM\Nav`, das nach einer frischen
      Installation leer ist, statt Zeilen zu löschen.
- [x] Datenbank nach den Läufen auf dem Ausgangsstand.
