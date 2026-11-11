---
id: 000-000-0112
title: check composer audit — belegen, dass das Gate die Advisory-Datenbank erneuert statt sie zu cachen
status: todo
depends_on: []
---

# check composer audit — belegen, dass das Gate die Advisory-Datenbank erneuert statt sie zu cachen

## Context
**Ein Sicherheits-Gate hat zwei Tage nach der Veröffentlichung von zehn Advisories grün
gemeldet.** Gefunden mit `000-000-0111`:

| Datum | Ereignis |
|---|---|
| **2026-11-04** | Zehn Advisories über fünf `symfony/*`-Pakete kommen in die Datenbank von `FriendsOfPHP/security-advisories` — das ist das `Reported at` der Einträge selbst |
| **2026-11-06** | PR #120 wird gemergt. `check: composer audit` war **grün** |
| **2026-11-11** | Lokal gegen denselben Lock: `tools/ci/audit.sh` **Exit 1**, zehn Advisories |

Der Lock war in allen drei Zeilen derselbe — `symfony/*` auf `7.4.17`–`7.4.21`. Geändert hat
sich nur, was das Gate gelesen hat.

**Das ist nicht derselbe Fall wie `000-000-0109`, und das ist der Punkt.** Dort lagen *Reported
at* (Meldung an den Hersteller) und die Aufnahme in die Datenbank einen Monat auseinander — der
grüne Lauf vom 2026-09-29 war korrekt, weil es zu diesem Zeitpunkt wirklich nichts zu finden gab.
Diese Erklärung trägt hier nicht: Das Datum, das hier am Eintrag steht, **ist** das
Aufnahmedatum. Am 2026-11-06 gab es etwas zu finden.

**Zwei Möglichkeiten, und beide sind ein Mangel am Gate:**
1. Der Job benutzt eine zwischengespeicherte Advisory-Datenbank (`COMPOSER_HOME`, der
   Actions-Cache, oder das Image) und erneuert sie vor dem Lauf nicht.
2. Der Job hat das Gate in diesem Lauf gar nicht erreicht, und „grün" heisst hier „nicht
   gelaufen".

**Warum das mehr ist als eine Fussnote:** `composer audit --locked` liest den Lock aus dem Repo,
aber die Advisories aus dem Netz. Das Gate ist damit das einzige der acht, dessen Ergebnis **nicht
reproduzierbar** ist — derselbe Commit kann heute grün und morgen rot sein, und das ist richtig
so. Falsch ist nur, wenn es rot sein müsste und grün meldet. Solange das nicht geklärt ist, gilt:
**Ein grüner Audit-Lauf belegt den Stand seiner Datenbank, nicht den Stand der Welt.** Für ein
Gate, das als eines von acht erforderlichen Checks vor `master` steht, ist das zu wenig.

## Acceptance criteria
- [ ] Es ist belegt, **welche** der beiden Möglichkeiten zutrifft — am Job-Log des Laufs von PR #120 (`gh run view`), nicht durch Nachdenken über den Workflow.
- [ ] Falls gecacht wird: Der Workflow erneuert die Advisory-Datenbank vor dem Audit, und der Cache-Schlüssel schliesst sie aus. Der Beweis ist ein Lauf, der ein bekannt neues Advisory findet.
- [ ] Falls der Job das Gate nicht erreicht hat: Der Grund ist benannt und der Job scheitert in diesem Fall **rot** statt grün — ein übersprungenes Sicherheits-Gate darf nicht wie ein bestandenes aussehen.
- [ ] `an_project/docs/deployment.md` hält fest, dass dieses Gate gegen eine veränderliche Quelle prüft, und was daraus für die Aussagekraft eines grünen Laufs folgt.
- [ ] Gegenprobe: Ein Lauf auf einem Commit, dessen Lock ein bekanntes Advisory trägt, wird **rot**. Ohne diese Probe ist nicht gezeigt, dass das Gate greift — genau die Lücke, die dieser Task schliesst.

## Verification
```sh
gh run list --workflow pipeline.yml --limit 20          # den Lauf von PR #120 finden
gh run view <lauf-id> --log --job "<audit-job>"         # Composer-Version, Datenbankstand, Exit
```
Der Lauf, der die Gegenprobe erbringt, wird mit seiner ID im Ergebnis dieses Tasks genannt — wie
in `000-000-0109` (`39394055170`) und `000-000-0105` (`36552555751`).

## Was dieser Task NICHT ist
Kein Lock-Bump. Die zehn Advisories sind mit `000-000-0111` geschlossen. Hier geht es
ausschliesslich darum, ob das Gate das nächste Mal vor dem Merge warnt statt danach.
