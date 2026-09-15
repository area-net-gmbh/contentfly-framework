---
id: 000-000-0047
title: Doctrine-Proxies werden bei jedem Request neu geschrieben
status: review
depends_on: []
---

# Doctrine-Proxies werden bei jedem Request neu geschrieben

## Context
**Gefunden bei `007-005-0004`** am Bestandsprojekt UFP (Befund F-8), nach der Rückmeldung aus dem
Handtest „Listen / Daten laden langsamer“.

Der Bootstrap übergibt `(bool) APP_AUTOGENERATE_PROXIES` an den EntityManager. Die Vorgabe ist `true`,
und `true` ist `ProxyFactory::AUTOGENERATE_ALWAYS`: **Jeder Request schreibt die Proxy-Dateien neu.**
Der Cast macht zugleich jede andere Doctrine-Stufe unerreichbar, ein Projekt kann nur „immer“ oder
„nie“ wählen.

**Gemessen** (UFP, zehn Listen- und Auswertungsaufrufe, Median aus fünf Läufen, Summe): Contentfly 1.x
382 ms, Contentfly 2 540 ms, Contentfly 2 ohne Proxy-Erzeugung 330 ms. Parallele Requests schreiben
dieselbe Datei gleichzeitig; auf dem eingebundenen `data/` stand dabei
`require(...__CG__AreanetPIMEntityUser.php): Operation not permitted` in der Antwort.

**Entschieden am 2026-09-15:** Vorgabe `AUTOGENERATE_FILE_NOT_EXISTS_OR_CHANGED`; der Schalter nimmt
Doctrines Konstanten an, `true`/`false` behalten ihre Bedeutung.

## Acceptance criteria
- [x] `APP_AUTOGENERATE_PROXIES` wird nicht mehr auf `bool` reduziert; `true` → `ALWAYS`, `false` → `NEVER`, die Konstanten 0–4 gehen durch, alles andere wird mit klarer Meldung abgewiesen.
- [x] Die Vorgabe ist `AUTOGENERATE_FILE_NOT_EXISTS_OR_CHANGED`, im Bootstrap und in `appcms:install`.
- [x] Tests: Umsetzung des Schalters (Unit) und der Modus des gebauten EntityManagers (Integration).
- [x] `breaking-changes.md` beschreibt die neue Vorgabe und was ein Projekt tut, das `true` gesetzt hat.
- [x] Am UFP-Probe-Backend: dieselbe Messung ohne Projektänderung mindestens so schnell wie Contentfly 1.x.
- [x] Volle Suite, PHPStan, Deprecation-Gate grün.

## Verification
`ProxyGenerationTest`, `ProxyGenerationModeTest`. Zeitmessung `probe-timing.py` am UFP-Probe-Backend
gegen das alte Backend.

## Ergebnis

**`Classes\ORM\ProxyGeneration::mode()` ersetzt den `(bool)`-Cast** im Bootstrap und die feste `true`
in `appcms:install`. `true` → `AUTOGENERATE_ALWAYS`, `false` → `AUTOGENERATE_NEVER`, die Konstanten
0–4 gehen durch, jeder andere Wert (auch `'4'`, `null`, `1.0`) bricht mit einer Meldung ab, die den
Schalter nennt. Die Vorgabe in `Classes\Config` ist `ProxyGeneration::DEFAULT` =
`AUTOGENERATE_FILE_NOT_EXISTS_OR_CHANGED`. `EntityManagerFactory::create()` nimmt `int`.

**Tests:** `tests/Unit/ORM/ProxyGenerationTest.php` (Vorgabe, `true`/`false`, alle fünf Modi,
sechs ungültige Werte) und `tests/Integration/Database/ProxyGenerationModeTest.php` (der gebaute
EntityManager meldet Modus 4). **Gegenprobe** mit `bootstrap.php` und `Config.php` von master: der
Integrationstest rot (Modus 1).

**Register:** `breaking-changes.md`, Abschnitt Konfiguration, Eintrag „Proxies werden nur noch bei Bedarf
geschrieben“, mit dem Weg für Projekte, die `true` oder `false` setzen. Kopf von `migration.md` auf 110.

**Gemessen am UFP-Probe-Backend** (Framework-Stand dieses Branches, keine Projektänderung, ohne Debug,
Median aus fünf Läufen je Aufruf):

| | Contentfly 1.x | Contentfly 2 vorher | Contentfly 2 mit 0047 |
|---|---|---|---|
| 10 Listen-/Auswertungsaufrufe | 382 ms | 540 ms | — |
| alle 164 Aufrufe des Szenarios | 7224 ms | — | **5069 ms** |

Contentfly 2 ist damit rund 30 % schneller als 1.x statt langsamer. Der Paralleltest (Logout, `track`
und `complete` gleichzeitig, 20 Läufe) zeigt keine `Operation not permitted` mehr. Die Aufzeichnung
aller 164 Aufrufe ist inhaltlich unverändert gegenüber dem Stand vor 0047; die Abweichungen dort sind
Daten aus dem Handtest und aus dem Paralleltest (siehe `007-005-0004`).

**Verifiziert:** volle Suite `Tests: 577, Assertions: 1857, Skipped: 3`, PHPStan `[OK] No errors`,
Deprecation-Gate 0, `tools/check-template-config.sh` grün.
