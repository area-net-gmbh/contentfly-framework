---
id: 011-001-0003
title: Fehlerantworten in dieselbe Form bringen
status: review
depends_on: [011-001-0002]
---

# Fehlerantworten in dieselbe Form bringen

## Context
Fehler entstehen an einer Stelle — dem Handler in `bootstrap-web.php` — und tragen heute
`message`, `type`, `status` sowie je nach Ausnahme `message_value`, `message_entity`,
`message_lang`. Das ist eine zweite Form neben der Erfolgsform, und ein Client braucht beide.

Zielform laut `api-envelope.md`: `data` = `null`, `errors` = Liste, `meta` wie beim Erfolg. Offen
und hier zu entscheiden: welche Felder ein Eintrag in `errors` trägt (`code`, `detail`, und was aus
`message_value`/`message_entity`/`message_lang` wird) und ob mehrere Einträge heute überhaupt
entstehen können.

**Was sich nicht ändert:** die Statuscodes. Sie sind mit `000-000-0006` in Ordnung gebracht worden,
und der Rumpf wiederholt sie nicht — `status` fällt weg, wie in `api-envelope.md` entschieden.

## Acceptance criteria
- [x] Der Fehlerhandler antwortet in der Zielform; `data` ist `null`, `errors` eine Liste.
- [x] Die Felder der Contentfly-Ausnahmen (`message_value`, `message_entity`, `message_lang`) haben im Eintrag einen benannten Platz; keine Information geht verloren.
- [x] Statuscodes unverändert, mit Test.
- [x] `status` steht nicht mehr im Rumpf.
- [x] Register und Leitfaden tragen den Bruch, mit der Zuordnung alt → neu je Feld.
- [x] Volle Suite, PHPStan, Deprecation-Gate grün.

## Verification
Je ein Test für 400, 401, 403, 404 und 500: Form gleich, Statuscode wie vorher. Gegenprobe gegen
den Stand davor.

## Ergebnis

### Entschieden

**Vier feste Schlüssel je Eintrag** — `code`, `detail`, `type`, `context`, immer alle vorhanden.
Das ist der eigentliche Bruch: Bisher hing es **von der Ausnahmeklasse ab, welche Schlüssel
ankamen** (`message_value` nur bei `ContentflyException`, `message_entity`/`message_lang` nur bei
`ContentflyI18NException`, bei einem PHP-Fehler keines von beiden). Ein Client musste die Ausnahmen
des Frameworks kennen, um einen Fehler überhaupt lesen zu können.

| Feld | Inhalt | kam von |
|---|---|---|
| `code` | Worauf ein Client **verzweigt**: der `Messages`-Schlüssel — `null` bei allem anderen | `message`, sofern Schlüssel |
| `detail` | Für einen Menschen: `getMessage()` | `message` |
| `type` | Die Ausnahmeklasse | `type` |
| `context` | `{"value": …}` bzw. `{"entity": …, "lang": …}`, sonst `null` | `message_value`, `message_entity`/`message_lang` |

- **`code: null` statt eines erfundenen Schlüssels.** Ein PHP-Fehler hat keinen stabilen
  Bezeichner. `null` sagt genau das, statt so zu tun, als gäbe es einen; wer solche Fälle doch
  unterscheiden muss, liest `type` und trägt das Risiko einer Umbenennung. Dass `code` und `detail`
  bei Contentfly-Ausnahmen derselbe String sind, ist der Preis dafür, dass die Regel „auf `code`
  verzweigen, `detail` anzeigen" ohne Ausnahme gilt.
- **Der Stacktrace geht nach `meta.debug`** — er beschreibt die Antwort, nicht den Fehler, also
  dorthin, wo `ts` und `hash` stehen. Wie bisher nur bei `APP_DEBUG`.
- **`status` fällt ersatzlos.** `000-000-0006` hatte das Feld in Ordnung gebracht (vorher kam der
  Code als schlüsselloser Eintrag `"0"` an); jetzt ist es ganz weg. Der Code steht in der
  HTTP-Antwort, und ein Rumpf, der ihn wiederholt, lädt dazu ein, dass beide auseinanderlaufen —
  genau das war vor `000-000-0006` der Fall.
- **`errors` ist eine Liste, obwohl heute immer genau ein Eintrag darin steht.** Geprüft: Der
  Handler sieht eine Ausnahme, mehr kann dort nicht entstehen. Die Liste ist der Platz für die
  Feldvalidierung, damit die Form später nicht ein zweites Mal bricht.

### Eine Klasse für beide Seiten

`Classes\Envelope` baut Erfolgs- **und** Fehlerhülle. Zwei Kopien der Meta an zwei weit entfernten
Stellen — `ApiController` und `bootstrap-web.php` — wären auseinandergelaufen, und dann hätte ein
Client doch vorher wissen müssen, welche Art Antwort er in der Hand hat. `renderResponse()` aus
`0002` geht seitdem ebenfalls durch die Klasse.

**Drei Stellen erzeugen Fehlerantworten, und alle drei sind umgestellt** — die dritte und die
zweite standen nicht in der Aufgabe, gehören aber dazu, weil sie sonst die neunte und zehnte Form
gewesen wären:

| Stelle | vorher |
|---|---|
| `bootstrap-web.php`, `$app->error()` | drei Zweige, je ein eigener Rumpf |
| `Kernel\Start::respondToStartupFailure()` | `message`/`type`/`status` — der Kommentar versprach „dieselbe Form wie jede andere Fehlerantwort" |
| `BaseControllerProvider`, „nicht installiert" (`503`) | blankes `{"message": …}` |

Die letzte hat dafür einen `Messages`-Schlüssel bekommen
(`contentfly_general_not_installed`) — „nicht installiert" ist ein vorhersehbarer Zustand, auf den
ein Client verzweigen darf, kein unvorhergesehener Fehler.

**Zwei Konstanten werden defensiv gelesen**, und das ist kein Zierrat: Die Startfehler-Antwort
entsteht, bevor `version.php` gelesen ist. Ein blosser Konstantenzugriff wäre ein Fatal **in der
Fehlerantwort** — die Fehlerart aus `000-000-0006`, ein Stockwerk tiefer. `meta.version` ist dort
`null`, und das ist die ehrliche Aussage: Zu diesem Zeitpunkt weiss niemand, welche Version nicht
starten konnte. `StartupFailureResponseTest` prüft beide Fälle gegeneinander — der fehlerhafte
Cache-Treiber fällt spät und hat Versionen, die fehlende `config.php` fällt früh und hat keine.
Derselbe Grund gilt für den Schema-Hash: `Envelope::schemaHash()` fängt ab, dass `$app['schema']`
gerade das ist, was nicht funktioniert.

### Die Testanpassungen

Die häufigste geänderte Erwartung war `assertArrayNotHasKey('data', $body)` — „ohne Token fliessen
keine Daten", 18-mal in der Suite. Sie hält nicht mehr **und sagte nie, was sie meinte**: `data`
ist jetzt immer da und im Fehlerfall `null`. Das ist die stärkere Aussage, denn ein fehlender
Schlüssel und ein Schlüssel mit Inhalt sind nur unterscheidbar, wenn man weiss, dass der Schlüssel
überhaupt fehlen kann. Neu prüft `assertErrorEnvelope()` das an einer Stelle — Gegenstück zu
`assertEnvelope()` aus `0002`.

`LoginEventApiTest` behält die alte Erwartung mit Begründung: Eine abgelehnte Anmeldung beantwortet
`AuthController` selbst, nicht der Handler — `/auth/*` zieht mit `011-001-0004` nach.

### Abnahme

`ErrorResponseApiTest` bekommt die beiden Tests, die die Verification verlangt: fünf Fehler
verschiedener Herkunft (fehlender Token, fehlendes Recht, unbekannte Entity, unbekannte Id,
PHP-Fehler) mit **einem** Leser, und die Gegenprobe, dass `status` in keiner Ebene des Rumpfs mehr
auftaucht. Die Statuscodes selbst sind unverändert — sie werden in den Tests der Endpunkte geprüft,
hier dienen sie nur als Beleg, dass die Form nicht von ihnen abhängt.

**Verifiziert:** volle Suite `Tests: 613, Assertions: 2282, Skipped: 3`, PHPStan `[OK] No errors`,
Deprecation-Gate 0.

### Was offen bleibt

Die **Erfolgs**antworten von `/auth/*`, `/file/upload`, `/file/overwrite` und `/system/do` —
`011-001-0004`. Ihre Fehlerfälle laufen, soweit sie durch den Handler gehen, bereits im Envelope;
was diese Controller selbst als Ablehnung bauen (etwa der `401` einer falschen Anmeldung), zieht
dort mit nach.
