---
id: 016-003-0000
title: Hooks laufen einmal statt fünfmal, das Golden-Master-Werkzeug ist wieder glaubwürdig, und MySQL 8.4 läuft in der CI
status: todo
depends_on: []
---

# Hooks laufen einmal statt fünfmal, das Golden-Master-Werkzeug ist wieder glaubwürdig, und MySQL 8.4 läuft in der CI

## Goal

Die drei Meldungen ohne gemeinsamen Faden sind abgearbeitet: `#109` im Request-Pfad, `#110` am
Migrationswerkzeug, und der offene zweite Teil von `#108` in der CI-Matrix. Sie stehen zusammen,
weil keine von ihnen eine Entscheidung mit den anderen teilt — nicht, weil sie thematisch
zusammengehören. Jede ist für sich zu haben.

## `#109` — die globalen Hooks laufen je Mount, nicht je Request

`BaseControllerProvider::setUpMiddleware()` registriert die globalen `before`/`after`-Hooks — das
JSON-Decoding des Bodys, die `pim.controller.*`-Events. Aufgerufen wird es von **fünf** Providern,
nachgezählt am 27.11.2026: `ApiControllerProvider`, `CustomControllerProvider`,
`FileControllerProvider`, `AuthControllerProvider`, `SystemControllerProvider`. Es gibt kein Flag
und keinen früheren Ausstieg.

Damit laufen die Hooks fünfmal pro Request — und bei einer Anwendung mit einigen Dutzend eigenen
Mounts entsprechend öfter. **Das ist nicht nur Arbeit umsonst:** ein Hook mit Seiteneffekt läuft
mehrfach. Dazu durchläuft ein `OPTIONS`-Preflight die ganze Kette, obwohl er nur die CORS-Antwort
braucht.

Umzusetzen: die globalen Hooks **einmal** registrieren (etwa über ein Flag im Container), und bei
`OPTIONS` früh aussteigen. Das Projekt hat beides heute als Patch in
`BaseControllerProvider.php`.

**Worauf zu achten ist:** Welche der registrierten Hooks heute *tatsächlich* einen Seiteneffekt
haben, der sich bei Mehrfachlauf aufaddiert, gehört vor dem Umbau festgestellt — ein Hook, der
idempotent ist, kostet nur Zeit; einer, der es nicht ist, erklärt womöglich einen bestehenden
Befund. Und der frühe `OPTIONS`-Ausstieg darf die CORS-Antwort nicht mit abschneiden; er ist mit
`#107` aus `016-002` am gleichen Code, aber unabhängig davon.

## `#110` — ein Werkzeug, dessen roten Meldungen man nicht glaubt, ist unbrauchbar

`tools/migration/record-api.php` hat zwei Lücken:

1. **Der Token lässt sich nicht als `Bearer` senden.** Das Werkzeug schreibt
   `<tokenHeader>: <token>`; gegengeprüft, es kennt nur `tokenHeader` (Vorgabe `APPCMS-Token`) und
   keinen Prefix. Eine API, die `Authorization: Bearer <jwt>` erwartet, ist damit nicht aufnehmbar
   — ebenso wenig ein Login, der einen zusätzlichen Header oder ein Cookie braucht (beim Melder ein
   Geräte-Cookie, das die OTP-Abfrage überspringt).
2. **Listen ohne feste Reihenfolge melden falsche Abweichungen.** Eine Liste, die nur nach einem
   Zeitstempel sortiert, kommt bei gleichen Zeitstempeln von Lauf zu Lauf anders heraus. Der
   Vergleich meldet dann eine Abweichung, obwohl sich nichts geändert hat.

Der zweite Punkt ist der schwerere, und zwar nicht wegen des Aufwands: **ein Golden-Master-Werkzeug,
das falsch rot meldet, wird nicht mehr gelesen.** Dann ist es nicht halb nützlich, sondern wertlos —
und `007-005-0004` hat `record-api.php` als Phase-8-Abnahme in den Migrationsleitfaden geschrieben.

Umzusetzen: Szenario-Keys `tokenPrefix` und `headers`, die für **alle** Anfragen gelten, Logins
eingeschlossen; dazu je Request ein `unordered`-Schalter, der die genannten Listen nach der
Normalisierung kanonisch sortiert, bevor verglichen wird. Das Projekt pflegt heute eine als solche
markierte Kopie unter `backend/tests/Recording/record-api.php` — die Kopie aufgeben zu können ist
die Abnahme.

**Hinweis für die Umsetzung:** Dieses Werkzeug hat in Runde 1 schon einmal Rückfluss bekommen
(`W-4` in `007-005-0005`: eine gedrosselte Anmeldung wird benannt, jede andere gescheiterte mit
Status). Der dort entstandene Test ist der Ansatzpunkt, nicht ein neues Testgerüst.

## `#108` — der Rest der Meldung: MySQL 8.4 in die CI-Matrix

Der Befund selbst ist erledigt: `BaseI18nTree::$treeParent` führt seit `000-000-0025` über
`(parent_id, parent_lang)` auf den zusammengesetzten Schlüssel `(id, lang)`, und genau daran
scheiterte MySQL 8.4 mit Fehler 6125. Offen ist der zweite Satz der Meldung, und er ist der
eigentliche Punkt: **`.github/workflows/pipeline.yml` fährt an beiden Stellen `mysql:8.0`.**

8.4 ist die aktuelle LTS. Ohne sie in der Matrix fällt ein Unterschied dieser Art erst beim
Anwender auf — so wie dieser. Dass der konkrete Fall heute grün wäre, ist kein Ersatz für die
Dimension; es ist der Anlass, sie zu ziehen.

**Zu klären, bevor jemand das für einen Einzeiler hält:** ob 8.4 zusätzlich oder statt 8.0 läuft
(Laufzeit der Pipeline gegen Abdeckung der Bestandsinstallationen), und ob die beiden Stellen in
`pipeline.yml` dieselbe Antwort brauchen. Ein zweiter Durchlauf der ganzen Suite ist nicht gratis.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [ ] noch keine — über `/new-task 016-003` anlegen
