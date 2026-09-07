---
id: 008-001-0000
title: Lesende API-Endpunkte charakterisieren
status: done
depends_on: []
---

# Lesende API-Endpunkte charakterisieren

## Goal
Die Leseseite der Datenschnittstelle bekommt ein Netz. Neun Routen liefern heute Daten an
Sync-Clients, und **keine einzige** ist getestet — was sie zurückgeben, weiß nur der Code. Diese
Story hält es fest, damit Epic `009` beim Kernel-Tausch etwas hat, woran sich „unverändert"
messen lässt.

## Umfang

### Die Routen
Alle über `POST` mit `appcms-token`-Header, gebunden in
`Classes/Controller/Provider/Base/ApiControllerProvider.php`:

| Route | Was festzuhalten ist |
|---|---|
| `/api/single` | Einzelobjekt: Feldmenge, Verschachtelung, `pim_blocked` bei fehlender Leseberechtigung |
| `/api/list` | Liste: Sortierung aus `sortBy`/`sortOrder`, Filter, Paginierung, `partial`-Select der Join-Felder |
| `/api/tree` | Baum über `treeChilds`, rekursiv, mit `properties`-Einschränkung aus dem Request |
| `/api/tree2` | Flacher Baum über die `pim_tree`-Tabelle — **liefert seit `012-005` alle skalaren Felder** statt einer Spaltenauswahl |
| `/api/all` | Sync-Abruf; hier greift `excludeFromSync` |
| `/api/count` | Trefferzahl zu denselben Filtern wie `/api/list` |
| `/api/deleted` | gelöschte Objekte seit einem Zeitstempel — die zweite Hälfte des Sync-Vertrags |
| `/api/translations` | i18n-Varianten, `i18n_universal`-Felder |
| `/api/query` | freies DQL-artiges Abfragen; **nur für Admins oder Gruppen mit `apiQueryEnabled`** |

### Was die Tests festhalten müssen
- **Die Form der Antwort**, nicht nur den Statuscode: Der Envelope (`ts`, `data`, `version`,
  `hash`), die Datumsdarstellung (`LOCAL`, `LOCAL_TIME`, `ISO8601`, `TIMESTAMP` — vier Felder je
  `datetime`), und dass Zeitwerte als `H:i` kommen.
- **Die Verschachtelungstiefe.** Verschachtelte Objekte liefern seit `012-005` **alle**
  Eigenschaften; begrenzt wird nur über `DB_NESTED_LEVELS`. Das ist frisches Verhalten und
  gehört genau deshalb festgenagelt.
- **Die Fehlerfälle in ihrer heutigen Form.** Unbekannte Entity, unbekannte ID, fehlender
  Token. Achtung: Task `000-000-0006` hält fest, dass manche davon heute als **HTTP 500** statt
  404/401 herauskommen. Das ist zu **charakterisieren, nicht zu reparieren** — die Reparatur ist
  ein eigener Task, und ein Test, der Wunschverhalten prüft, wäre kein Netz.

### Vorbedingungen, die schon stehen
Die Infrastruktur ist da und muss nicht erfunden werden: `tests/router.php`, die Trennung in
`tests/Unit` und `tests/Integration`, das Überspringen ohne `CONTENTFLY_TEST_BASE_URL`, und die
Muster aus `AuthApiTest` und `FileApiTest`. Beschrieben in `tests/README.md`.

Testdaten legt die Suite selbst an — über die Schreib-Endpunkte oder direkt über die Datenbank.
Welcher Weg, ist beim Umsetzen zu entscheiden; er darf die Story nicht von `008-002` abhängig
machen.

## Fertig, wenn
- Jede der neun Routen hat mindestens einen Test für den Erfolgsfall und einen für den
  wichtigsten Fehlerfall.
- Die Form der Antwort ist geprüft, nicht nur der Statuscode.
- Die vier Datumsfelder und das Zeitformat sind festgehalten.
- Die Verschachtelung verschachtelter Objekte ist festgehalten.
- Das Admin-Gating von `/api/query` ist geprüft.
- Wo das heutige Verhalten fragwürdig ist (500 statt 404), hält der Test **den Ist-Zustand**
  fest und verweist im Kommentar auf `000-000-0006`.
- Die Suite läuft gegen den heutigen Silex-Stand grün.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [x] 008-001-0001 — Gemeinsame Basis für die Integrationstests
- [x] 008-001-0002 — /api/single und /api/list festhalten
- [x] 008-001-0003 — Baum-Endpunkte /api/tree und /api/tree2
- [x] 008-001-0004 — Sync-Endpunkte /api/all, /api/deleted und /api/count
- [x] 008-001-0005 — /api/translations und /api/query
