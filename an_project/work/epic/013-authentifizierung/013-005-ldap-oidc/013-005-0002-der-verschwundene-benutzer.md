---
id: 013-005-0002
title: Was mit einem verschwundenen Benutzer passiert
status: todo
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
- [ ] Der Weg ist gewaehlt und begruendet, im Code und im Ergebnis.
- [ ] Ein Benutzer, den das Verzeichnis nicht mehr kennt, verliert seinen Zugang **nachweislich** — auch mit einem noch gueltigen Refresh-Token.
- [ ] Der Anlass ist benannt und umgesetzt; er laeuft nicht beiläufig im Request eines anderen.
- [ ] Ein Benutzer ohne Provider (lokales Passwort) wird davon nicht beruehrt.
- [ ] Faellt das Verzeichnis aus, wird **niemand** gesperrt. Ein Ausfall darf nicht wie ein geloeschter Benutzer aussehen — das ist der Fehler, der eine ganze Belegschaft aussperrt.

## Verification
Ein Test, der einen bereitgestellten Benutzer anlegt, ihn im Doppelgaenger-Verzeichnis
verschwinden laesst und danach prueft, dass sein Refresh-Token nicht mehr einloest. Ein zweiter,
der das Verzeichnis ausfallen laesst und erwartet, dass niemand gesperrt wird. Volle Suite.
