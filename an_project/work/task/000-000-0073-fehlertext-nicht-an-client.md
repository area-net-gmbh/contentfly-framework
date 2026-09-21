---
id: 000-000-0073
title: Unerwartete Fehler nicht mit ihrem Ausnahmetext an den Client geben
status: review
depends_on: []
---

# Unerwartete Fehler nicht mit ihrem Ausnahmetext an den Client geben

## Context
**Gefunden in `000-000-0069` (2026-09-21).** Bei einem unerwarteten Fehler steht in
`errors[0].detail` der Text der Ausnahme — auch mit `APP_DEBUG=0`. Gemessen am Fehler aus
`000-000-0072`: Die Antwort enthält die SQL-Fehlermeldung von MySQL samt Ausschnitt der Abfrage.
Genau diese Art Informationsleck hat TeamViewer 2025 bei UFP gemeldet (PHP-Fehlerausgabe, CVSS 6.5).

Der Envelope (`011-001-0003`) hat entschieden, dass `detail` für einen Menschen ist und `type` die
Ausnahmeklasse nennt. Für eine `ContentflyException` ist der Text ein Meldungsschlüssel — harmlos.
Für jede andere Ausnahme ist er Interna.

## Acceptance criteria
- [x] Ohne Debug: Eine Ausnahme, die keine `ContentflyException` ist, antwortet mit einem festen `detail` (z. B. `contentfly_general_internal_error`) und `type` ohne Klassennamen aus Doctrine/DBAL; der volle Text geht ins Log.
- [x] Mit Debug bleibt der Text sichtbar, wie heute.
- [x] Ein Test löst einen Datenbankfehler aus und prüft, dass weder SQL noch Tabellenname in der Antwort stehen.
- [x] Registereintrag, falls ein Client `detail` bisher ausgewertet hat.

## Verification
Test in `ErrorResponseApiTest`; die Suite bleibt grün.

## Ergebnis (2026-09-21)
**Ohne Debug zeigt ein unvorhergesehener Fehler nur noch `contentfly_general_internal_error`; der
volle Text steht im Server-Log.**

### Umgesetzt
- **Fehlerhandler** (`bootstrap-web.php`): Ist die Ausnahme weder eine `ContentflyException` noch
  eine HTTP-Ausnahme und der Status 5xx, dann `detail` = `contentfly_general_internal_error`,
  `type` = `InternalServerError`, `code` bleibt `null` (die Aussage des Envelopes: nichts Stabiles
  zum Verzweigen). Die Meldung samt Klasse, Datei und Zeile geht per `error_log()` ins Log. Mit
  `APP_DEBUG` unverändert.
- **Gilt auch für PHP-Fehler.** Ein `TypeError` nannte Methode, Signatur und Server-Pfad —
  ebenfalls Interna.
- **`/system/do` warf für Eingabefehler eine nackte `\Exception` mit 500.** Mit der neuen Regel
  hätten die Sätze niemanden mehr erreicht. Die sechs Stellen sind jetzt `ContentflyException` mit
  passendem Status (400, 404, 409) und Schlüssel; das Detail steht in `context.value`. Neuer
  Schlüssel `contentfly_general_token_too_weak`.

### Belegt
- `ErrorResponseApiTest::testAnUnforeseenFaultDoesNotShowItsInternals`: ein DBAL-Fehler über
  `/api/query`; geprüft am **ungeparsten** Rumpf, dass weder `SQLSTATE` noch Spaltenname, Tabelle,
  `Doctrine` oder `DBAL` darin stehen. **Gegenprobe:** ohne die Änderung rot.
- Neun bestehende Tests in `SystemControllerApiTest` und `ErrorResponseApiTest` erwarteten 500 bzw.
  den alten Text — angepasst, jeweils mit Begründung im Test.

### Bewusst nicht Teil dieses Tasks
- **Der Startfehler** (`Kernel\Start`) gibt seine Meldung weiterhin aus, ohne Verzeichnisse — so
  entschieden in `000-000-0018`, bevor die Konfiguration geladen ist. Ein Verbindungsfehler zur
  Datenbank nennt dort Host bzw. Socket.
- **`/api/query` mit unbekannter Spalte endet mit 500** statt 400 — die Spalten der `select`-Liste
  prüft niemand. Gehört zu `0076`.
- Der Log-Eintrag ist nicht über einen Test abgesichert; die Suite hat keinen Zugriff auf das Log des
  Testservers.

