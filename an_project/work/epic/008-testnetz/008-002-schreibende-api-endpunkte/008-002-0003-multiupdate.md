---
id: 008-002-0003
title: /api/multiupdate festhalten
status: todo
depends_on: [008-002-0001]
---

# /api/multiupdate festhalten

## Context
Ein Aufruf ändert mehrere Objekte. Die interessante Frage ist nicht der Erfolgsfall, sondern
der **Teilfehler**: Wenn das dritte von fünf Objekten scheitert — sind die ersten beiden
geschrieben, oder wird alles zurückgerollt?

## Umfang

- **Erfolgsfall**: mehrere Objekte in einem Aufruf, alle geändert.
- **Teilfehler**: ein Objekt im Stapel scheitert (unbekannte ID oder eine `unique`-Verletzung).
  Festzuhalten ist der **Ist-Zustand**, egal wie er ausfällt:
  - Bleiben die vorher verarbeiteten Objekte geändert?
  - Welchen Statuscode und welche Nutzlast liefert die Antwort?
  - Steht in der Antwort, welches Objekt scheiterte?
- **Form der Antwort** im Vergleich zu `/api/update`.
- Fehlender Token.

> **Ausdrücklich nicht:** eine Transaktion einbauen, wenn keine da ist. Die Abgrenzung des Epics
> ist eindeutig — *„Wenn `multiupdate` bei einem Fehler die bereits geschriebenen Objekte stehen
> lässt, dann ist das der Test — nicht die Transaktion, die man sich wünscht."* Fällt dabei ein
> echter Mangel auf, wird er als eigener Task notiert (`/new-task`), wie es mit `000-000-0007`
> in `008-001-0004` geschehen ist.

## Acceptance criteria
- [ ] Der Erfolgsfall mit mindestens zwei Objekten ist festgehalten.
- [ ] Das Verhalten bei einem Teilfehler ist festgehalten — inklusive der Frage, ob bereits
      verarbeitete Objekte geändert bleiben.
- [ ] Die Form der Antwort ist geprüft, nicht nur der Statuscode.
- [ ] Die Route weist ohne Token ab.
- [ ] Ein dabei entdeckter Mangel ist als eigener Task notiert, nicht hier repariert.

## Verification
Mehrere vollständige Läufe grün. Der Teilfehler-Test muss **deterministisch** sein: Der Fehler
wird gezielt herbeigeführt (unbekannte ID oder `unique`-Verletzung auf `PIM\Tag.title`), nicht
dem Zufall überlassen.
