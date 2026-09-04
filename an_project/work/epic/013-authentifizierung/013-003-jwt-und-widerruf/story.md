---
id: 013-003-0000
title: JWT ausstellen und widerrufen
status: todo
depends_on: [013-002-0000]
---

# JWT ausstellen und widerrufen

## Goal
Der Login stellt wahlweise ein JWT aus, und ein ausgestelltes JWT lässt sich wieder entziehen.
Der zweite Teil ist der schwierige: Ein JWT gilt bis zum Ablauf — Logout, Sperrung und Leak
wirken nicht, solange niemand nachschaut.

## Umfang

**Ausstellung.** Der Login-Endpunkt entscheidet, welchen Tokentyp er ausgibt — über Konfiguration
oder einen Request-Parameter. Ohne Angabe bleibt es beim opaquen Token, damit Bestandsclients
nichts merken.

**Widerruf über das Refresh-Modell.** Statt eine Denylist über lange Laufzeiten zu führen:

- **Access-JWT kurzlebig** (Größenordnung 5–15 Minuten), zustandslos geprüft.
- **Refresh-Token als opaques DB-Token** — also genau der Mechanismus, der ohnehin existiert.
  Der Widerruf sitzt damit dort, wo schon Zustand liegt: Zeile löschen, und nach Ablauf des
  Access-Tokens ist der Zugang zu.
- **Denylist nur für das Restfenster**: Wer sofortige Wirkung braucht (Sperrung, Leak), führt
  die `jti` des Access-Tokens bis zu dessen Ablauf in einer Sperrliste. Die bleibt klein, weil
  Einträge mit dem Token verfallen.

Das Kundenprojekt hatte genau diese Denylist gebaut — ohne das Refresh-Modell daneben, weshalb
sie unbegrenzt wachsen musste.

**Schlüsselverwaltung.** Das Signaturgeheimnis kommt aus der Umgebung, nicht aus der Datenbank
und nicht aus einer committeten Konfiguration. Schlüsselwechsel muss möglich sein, ohne alle
Sitzungen zu beenden — also mit einer Kennung im Token-Header und einer Übergangszeit, in der
zwei Schlüssel akzeptiert werden.

**Claims.** Festlegen und dokumentieren, was im Token steht: Benutzerkennung, Ablauf, `jti`,
Ausgeber. Was sich ändern kann, ohne dass jemand neu anmeldet — Rollen, Gruppen, Berechtigungen —
gehört **nicht** hinein, sonst wirkt eine Rechteänderung erst nach Ablauf.

## Fertig, wenn
- Der Login liefert auf Anforderung ein Access-JWT und ein Refresh-Token, sonst weiterhin ein
  opaques Token.
- Ein abgelaufenes Access-JWT wird abgewiesen; das Refresh-Token erneuert es.
- Logout entzieht das Refresh-Token; der Zugang endet spätestens mit dem Access-Token.
- Eine Sperrung wirkt sofort — nachgewiesen mit einem noch gültigen Access-Token.
- Ein Schlüsselwechsel ist ohne Zwangsabmeldung durchgeführt worden.
- Das Signaturgeheimnis steht in keiner Datei im Repo.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
