---
id: 013-003-0002
title: Der Refresh-Weg
status: review
depends_on: [013-003-0001]
---

# Der Refresh-Weg

## Context
Ein Access-JWT ist kurzlebig — das ist seine ganze Sicherheitsleistung. Ohne Erneuerung müsste
sich ein Benutzer alle paar Minuten neu anmelden. `POST /auth/refresh` nimmt das Refresh-Token
entgegen und gibt ein frisches Access-JWT zurück.

**Das Refresh-Token wird dabei ersetzt.** Ein Refresh-Token, das mehrfach gilt, ist ein
langlebiges Geheimnis: Wer es abgreift, kann sich damit beliebig lange frische Zugangstokens
holen, und niemand sieht es. Wird es bei jedem Gebrauch getauscht, fällt ein zweiter Gebrauch
desselben Tokens auf.

**Die Anmeldebremse gilt auch hier.** `013-001-0003` bremst `POST /auth/login` pro Kennung und
pro IP. Ein Endpunkt, der Zugangstokens ausgibt, ist dasselbe Ziel — ihn ungebremst zu lassen
hiesse, die Bremse an der Vordertür anzubringen und die Seitentür offen zu lassen.

## Acceptance criteria
- [x] `POST /auth/refresh` liefert gegen ein gültiges Refresh-Token ein frisches Access-JWT.
- [x] Das Refresh-Token wird dabei ersetzt; das vorgezeigte gilt danach nicht mehr. Ein Test benutzt es zweimal und erwartet beim zweiten Mal eine Abweisung.
- [x] Ein unbekanntes, abgelaufenes oder bereits verbrauchtes Refresh-Token wird abgewiesen — alle drei mit derselben Antwort.
- [x] Ein Access-JWT taugt nicht als Refresh-Token, und ein Refresh-Token nicht als Zugangstoken. Beide Richtungen sind geprüft.
- [x] Ein gesperrter Benutzer bekommt kein neues Access-JWT.
- [x] Der Endpunkt unterliegt der Anmeldebremse aus `013-001-0003`.

## Verification
Integrationstest: mit JWT anmelden, das Refresh-Token einlösen, mit dem neuen Access-JWT
`/api/schema` öffnen, dann dasselbe Refresh-Token ein zweites Mal einlösen wollen. Dazu ein
Test, der einen gesperrten Benutzer erneuern lassen will. Volle Suite.

## Ergebnis

**`POST /auth/refresh` tauscht ein Refresh-Token gegen ein frisches Access-JWT** — und ersetzt
dabei das Refresh-Token.

### Rotation ist kein Beiwerk

Ein Refresh-Token, das mehrfach gilt, ist ein langlebiges Geheimnis: Wer es abgreift, holt sich
damit beliebig lange frische Zugangstokens, und niemand sieht es. Wird es bei jedem Gebrauch
getauscht, fällt ein zweiter Gebrauch auf — er wird abgewiesen, weil die Zeile nicht mehr
existiert. Ein Test löst dasselbe Token zweimal ein und erwartet beim zweiten Mal eine
Abweisung, beim neuen dagegen Erfolg.

Die alte Zeile wird **gelöscht** und eine neue angelegt, statt den Wert an Ort und Stelle zu
tauschen: Ein Token *ist* seine Zeile, und `created` soll sagen, wann dieses Token entstand.

### Der Endpunkt hängt nicht hinter der Anmeldung

`/auth/refresh` bekommt bewusst **kein** `$checkAuth`. Ein Refresh-Token ist kein Zugangstoken —
der `Tokenhandler` weist es ausdrücklich ab. Hinge die Anmeldung davor, wäre der Endpunkt nur
mit einem gültigen Access-JWT erreichbar, also genau dann nicht, wenn man ihn braucht: nach
dessen Ablauf.

Er prüft dafür selbst, und er unterliegt der Anmeldebremse aus `013-001-0003`. **Gebremst wird
nur über die Adresse**, nicht über eine Kennung: Der Request bringt keine mit. Das ist auch die
richtige Achse — ein Refresh-Token lässt sich nicht über einen Benutzernamen erraten, sondern
nur durch Durchprobieren von einer Stelle aus.

### Beide Richtungen sind zu

| vorgezeigt als | Antwort |
|---|---|
| Refresh-Token an `/api/schema` | abgewiesen (`013-003-0001`) |
| Access-JWT an `/auth/refresh` | abgewiesen |
| opaques Anmeldetoken an `/auth/refresh` | abgewiesen |

Das dritte ist der Fall, den man übersieht: Ein gewöhnliches Anmeldetoken ist ebenfalls eine
`pim_token`-Zeile, nur ohne `purpose`. Ohne die Prüfung wäre der Refresh-Endpunkt ein Weg,
aus einem opaquen Token ein JWT zu machen — und damit die Trennung in einer Richtung wieder auf.

### Die Ablaufrechnung steht jetzt an einer Stelle

`Tokenhandler::abgelaufen()`, `timeoutFuer()` und `timeoutGilt()` sind aus dem opaquen Zweig
herausgezogen, weil der Refresh-Weg dieselbe Rechnung braucht — Gruppen-Timeout in Minuten,
`referrer`-Token verfallen nicht, abschaltbar über `APP_CHECK_TOKEN_TIMEOUT`. Zwei Kopien
derselben Ablauflogik laufen auseinander, und die eine, die es dann falsch macht, lässt jemanden
länger herein als gedacht.

### Ein gesperrter Benutzer bekommt kein neues Token

Das ist der Fall, den das Refresh-Modell tragen muss: Der Zugang endet spätestens mit dem
laufenden Access-Token, weil danach niemand mehr ein neues bekommt. Nachgewiesen mit einem
Testbenutzer, der mitten in der Sitzung gesperrt wird.

### Nachweis

| Probe | Ergebnis |
|---|---|
| Volle Suite | `OK (387 tests, 971 assertions)`, 0 übersprungen (vorher 379) |
| PHPStan | `[OK] No errors` |
| Deprecations | 0 protokollierte Zeilen |
| Fehlschläge am Refresh | drei Ursachen, eine Antwort — verglichen, nicht einzeln geprüft |
