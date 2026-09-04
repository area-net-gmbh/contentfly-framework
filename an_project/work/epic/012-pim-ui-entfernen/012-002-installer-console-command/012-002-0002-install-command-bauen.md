---
id: 012-002-0002
title: Install-Command implementieren
status: todo
depends_on: [012-002-0001]
---

# Install-Command implementieren

## Context
Die Installation wird ein Console-Command — nicht-interaktiv aufrufbar und damit skript- und CI-fähig, was die Twig-Maske nie konnte.

## Acceptance criteria
- [ ] Ein Command führt die Installation vollständig durch.
- [ ] Alle Eingaben sind über Optionen oder Umgebungsvariablen setzbar; kein Schritt erzwingt eine Eingabeaufforderung.
- [ ] Ein zweiter Aufruf erkennt die bestehende Installation und bricht ab, statt Daten zu überschreiben.
- [ ] Fehlerfälle (DB nicht erreichbar, Verzeichnis nicht schreibbar) melden verständlich, was fehlt.

## Verification
Gegen eine leere Datenbank ausführen: Der Command läuft durch, die Anwendung ist danach benutzbar. Zweiter Aufruf bricht sauber ab.
