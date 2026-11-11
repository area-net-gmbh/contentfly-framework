---
id: 000-000-0112
title: Veraltete Check-Ergebnisse dürfen keinen Merge freigeben — und sechs rote Zeitplan-Läufe dürfen nicht unbemerkt bleiben
status: todo
depends_on: []
---

# Veraltete Check-Ergebnisse dürfen keinen Merge freigeben — und sechs rote Zeitplan-Läufe dürfen nicht unbemerkt bleiben

## Context
**Das Gate hat funktioniert. Gemerkt hat es niemand, und gemergt wurde trotzdem.**

Angelegt mit `000-000-0111` unter der Annahme, `check: composer audit` cache die
Advisory-Datenbank. **Die Annahme war falsch**, und die Messung hat etwas Schlimmeres ergeben.
Die Zeitachse, erhoben am 2026-11-11 aus den Läufen selbst:

| Datum | Ereignis | Beleg |
|---|---|---|
| **2026-11-02 11:56** | PR #120 (Dependabot, `phpdotenv`) wird angelegt. Die Checks laufen **einmal** und sind grün | Lauf `41440694998` auf `b25df645` |
| **2026-11-04** | Die zehn `symfony/*`-Advisories kommen in die Datenbank | `Reported at` der Einträge |
| **2026-11-04 06:00** | Geplanter Lauf auf `master`: **grün** | `41839698235` |
| **2026-11-05 06:00** | Geplanter Lauf auf `master`: **rot**, `check: composer audit` | `42039191270` |
| 06.–10.11. je 06:00 | Geplanter Lauf: **rot**, jeden Tag, immer dasselbe Gate | `42226506586`, `42371610690`, `42480935670`, `42638932052`, `42830526185` |
| **2026-11-06 13:35** | **PR #120 wird gemergt** — auf den Check-Ergebnissen vom **2026-11-02** | `mergedAt` vs. Lauf oben |

**Zwei getrennte Defekte, und keiner davon ist der Lock.**

### 1. Ein erforderlicher Sicherheits-Check kann einen Merge mit vier Tage alten Ergebnissen freigeben
`an_project/docs/git.md` hält fest, dass *„Require branches to be up to date"* **aus** steht, mit
dieser Begründung:

> „sonst müsste jeder Branch vor dem Merge neu auf `master` gesetzt werden, auch wenn sich nichts
> überschneidet. **Die Checks laufen ohnehin gegen den Merge-Stand.**"

**Für `check: composer audit` trägt diese Begründung nicht.** Sie gilt für die sieben Checks, die
ausschliesslich den Baum prüfen — dort ist das Ergebnis eine Funktion des Codes, und *wann* es
erhoben wurde, ist gleichgültig. `composer audit` prüft den Lock gegen eine **veränderliche
äussere Quelle**. Sein Ergebnis ist eine Funktion aus Code **und Zeitpunkt**. Ein grünes Häkchen
vom 02.11. sagt über den 06.11. nichts, und GitHub hat es trotzdem als Freigabe akzeptiert.

Das ist keine theoretische Lücke: Genau so ist `master` am 06.11. mit zehn offenen Advisories
gemergt worden.

### 2. Sechs rote Zeitplan-Läufe in Folge haben zu nichts geführt
Der Zeitplan-Lauf ist das Ergebnis von `000-000-0055` (`cron: '0 6 * * *'`), und er hat **genau
getan, wofür er gebaut wurde** — die Lücke am Tag nach ihrer Veröffentlichung gemeldet. Dann hat
er sie fünf weitere Tage gemeldet. Gefunden wurde sie am 11.11. von Hand, bei der Vorbereitung
eines Releases, das mit der Sache nichts zu tun hatte.

**Ein Gate, dessen rotes Ergebnis niemanden erreicht, ist ein Logbuch, kein Gate.** `000-000-0055`
hat den Lauf geschaffen und damit das aktive Prüfen; der Meldeweg aus einem *fehlgeschlagenen
Zeitplan-Lauf* heraus ist offenbar nicht wirksam. Das ist hier zu schliessen, nicht dort erneut
aufzumachen.

## Acceptance criteria
- [ ] **Der Zeitplan-Lauf meldet sich.** Ein fehlgeschlagener geplanter Lauf erzeugt eine Benachrichtigung, die tatsächlich ankommt — nachgewiesen an einem absichtlich rot gemachten Lauf, nicht an der Konfiguration.
- [ ] **Frische Ergebnisse für das Audit-Gate.** `check: composer audit` kann einen Merge nicht mehr mit veralteten Ergebnissen freigeben. Entweder läuft das Gate unmittelbar vor dem Merge erneut, oder sein Ergebnis bekommt eine Höchstdauer, oder *„Require branches to be up to date"* wird eingeschaltet — die Wahl ist zu begründen, nicht nur zu treffen.
- [ ] Falls *„Require branches to be up to date"* eingeschaltet wird: Die Begründung in `an_project/docs/git.md` wird ersetzt, nicht ergänzt — der Satz „Die Checks laufen ohnehin gegen den Merge-Stand" ist für dieses eine Gate widerlegt und darf nicht stehenbleiben.
- [ ] `an_project/docs/git.md` und `deployment.md` halten den Unterschied fest: sieben Checks prüfen den Baum, einer prüft den Baum **gegen eine veränderliche äussere Quelle**. Nur beim letzten verfällt ein grünes Ergebnis.
- [ ] **Gegenprobe:** Ein PR, dessen Checks vor einem neu aufgenommenen Advisory grün gelaufen sind, lässt sich **nicht** mergen. Ohne diese Probe ist nicht gezeigt, dass die Lücke geschlossen ist.
- [ ] Geprüft und festgehalten, ob Dependabot für die zehn Advisories einen Security-PR geöffnet hat — und wenn nein, warum nicht. Der passive Weg aus `000-000-0055` hätte hier der zweite Netz sein sollen.

## Verification
```sh
gh run list --event schedule --limit 10 \
  --json databaseId,headSha,conclusion,createdAt   # die Reihe der roten Läufe
gh pr view <nr> --json createdAt,mergedAt,statusCheckRollup
                                                   # Alter der Ergebnisse gegen mergedAt
```
Die Zeitachse oben ist mit genau diesen beiden Aufrufen erhoben und in jeder Zeile mit der
Lauf-ID belegt. Wer sie nachprüft, braucht nichts zu glauben.

## Was dieser Task NICHT ist
Kein Lock-Bump — die zehn Advisories sind mit `000-000-0111` geschlossen und `master` ist mit
dessen Merge wieder grün. Hier geht es ausschliesslich darum, dass der **nächste** Fall vor dem
Merge auffällt statt fünf Tage später bei einer unverwandten Arbeit.
