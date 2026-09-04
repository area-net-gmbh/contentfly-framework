---
id: 000-000-0003
title: Lokale Entwicklungsumgebung mit MySQL bereitstellen
status: done
depends_on: []
---

# Lokale Entwicklungsumgebung mit MySQL bereitstellen

## Context
Es gibt in diesem Repo keine Datenbank und keinen Weg, eine zu bekommen: `docker-compose.yml`,
`docker/` und `docker` stehen in der `.gitignore` (Zeilen 12, 13, 23, 24) — übernommen aus dem
Kundenprojekt, wo die Compose-Datei bewusst außerhalb lag.

Für ein Repo, dessen Zweck eine Migration mit Testnachweis ist, ist das die teuerste Lücke im
Backlog. Vier Arbeitspakete hängen daran und keines kommt ohne sie weiter:

| Blockiert | Wofür die Datenbank gebraucht wird |
|---|---|
| `012-002-0002` | Die Installation gegen eine leere Datenbank fahren — die Abnahme steht seit dem 2026-09-04 offen |
| `012-003-0003` | Upload und Download der API mit Tests absichern |
| `012-004-0003` | Token-Authentifizierung mit Tests absichern |
| Epic `008` **komplett** | Ein Testnetz ohne laufendes System gibt es nicht |

Bei `012-001` und `012-002` musste jede Verifikation deshalb auf `grep` und `php -l` heruntergehen.
Das trägt für Löscharbeiten; für den Kernel-Wechsel in Epic `009` trägt es nicht.

**Port 3306 ist belegt** — auf dieser Maschine läuft ein fremder MySQL-Container
(`anccounting-db-1`). Die Umgebung muss daneben laufen können, ohne ihn zu stören.

## Acceptance criteria
- [x] Eine `docker-compose.yml` liegt **im Repo** und startet mindestens einen MySQL-Dienst.
- [x] Die `.gitignore`-Einträge, die sie bisher ausschließen, sind entfernt — bewusst und mit
      einer Notiz, warum: In einem Framework-Repo ist die Compose-Datei Teil der Vorlage, nicht
      lokaler Kram.
- [x] Der Port ist frei wählbar und kollidiert im Standard **nicht** mit 3306.
- [x] Zugangsdaten und Datenbankname sind neutral (kein `usabiq`) und passen zu dem, was die
      Vorlage `custom/config.php` erwartet.
- [x] Zeichensatz und Kollation stimmen mit `Config::DB_CHARSET` / `DB_COLLATE` überein — sonst
      legt die Installation Tabellen an, die später nicht zum Schema passen.
- [x] Die Daten überleben einen Neustart (benanntes Volume), und es gibt einen dokumentierten Weg,
      sie wegzuwerfen und neu anzufangen.
- [x] Das Runbook (`an_project/docs/runbook.md`) beschreibt Hochfahren, Verbinden, Zurücksetzen
      und Herunterfahren — mit den echten Befehlen, nicht mit Platzhaltern.
- [x] PHP erreicht die Datenbank nachweislich aus diesem Repo heraus.

## Verification
```sh
docker compose up -d
docker compose ps          # der Dienst ist "healthy"
```
Danach eine Verbindung aus PHP heraus aufbauen und eine triviale Abfrage absetzen — die Antwort
muss ankommen, ohne dass der fremde Container auf 3306 berührt wird (`docker ps` zeigt ihn
unverändert laufend).
