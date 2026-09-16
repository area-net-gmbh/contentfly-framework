---
id: 011-001-0001
title: Bestandsaufnahme der Antwortformen und Geltungsbereich des Envelopes
status: todo
depends_on: []
---

# Bestandsaufnahme der Antwortformen und Geltungsbereich des Envelopes

## Context
`api-envelope.md` entscheidet die Zielform (`data` / `errors` / `meta`) und führt eine Tabelle für
zehn `/api/*`-Endpunkte plus den Fehlerfall. Der Baum hat aber mehr Antwortstellen: `/api/*` läuft
über **einen** Trichter (`ApiController::renderResponse()`, 18 Aufrufe), die Fehlerform entsteht im
Handler in `bootstrap-web.php`, und `/auth`, `/file` und `/system` bauen ihre Antworten je selbst
(`new JsonResponse(...)`: AuthController 10, FileController 2, SystemController 1).

**Die offene Frage ist der Geltungsbereich.** Gilt der Envelope nur für `/api/*` — dann hat die API
danach zwei Formen statt sieben, aber eben zwei — oder auch für Anmeldung, Dateien und
Systemaufrufe? Das entscheidet den Umfang von `0002` bis `0004` und den Umfang des Bruchs für
Clients.

Ohne diese Erhebung ist jede Umstellung ein Stochern: Die Endpunkte, die `api-envelope.md` nicht
nennt (`/api/tree`, `/api/tree2`, `/api/translations`, `/api/query`, `/api/replace`, `/api/mail`,
`/api/schema`), fehlen dort nur, weil Epic `008` sie nicht charakterisiert hat.

## Acceptance criteria
- [ ] Jede Antwortstelle des Frameworks ist erhoben: Endpunkt, heutige Form, Fundstelle (Datei:Zeile) — aus dem Code, nicht aus der Erinnerung.
- [ ] Zu jedem Endpunkt steht die Zielform fest; die Tabelle in `api-envelope.md` ist auf **alle** Endpunkte erweitert.
- [ ] Der Geltungsbereich ist entschieden und begründet: `/api/*` allein, oder samt `/auth`, `/file`, `/system`.
- [ ] Festgehalten, welche Tests die heutige Form halten (Datei, Testname) — die Liste ist die Arbeitsgrundlage für `0002` und `0003`.
- [ ] Kein Code geändert.

## Verification
Ein Abgleich: Jede `JsonResponse` und jeder `renderResponse`-Aufruf in `lib/` taucht in der Tabelle
auf. `git grep -c "new JsonResponse\|renderResponse("` gegen die Zahl der Zeilen im Dokument.
