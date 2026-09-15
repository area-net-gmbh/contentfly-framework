---
id: 007-005-0002
title: Das Ist-Verhalten des alten Projekts aufzeichnen
status: todo
depends_on: [007-005-0001]
---

# Das Ist-Verhalten des alten Projekts aufzeichnen

## Context
Das Projekt hat vermutlich wenige automatische Tests. Ohne eine Aufzeichnung, was das **alte**
Backend antwortet, lässt sich nach der Migration nicht belegen, dass die App nichts merkt — der
Vergleich in Phase 8 des Leitfadens hätte keinen Massstab.

Dasselbe Vorgehen wie Epic `008` für das Framework, zugeschnitten auf das, was die
Ionic/Angular-App tatsächlich aufruft.

## Acceptance criteria
- [ ] **Die Aufrufe der App sind erhoben** — aus dem Frontend-Code (`ApiService`, Interceptors, Guards), nicht geschätzt: Endpunkte, Methoden, welche Token-Quelle, welche Antwortfelder die App liest.
- [ ] **Referenzantworten** des alten Backends für diese Aufrufe liegen reproduzierbar vor — Statuscode, Envelope, Felder — mit einem Skript, das denselben Satz später gegen das migrierte Backend fährt.
- [ ] **Flüchtige Werte** (Zeitstempel, Tokens, Ids) sind im Vergleich ausdrücklich ausgenommen, nicht stillschweigend.
- [ ] **Der Anmeldeweg ist beschrieben**, wie er heute läuft: welcher LoginManager für welchen Einstieg, OAuth-2.0-Fluss mit Profilabfrage an die Community-API, was lokal ohne echten Identity-Provider durchspielbar ist und was nicht.
- [ ] Im Projekt-Repo ist nichts geändert; das Aufzeichnungsskript liegt in diesem Repo unter `tools/migration/`.

## Verification
Das Skript zweimal hintereinander gegen das alte Backend fahren: identisches Ergebnis nach
Abzug der ausgenommenen Werte.

## Offene Fragen
- Lässt sich der OAuth-2.0-Fluss lokal durchspielen (Client-Zugangsdaten, Redirect-URL auf
  `localhost`), oder braucht es einen Test-Identity-Provider? Zu klären am Anfang des Tasks.
