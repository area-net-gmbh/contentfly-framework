---
id: 007-004-0000
title: Der Migrationsleitfaden
status: done
depends_on: [007-001-0000, 007-002-0000, 007-003-0000]
---

# Der Migrationsleitfaden

## Goal
Ein Leitfaden im Repo führt Schritt für Schritt vom alten Contentfly auf die neue Version, mit
einer vollständigen Liste der Breaking Changes und je Eintrag „vorher → nachher".

## Das Rohmaterial liegt vor — falsch herum sortiert

`an_project/docs/breaking-changes.md` hat **96 Einträge in 13 Abschnitten** (Stand 2026-09-11).
Sie sind nach Epic und Story geordnet, also danach, **wann wir etwas geändert haben**. Ein
Bestandsprojekt braucht die umgekehrte Ordnung: **was es in welcher Reihenfolge zu tun hat.**

Ein Projekt fragt nicht „was hat Story `013-002` geändert", sondern „was muss ich an meinen
Entities tun, was an meiner Konfiguration, was an meinen Controllern, und in welcher
Reihenfolge, damit ich zwischendurch nicht auf einem Stand stehe, der gar nicht läuft".

**Die Datei bleibt, sie wird nicht ersetzt.** Sie ist das Register; der Leitfaden ist der Weg.

## Was hineingehört

- **Die gestrichene Oberfläche ist der grösste Brocken.** Ein Projekt, das die Admin-UI heute
  benutzt, verliert sie ersatzlos. Der Leitfaden sagt klar, was an ihre Stelle tritt (API,
  Console) — und was nicht.
- **Der Bezugsweg aus `007-001`:** von der Kopie auf das Paket.
- **Der Rector-Lauf aus `007-002`,** mit dem, was er nicht kann.
- **Die Festlegung aus `007-003`,** damit ein Projekt weiss, ob es seine Controller anfassen muss.
- **Die Datenmigration — eingesammelt, nicht neu gebaut.** `appcms:security:reencrypt` existiert
  seit `010-004`, mit `--dry-run`, Stapelgrösse und einem in `an_project/docs/deployment.md`
  beschriebenen Rückweg (Sicherung vor dem Lauf; ein abgebrochener Lauf ist kein Schaden, weil
  beide Formate lesbar bleiben). Das Erfolgskriterium des Epics ist hier bereits eingelöst; diese
  Story verweist darauf und wiederholt es nicht.

## Abnahme

Ein Leser, der das alte Contentfly kennt und diesen Text zum ersten Mal sieht, kommt ohne
Rückfrage durch. Jeder der 96 Brüche ist im Leitfaden erreichbar — entweder als eigener Schritt
oder als Verweis ins Register, und keiner fällt heraus. Die Probe darauf ist `007-005`.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [x] 007-004-0001 — Die Phasen festlegen und das Register zuordnen
- [x] 007-004-0002 — Den Leitfaden schreiben
- [x] 007-004-0003 — Die Vollständigkeit prüfbar machen

`0001` legt die Reihenfolge fest — **die eigentliche Leistung des Leitfadens**; entstünde sie
beim Schreiben nebenbei, entstünde sie aus der Ordnung des Registers, und die ist genau die
falsche. `0002` erzählt sie. `0003` macht die Abnahme „keiner fällt heraus" mechanisch.

**Stand des Registers am 2026-09-11: 100 Einträge in 14 Abschnitten** — nicht mehr 96 in 13, wie
oben beim Schneiden des Epics gezählt. Vier Einträge sind mit Epic `007` selbst dazugekommen.

## Ergebnis

**`an_project/docs/migration.md` ist der Weg; `breaking-changes.md` bleibt das Register.** Neun
Phasen, jede mit dem, was zu tun ist, und mit einem prüfbaren Ende.

**Die Reihenfolge ist an drei Stellen erzwungen, und dort steht der Grund dabei:** Der Bezugsweg
muss vor die Entities, weil die Rector-Regel mit dem Paket kommt. Der Metadaten-Cache muss vor
das ORM-Upgrade geleert werden. Der Re-Encrypt-Lauf braucht eine laufende Anwendung.

**Sechs der neun Abbruchkriterien sind gegen die Vorlage gefahren** — Erweiterungen, Paket,
Rector, Installation, `/api/config`, Re-Encrypt. Die drei übrigen (Code, Authentifizierung,
API-Vertrag) lassen sich nur an einem Projekt prüfen, das eigenen Code mitbringt; das ist
`007-005`.

**Die gestrichene Oberfläche steht vor allen Phasen, nicht in einer.** Sie ist die Entscheidung,
die vor der ersten Zeile Arbeit fällt — mit dem Satz, der die Sache ehrlich macht: Wer sie nicht
treffen will, migriert nicht.

**Alle 14 Register-Abschnitte sind zugeordnet, und drei Tests halten das.** Dazu die
Grössenangabe im Kopf des Leitfadens, die sonst beim nächsten Bruch still falsch würde.

## Was diese Story über sich selbst gelernt hat

**Die Regel, die die Zuordnung erst eindeutig macht,** stand nicht im Plan: Ein Abschnitt gehört
in die Phase, in der seine **Handlung** liegt — nicht in jede, die er berührt. Ohne sie wäre die
abschnittsweise Granularität gescheitert, und der Fall, an dem es sich zeigt
(*Feldverschlüsselung* in den Phasen 1, 5 und 9), wäre als Widerspruch stehen geblieben.

## Drei Funde, die nicht zur Story gehörten

1. **Eine Zahl in meinem eigenen Entwurf stimmte nicht.** Die acht `FRONTEND_*`-Felder stehen im
   Register; `pim-annotationen-migration.md`, Abschnitt 6 führt **zwei weitere**. Wer nur eine
   Liste abarbeitet, lässt Zeilen stehen — und genau das steht jetzt dort.
2. **Der Leitfaden war nirgends verzeichnet.** `CLAUDE.md` listet die Projektdokumente; die neue
   Datei fehlte. Ein Leitfaden, den niemand findet, ist keiner.
3. **Ein Test der Suite ist flatterig, und es ist gemessen.**
   `SyncApiTest::testZweiLoeschungenInDerselbenSekundeKommenBeide` heisst „in derselben
   Sekunde", stellt das aber nicht sicher — **2 von 1500 Paaren fallen auseinander, 0,13 %.**
   Nicht nebenbei repariert: Er gehört nicht zu dieser Story, und die Reparatur ändert eine
   Zusicherung. **Er bekommt einen eigenen Task.**

**Zahlen:** Die Suite wächst von 521 auf **524** Tests. PHPStan `[OK] No errors`, 0 Deprecations
bei 0 Ausnahmen, 0 Byte Postausgang. Der Leitfaden hat 255 Zeilen.
