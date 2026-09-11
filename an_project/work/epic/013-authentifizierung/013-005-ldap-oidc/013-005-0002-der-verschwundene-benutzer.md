---
id: 013-005-0002
title: Was mit einem verschwundenen Benutzer passiert
status: review
depends_on: [013-005-0001]
---

# Was mit einem verschwundenen Benutzer passiert

## Context
Die dritte Frage aus dem Story-Umfang, und die einzige der drei, die nicht im Provider sitzt.

**Wer nicht mehr im Verzeichnis steht, kommt nicht mehr herein** — das ergibt sich von selbst:
Der Provider findet ihn nicht und liefert `null`. Damit ist die Frage aber nicht beantwortet,
denn **sein Contentfly-Konto bleibt**, und mit ihm:

  - ein Refresh-Token, das bis zu seinem Zeitlimit weiter frische Access-JWT holt
    (`013-003-0002`),
  - ein opaques Anmeldetoken, das bis zum Timeout gilt,
  - der Eintrag in `pim_user`, den niemand mehr zuordnen kann.

Ein Benutzer, den die Personalabteilung aus dem AD genommen hat, arbeitet also weiter — bis ein
Zeitlimit ablaeuft, das niemand dafuer gewaehlt hat.

**Drei Wege, und die Wahl gehoert begruendet:**

  ignorieren   Nichts tun. Billig, und genau der Zustand, der hier beschrieben ist.
  sperren      `isActive` auf false. Wirkt sofort — der `Benutzerlader` weist einen inaktiven
               Benutzer ab (`013-002-0001`), und damit fallen auch laufende Tokens. Umkehrbar.
  loeschen     Die Zeile weg. Nicht umkehrbar, und `pim_log` verliert seinen Bezug.

**Der Haken bei „sperren": Woher weiss die Anwendung davon?** Ein verschwundener Benutzer meldet
sich gerade nicht an — das ist ja der Punkt. Es braucht einen Anlass: ein Console-Command, das
den Bestand gegen das Verzeichnis haelt, oder die naechste Anmeldung eines anderen Benutzers als
Aufhaenger. Das erste ist ehrlicher; das zweite verteilt Arbeit auf fremde Requests.

## Acceptance criteria
- [x] Der Weg ist gewaehlt und begruendet, im Code und im Ergebnis.
- [x] Ein Benutzer, den das Verzeichnis nicht mehr kennt, verliert seinen Zugang **nachweislich** — auch mit einem noch gueltigen Refresh-Token.
- [x] Der Anlass ist benannt und umgesetzt; er laeuft nicht beiläufig im Request eines anderen.
- [x] Ein Benutzer ohne Provider (lokales Passwort) wird davon nicht beruehrt.
- [x] Faellt das Verzeichnis aus, wird **niemand** gesperrt. Ein Ausfall darf nicht wie ein geloeschter Benutzer aussehen — das ist der Fehler, der eine ganze Belegschaft aussperrt.

## Verification
Ein Test, der einen bereitgestellten Benutzer anlegt, ihn im Doppelgaenger-Verzeichnis
verschwinden laesst und danach prueft, dass sein Refresh-Token nicht mehr einloest. Ein zweiter,
der das Verzeichnis ausfallen laesst und erwartet, dass niemand gesperrt wird. Volle Suite.

## Ergebnis

**Gewählt: sperren, mit einem Console-Command als Anlass.** `appcms:provider:abgleich` hält die
bereitgestellten Benutzer gegen ihr Fremdsystem und setzt `isActive` auf false, wo es sie nicht
mehr kennt.

### Warum sperren und nicht löschen

| | |
|---|---|
| **Es wirkt sofort** | Der `Benutzerlader` weist einen inaktiven Benutzer ab (`013-002-0001`), und zwar auf **jedem** Weg. Laufende Access-JWT fallen mit, weil der Benutzer bei jedem Request geladen wird; der Refresh-Endpunkt lehnt ebenfalls ab (`013-003-0002`). |
| **Es ist umkehrbar** | Wer versehentlich aus einer Gruppe fiel, wird wieder aktiviert. Eine gelöschte Zeile kommt nicht zurück. |
| **`pim_log` behält seinen Bezug** | Ein Protokoll, dessen Benutzer verschwunden ist, kann nicht mehr sagen, wer gehandelt hat. |

„Ignorieren" war ausdrücklich keine Option — das ist der Zustand, gegen den dieser Task gebaut
ist.

### Die dritte Antwort ist die wichtigste

`Bestandspruefung::kenntKennung()` gibt `?bool` zurück, nicht `bool`:

```
true   vorhanden
false  nicht mehr vorhanden
null   kann es gerade nicht sagen
```

**Ohne die dritte Antwort gibt es nur zwei falsche Wege.** Ein nicht erreichbares Verzeichnis
müsste entweder als `true` gelesen werden — dann wirkt der Abgleich nie — oder als `false`, und
dann sperrt ein Netzwerkfehler die ganze Belegschaft aus. Die Typangabe trägt die Entscheidung,
nicht ein Kommentar.

**Beim `BeispielProvider` fiel das sofort an:** Der erste Entwurf las eine leere Liste als „kennt
niemanden". Damit hätte der erste Abgleich nach einem vergessenen Umgebungseintrag jeden
Benutzer ausgesperrt. Eine leere Liste heisst jetzt „keine Auskunft", und ein eigener Test hält
den Unterschied fest.

### Eine Zusatzfähigkeit, keine Pflicht

`Anmeldeprovider` verlangt weiterhin **eine** Methode. Wer das Fremdsystem auch ohne vorgezeigtes
Passwort befragen kann, implementiert zusätzlich `Bestandspruefung`.

**Nicht jeder kann das.** Ein OIDC-Provider prüft einen Token, den der Client mitbringt; ohne
Token hat er keine Handhabe, nach einer Kennung zu fragen. Er implementiert das Interface
deshalb nicht — und der Abgleich **nennt** die übersprungenen Konten, statt sie stillschweigend
zu übergehen. Ein Abgleich, der Konten auslässt, ohne es zu sagen, ist schlimmer als keiner.

### Ein Command und kein Nebenher

Ein verschwundener Benutzer meldet sich gerade nicht an; das ist ja der Punkt. Die Alternative
wäre, den Abgleich an die nächste Anmeldung **eines anderen** Benutzers zu hängen — das verteilt
fremde Arbeit auf einen Request, der davon nichts weiss, und macht die Anmeldezeit von der Grösse
des Verzeichnisses abhängig.

`--dry-run` zählt und fasst nichts an. Ein Abgleich, den man nicht vorher ansehen kann, wird
nicht ausgeführt — dieselbe Linie wie bei `appcms:token:cleanup`.

### Nachweis

| Probe | Ergebnis |
|---|---|
| Volle Suite | `OK (460 tests, 1133 assertions)`, 0 übersprungen (vorher 455) |
| PHPStan | `[OK] No errors` |
| Verschwundener Benutzer | gesperrt, und sein Refresh-Token löst nicht mehr ein |
| Keine Auskunft | niemand gesperrt, Meldung in der Ausgabe |
| Noch vorhanden | unangetastet |
| `--dry-run` | zählt, sperrt nicht |
| Benutzer ohne Provider | unangetastet |

Gemessen über den `BeispielProvider`: Der Console-Aufruf bekommt eine andere Liste als der
Testserver, womit ein Benutzer aus Sicht des Abgleichs verschwindet, ohne dass irgendwo ein
Verzeichnis laufen müsste.
