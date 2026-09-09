---
id: 000-000-0010
title: UI-Reste aus Konfiguration und Schema entfernen
status: done
depends_on: []
---

# UI-Reste aus Konfiguration und Schema entfernen

## Context
Epic `012` hat die PIM-Oberfläche entfernt, aber vier Rückstände bewusst stehen lassen, weil
sie außerhalb des jeweiligen Story-Umfangs lagen. Das Testnetz aus Epic `008` hat sie einzeln
belegt. Jetzt gibt es die Absicherung, um sie ohne Risiko zu ziehen.

## Umfang

### A — Die übrigen `FRONTEND_*`-Einstellungen
`Classes/Config.php` führt weiterhin `FRONTEND_UI`, `FRONTEND_TITLE`,
`FRONTEND_CUSTOM_NAVIGATION`, `FRONTEND_WELCOME`, `FRONTEND_URL`, `FRONTEND_LOGIN_REDIRECT`,
`FRONTEND_CUSTOM_LOGO`, `FRONTEND_CUSTOM_LOGIN_BG`, `FRONTEND_FORM_IMAGE_SQUARE_PREVIEW` und
`FRONTEND_ITEMS_PER_PAGE`. Story `012-005-0004` hat nur die drei entfernt, die sie selbst
verwaist hatte, und den Rest ausdrücklich als eigenen Task vermerkt.

### B — `customLogo` in `/api/config`
`Api::getExtendedSchema()` liefert im `frontend`-Block noch `customLogo`. Das ist der
**einzige öffentlich erreichbare Endpunkt** (keine Token-Pflicht) — dass er eine Eigenschaft
der gelöschten Oberfläche bewirbt, hat `008-003-0004` festgehalten.

### C — Die Schema-Keys `multipe` und `multiple`
An neun Stellen gesetzt, davon sechs unter dem Tippfehler `multipe`, und **an keiner Stelle
gelesen**. Ein reiner Widget-Hinweis. Story `012-006-0001` hat sie stehen gelassen, weil ihre
Entfernung das API-Schema geändert hätte und jene Story das ausschloss.

### D — `Type::$insertCallback` und `$updateCallback`
Zwei öffentliche Felder der abstrakten Basisklasse, die **niemand setzt und niemand liest**
(festgestellt in `012-006-0001`).

## Warum das jetzt geht
Die Punkte B und C ändern das **API-Schema**. Bis Epic `008` gab es dafür keinen Maßstab;
inzwischen halten `ReadApiTest`, `TreeApiTest` und die übrigen fest, was die API liefert. Eine
Änderung fällt damit auf, statt still zu passieren.

## Acceptance criteria
- [x] Die übrigen `FRONTEND_*`-Einstellungen sind entfernt — oder es ist begründet
      festgehalten, welche bleiben und warum.
- [x] `/api/config` bewirbt kein `customLogo` mehr; der `frontend`-Block ist entweder leer
      oder ganz entfallen.
- [x] `multipe` und `multiple` sind aus allen Typ-Klassen entfernt.
- [x] `Type::$insertCallback` und `$updateCallback` sind entfernt.
- [x] Die betroffenen Charakterisierungstests sind **bewusst umgedreht**, nicht gelöscht —
      insbesondere `RouteSecurityApiTest::testApiConfigIstOhneTokenErreichbar()`.
- [x] Die Änderungen am API-Schema sind in `an_project/docs/pim-annotationen-migration.md` als
      Breaking Change für Epic `007` vermerkt.

## Verification
Die Suite aus Epic `008` grün, mit den umgedrehten Zusicherungen. Zusätzlich das Schema vor
und nach der Änderung vergleichen, wie es `012-005-0003` getan hat: Es dürfen **nur** die vier
oben genannten Schlüssel verschwinden.

## Ergebnis
**64 Pfade verschwinden aus dem API-Schema, keiner kommt hinzu, und die Suite bleibt grün.**
Der Schema-Vergleich vorher/nachher ist die eigentliche Abnahme dieses Tasks — er zeigt genau
die vier Punkte und nichts sonst.

### Der Schema-Vergleich
| | vorher | nachher |
|---|---|---|
| Pfade im Schema | 2 148 | 2 084 |

**Verschwunden (64), neu (0):**

| Schlüssel | Vorkommen |
|---|---|
| `multipe` | 27 |
| `multiple` | 32 |
| `customLogo` · `formImageSquarePreview` · `title` · `welcome` · `login_redirect` | je 1 |

Die fünf Einzelnen sind der `frontend`-Block, die 59 anderen sind Punkt C. Kein Entity-Feld,
kein `properties`-Eintrag, nichts Unbeabsichtigtes.

### A — Acht von zehn `FRONTEND_*`-Feldern
Drei wurden **nirgends** gelesen (`FRONTEND_UI`, `FRONTEND_URL`, `FRONTEND_CUSTOM_LOGIN_BG`),
fünf nur vom `frontend`-Block des Schemas.

**Zwei bleiben, und das ist der interessante Teil.** Sie tragen `FRONTEND_` im Namen, steuern
aber kein Frontend:

| Feld | was es wirklich tut |
|---|---|
| `FRONTEND_ITEMS_PER_PAGE` | Standard-Seitengrösse der Pagination in `ApiController::listAction()` — reines API-Verhalten |
| `FRONTEND_CUSTOM_NAVIGATION` | schaltet einen Zweig frei, der `PIM\Nav` und `PIM\NavItem` ausliest — beide Entities gibt es, drei Testdateien berühren sie |

Wer nach dem Präfix gegangen wäre statt nach den Konsumenten, hätte die Seitengrösse der API
entfernt. Das Kriterium liess diesen Ausgang ausdrücklich zu; die Begründung steht als
Kommentar an beiden Feldern.

Umbenennen wäre die sauberere Lösung und ist es hier trotzdem nicht: Das wäre ein zweiter
Bruch für jedes Bestandsprojekt, ohne Gegenwert. Gehört zu Epic `007`, wenn überhaupt.

### B — Der Task-Text verwechselt zwei Endpunkte
Er sagt: *„`Api::getExtendedSchema()` liefert im `frontend`-Block noch `customLogo`. Das ist
der **einzige öffentlich erreichbare Endpunkt**."* Das sind zwei verschiedene Dinge:

| | Endpunkt | Token | `frontend`-Block |
|---|---|---|---|
| `getExtendedSchema()` | `/api/schema` **und** die Login-Antwort | **ja** | 7 Schlüssel |
| `configAction()` | `/api/config` | **nein** | 1 Schlüssel (`customLogo`) |

Öffentlich ist `/api/config`, nicht `getExtendedSchema()`. Das Akzeptanzkriterium nennt
richtigerweise `/api/config`, und der Test sagt es auch. Beide wurden behandelt:

- **`/api/config`**: `frontend` ist **ganz entfallen**, nicht geleert. Ein Schlüssel, der
  nichts mehr trägt, lädt dazu ein, wieder etwas hineinzulegen — und auf dem einzigen
  tokenfreien Endpunkt ist das die falsche Einladung.
- **`getExtendedSchema()`**: von sieben Schlüsseln auf zwei. Geblieben sind `customNavigation`
  (Daten) und `languages` (aus `APP_LANGUAGES`, bestimmt über `bootstrap.php` die
  Hauptsprache). Der Schlüsselname `frontend` bleibt — ihn umzubenennen wäre ein weiterer
  Bruch ohne Gewinn.

### C — Neun Zeilen, die nie jemand gelesen hat
Sechs unter dem Tippfehler `multipe`, drei als `multiple`. **Ausschliesslich geschrieben, an
keiner Stelle gelesen** — nachgeprüft über `lib/`, `custom/` und `tests/`. Im ausgelieferten
Schema waren es 59 Vorkommen.

Der Tippfehler ist dabei das Argument: Auf welchen der beiden Schlüssel ein Typ hörte, war
Zufall. Ein Client, der sie auswertete, wertete etwas Zufälliges aus.

### D — Zwei Felder ohne Leser und ohne Schreiber
`Type::$insertCallback` und `$updateCallback`, entfernt. Sie tauchten im Schema nie auf; der
Vergleich oben zeigt sie deshalb nicht.

### Der Test ist umgedreht, nicht gelöscht
`testApiConfigIstOhneTokenErreichbar()` trug den Satz: *„Rest der gelöschten Oberfläche: Der
öffentliche Endpunkt bewirbt weiterhin customLogo. Festgehalten, nicht bereinigt — das wäre
ein eigener Task."*

Das war dieser Task. Der Test prüft jetzt das Gegenteil — dass der Envelope nur noch
`devmode`, `version` und `hash` trägt und `frontend` **nicht** mehr enthält — und der
Kommentar hält fest, was vorher dort stand und warum es weg ist.

### Zwei Dokumente statt einem
Das Kriterium nennt `pim-annotationen-migration.md`. Dort steht der Nachtrag zu Abschnitt 6:
Die acht Felder mit Begründung je Zeile, und warum zwei bleiben — Abschnitt 6 listete bereits
zwei entfallene `FRONTEND_*`-Felder aus `012-005-0004`, das ist die richtige Stelle.

**Zusätzlich in `breaking-changes.md`**, weil dort die allgemeine Sammlung liegt und ihr eigener
Kopf sagt, sie werde *„laufend ergänzt, sobald eine Änderung Bestandsprojekte trifft"*. Sie
bekam einen neuen Abschnitt **API** mit den beiden Antwortformat-Änderungen und einen Eintrag
unter *Konfiguration*, der auf die Migrationsdatei verweist. Epic `007` baut aus dieser Datei
den Migrationsleitfaden — eine Schema-Änderung nur in einem Annotationen-Dokument zu vermerken
hiesse, sie dort nicht zu finden.

### Verification
| Prüfung | Ergebnis |
|---|---|
| Schema-Vergleich vorher/nachher | 64 Pfade weg, **0 neu** — nur die genannten Schlüssel |
| `/api/config` | `{"devmode":…,"version":…,"hash":…}`, kein `frontend` mehr |
| volle Suite mit `CI=true` | **OK (249 tests, 605 assertions)**, 0 übersprungen |
| PHPStan | unverändert 47 Treffer |
| Deprecation-Gate | grün, 4 Paare, 4 ausgenommen |

Die Suite ist der Punkt, an dem der Task hing: `012-005-0004` und `012-006-0001` haben diese
vier Rückstände stehen lassen, **weil es damals keinen Massstab für eine Schema-Änderung gab**.
Jetzt gibt es ihn, und er zeigt, dass nur verschwunden ist, was verschwinden sollte.
