---
id: 013-003-0000
title: JWT ausstellen und widerrufen
status: done
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

**Dabei zu beachten, und beim Schneiden aufgefallen:** Das Refresh-Token ist eine
`pim_token`-Zeile, und der opaque Zweig des `Tokenhandler` nimmt heute *jede* Zeile an. Wer sein
Refresh-Token als `appcms-token` vorzeigte, bekäme damit die ganze API statt nur ein neues
Access-JWT. Die Zeile braucht deshalb einen Vermerk über ihren Zweck, und der opaque Zweig muss
sie als Zugangstoken abweisen.

**Widerruf über das Refresh-Modell.** Statt eine Denylist über lange Laufzeiten zu führen:

- **Access-JWT kurzlebig** (Größenordnung 5–15 Minuten), zustandslos geprüft.
- **Refresh-Token als opaques DB-Token** — also genau der Mechanismus, der ohnehin existiert.
  Der Widerruf sitzt damit dort, wo schon Zustand liegt: Zeile löschen, und nach Ablauf des
  Access-Tokens ist der Zugang zu.
- **Denylist nur für das Restfenster**: Wer sofortige Wirkung braucht (Logout, Leak), führt
  die `jti` des Access-Tokens bis zu dessen Ablauf in einer Sperrliste. Die bleibt klein, weil
  Einträge mit dem Token verfallen.

Das Kundenprojekt hatte genau diese Denylist gebaut — ohne das Refresh-Modell daneben, weshalb
sie unbegrenzt wachsen musste.

**Nachgemessen am 2026-09-10: Eine Benutzersperrung braucht die Denylist nicht.** Sie wirkt seit
`013-002-0001` sofort — der JWT-Zweig gibt sein `UserBadge` ohne eigenen Lader zurück, also lädt
der `Benutzerlader` den Benutzer aus `pim_user` und weist einen gesperrten mit derselben
Ausnahme ab wie einen unbekannten. Der Story-Text nannte die Sperrung als Anwendungsfall der
Liste; sie ist es nicht mehr. Übrig bleiben Logout und Leak, also der Widerruf **eines**
Tokens.

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
- Ein Widerruf wirkt sofort — nachgewiesen mit einem noch gültigen Access-Token nach dem Logout.
  (Die Benutzer**sperrung** wirkt bereits ohne diese Story sofort, siehe oben; auch das gehört
  nachgewiesen, damit die Zusicherung nicht unbelegt dasteht.)
- Ein Schlüsselwechsel ist ohne Zwangsabmeldung durchgeführt worden.
- Das Signaturgeheimnis steht in keiner Datei im Repo.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [x] 013-003-0001 — Die Claims und die Ausstellung
- [x] 013-003-0002 — Der Refresh-Weg
- [x] 013-003-0003 — Widerruf: Logout und die Sperrliste
- [x] 013-003-0004 — Schlüsselwechsel ohne Zwangsabmeldung
- [x] 013-003-0005 — Die Buchführung
