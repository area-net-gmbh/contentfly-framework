---
id: 000-000-0073
title: Unerwartete Fehler nicht mit ihrem Ausnahmetext an den Client geben
status: todo
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
- [ ] Ohne Debug: Eine Ausnahme, die keine `ContentflyException` ist, antwortet mit einem festen `detail` (z. B. `contentfly_general_internal_error`) und `type` ohne Klassennamen aus Doctrine/DBAL; der volle Text geht ins Log.
- [ ] Mit Debug bleibt der Text sichtbar, wie heute.
- [ ] Ein Test löst einen Datenbankfehler aus und prüft, dass weder SQL noch Tabellenname in der Antwort stehen.
- [ ] Registereintrag, falls ein Client `detail` bisher ausgewertet hat.

## Verification
Test in `ErrorResponseApiTest`; die Suite bleibt grün.
