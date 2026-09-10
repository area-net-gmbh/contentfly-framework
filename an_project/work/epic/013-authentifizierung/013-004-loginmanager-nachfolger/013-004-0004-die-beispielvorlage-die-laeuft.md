---
id: 013-004-0004
title: Die Beispiel-Vorlage, die wirklich läuft
status: review
depends_on: [013-004-0002, 013-004-0003]
---

# Die Beispiel-Vorlage, die wirklich läuft

## Context
`custom/Classes/` enthaelt heute zwei Service-Klassen und keinen einzigen Provider. Die Story
verlangt eine Vorlage, die **wirklich laeuft** — und das ist mehr als ein Codeschnipsel im
Kommentar: Eine Vorlage, die nie ausgefuehrt wird, ist eine Behauptung.

Sie ist zugleich der einzige Weg, den ganzen Pfad end-to-end zu pruefen. Bis hierhin sind
Vertrag, Provisionierung und Abbildung je fuer sich gemessen; erst ein echter Provider zeigt,
dass sie zusammenpassen.

**Was die Vorlage nicht sein darf:** ein Zugang, den eine Installation versehentlich offen
laesst. Sie prueft gegen etwas, das ohne ausdrueckliche Konfiguration nichts durchlaesst, und sie
sagt in ihrem eigenen Text, dass sie eine Vorlage ist.

## Acceptance criteria
- [x] In `custom/` steht ein Provider, der den neuen Vertrag erfuellt und ueber HTTP eine Anmeldung durchfuehrt.
- [x] Ein Integrationstest meldet sich ueber ihn an und bekommt einen Token — derselbe Weg, den ein Projekt gehen wuerde.
- [x] Ohne ausdrueckliche Konfiguration laesst die Vorlage **niemanden** herein. Ein Test belegt es.
- [x] Der Vorlagentext sagt, was ein Projekt daran aendern muss, und was es nicht anfassen sollte.
- [x] `tools/check-template-config.sh` bleibt gruen.

## Verification
Ein Integrationstest ueber den vollen Weg: registrieren, anmelden, Token vorzeigen, geschuetzte
Route oeffnen. Ein zweiter ohne Konfiguration, der eine Abweisung erwartet. Volle Suite.

## Ergebnis

**`custom/Classes/Anmeldung/BeispielProvider.php` läuft wirklich** — und der Test geht damit den
ganzen Weg: registrieren, anmelden, Token vorzeigen, geschützte Route öffnen.

### Erst hier passt zusammen, was vorher einzeln gemessen war

`0001` bis `0003` haben Vertrag, Provisionierung und Gruppenabbildung je für sich geprüft. Drei
Tasks lang stand im Ergebnis derselbe Satz: Der end-to-end-Nachweis braucht einen Provider, den
man wirklich laufen lassen kann. Den gab es nicht — `custom/Classes/` enthielt zwei
Service-Klassen und keinen Provider.

Acht Tests lösen das ein:

| Probe | Ergebnis |
|---|---|
| Anmeldung über `loginManager: beispiel` | 200 mit Token, der `/api/schema` öffnet |
| Die angelegte Zeile | `pass = '*'`, `externalId` lesbar, Alias `beispiel:<kennung>` |
| Kennung, Alias oder `*` als Passwort | drei Versuche, dreimal 401 |
| Zweite Anmeldung | genau eine Zeile, kein zweites Konto |
| Falsches Geheimnis, unbekannte Kennung | 401 |
| Ohne Konfiguration | der Provider lässt niemanden herein |
| Ohne Abbildung | keine Gruppe, keine Adminrechte |

### Eine Vorlage, die niemanden versehentlich hereinlässt

Sie prüft gegen eine Liste aus `CONTENTFLY_BEISPIEL_PROVIDER`. Ist die Variable nicht gesetzt,
ist die Liste leer, und **jede** Anmeldung wird abgelehnt. Eine Vorlage, die eine Installation
versehentlich offen liesse, wäre schlimmer als gar keine.

Das Format `kennung:geheimnis:gruppe|gruppe` ist ein **Platzhalter für ein Fremdsystem und kein
Vorschlag** — Geheimnisse in einer Umgebungsvariablen sind für einen Test in Ordnung und für den
Betrieb nicht. Der Vorlagentext sagt das, und er sagt auch, was ein Projekt ändert (`pruefen()`)
und was nicht (den Rückgabetyp, und die Gruppenabbildung, die in die Konfiguration gehört).

`hash_equals()` statt `===`: Ein Vergleich, der beim ersten abweichenden Zeichen abbricht, verrät
über seine Laufzeit, wie weit man richtig lag. Bei einem Geheimnis dieser Art ist das die ganze
Prüfung.

### Der eine Test, der nicht über HTTP läuft — und warum

`testOhneKonfigurationLaesstDieVorlageNiemandenHerein` misst an der Klasse, nicht am Server. Der
Testserver hat die Variable gesetzt, und ihn dafür neu zu starten hiesse, die halbe Suite
anzuhalten. Es ist dieselbe Klasse, die dort läuft, und die Liste kommt aus derselben Zeile.

### Die Testumgebung kennt die Variable jetzt

`tools/ci/prepare-test-environment.sh` verlangt `CONTENTFLY_TEST_PROVIDER` und reicht sie als
`CONTENTFLY_BEISPIEL_PROVIDER` an den Testserver — dieselbe Trennung wie beim
JWT-Signaturgeheimnis: `CONTENTFLY_TEST_*` ist die Umgebung des Testlaufs,
`CONTENTFLY_BEISPIEL_PROVIDER` die der Anwendung. `tests/README.md` führt beide Hälften auf,
weil der von Hand nachgespielte Lauf sonst zwei Tests überspringt, ohne dass jemand merkt warum.

### Nachweis

| Probe | Ergebnis |
|---|---|
| Volle Suite | `OK (442 tests, 1098 assertions)`, 0 übersprungen (vorher 434) |
| PHPStan | `[OK] No errors` |
| Deprecations | 0 protokollierte Zeilen |
| `tools/check-template-config.sh` | Exit 0 |
