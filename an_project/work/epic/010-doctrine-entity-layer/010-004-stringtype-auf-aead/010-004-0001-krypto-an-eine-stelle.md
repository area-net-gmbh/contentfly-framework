---
id: 010-004-0001
title: Den doppelten Krypto-Code an eine Stelle ziehen
status: done
depends_on: []
---

# Den doppelten Krypto-Code an eine Stelle ziehen

## Context
`StringType` und `TextareaType` tragen dieselbe Ver- und Entschlüsselung, Zeile für Zeile.
Solange das so ist, muss jede Änderung zweimal gemacht und zweimal geprüft werden — und der
Verfahrenswechsel ist genau eine solche Änderung.

**Dieser Task wechselt das Verfahren nicht.** Er zieht den vorhandenen AES-CBC-Code in eine
Klasse und legt Tests darum. Erst danach ist `010-004-0002` eine Änderung an **einer** Stelle,
und die Tests von hier sagen, ob sie etwas kaputt macht.

**Warum das hier nicht selbstverständlich ist:** Der Code hat heute **keinen Prüfgegenstand**.
Keine Entity setzt `encoded=true`, `SECURITY_CIPHER_KEY` steht auf `null`, und
`ConstraintApiTest` hält genau das fest. Die Tests dieses Tasks sind damit die **ersten**, die
die Verschlüsselung überhaupt ausführen.

## Acceptance criteria
- [x] Eine Klasse trägt Ver- und Entschlüsselung; `StringType` und `TextareaType` rufen nur noch sie.
- [x] Das Format des Chiffretexts ist unverändert — ein mit dem alten Code erzeugter Wert wird von der neuen Klasse gelesen und umgekehrt, beides im Test belegt.
- [x] Die Tests decken ab: Rundlauf, leerer Wert, fehlender Schlüssel (die Ausnahme bleibt), und ein manipulierter Chiffretext — der Letzte **schlägt heute nicht an**, und genau das hält der Test fest.
- [x] Die vorhandene Suite bleibt grün, `ConstraintApiTest` eingeschlossen.

## Verification
`./vendor/bin/phpunit --testsuite unit`, dann die volle Suite. Der Formatnachweis gehört in
den Test: ein fest hinterlegter Chiffretext aus dem alten Code, der entschlüsselt werden muss.

## Ergebnis

**`Classes\Security\Feldverschluesselung` trägt die Ver- und Entschlüsselung; `StringType` und
`TextareaType` rufen nur noch sie.** Kein `openssl_`-Aufruf steht mehr in den Typ-Klassen. Das
Verfahren ist unverändert AES-256-CBC — dieser Task wechselt es nicht.

Die Suite wächst von 268 auf **274**: sechs Tests, und es sind die **ersten**, die diese
Verschlüsselung überhaupt ausführen.

### Der Befund, um den es der Story geht — jetzt gemessen statt behauptet

AES-256-CBC hat keinen MAC. Ein verändertes Byte im Chiffretext wird nicht abgewiesen. Gemessen
an einem Beispiel, `Ueberweisung an Konto A, Betrag 100 Euro, dringend bitte`:

| Byte gekippt | Ergebnis |
|---|---|
| 20 | entschlüsselt: `J···7$····UMX$XKonpo A, Betrag 100 Euro, dri` |
| 30 | `false` — Padding-Prüfung schlägt an |
| 40 | entschlüsselt: `Ueberweisung an G···}···[·*6·10 Euro, dri` |
| 101 | `false` |

**Ein Block wird zu Müll, der Rest steht.** Wer den Chiffretext erreicht, kann gezielt Teile
verändern, und die Anwendung liefert das Ergebnis aus, ohne etwas zu bemerken.

Nicht jede Position gelingt — trifft die Änderung den letzten Block, scheitert das Padding. Der
Test sucht deshalb eine Position, an der die Manipulation **durchgeht**: Eine einzige genügt,
damit die Zusicherung wertlos ist.

`testEinManipulierterChiffretextFaelltHeuteNichtAuf` hält den Zustand fest, statt ihn zu
beklagen. Mit `010-004-0002` wird er umgedreht.

### Der Formatnachweis ist fest hinterlegt, nicht berechnet

`ALTER_CHIFFRETEXT` ist ein Wert, den der Code **vor** diesem Task erzeugt hat. Ein Chiffretext,
den der Test selbst verschlüsselt, bewiese nur, dass er mit sich selbst übereinstimmt. So ist er
der Nachweis, dass Bestandsdaten lesbar bleiben — und er bleibt es über `010-004-0002` hinaus.

### Zwei Dinge sind unverändert geblieben, obwohl sie schöner gingen

**Das Doppel-Base64.** `openssl_encrypt()` liefert mit `$options = 0` bereits base64; der IV
wird davorgehängt und das Ganze noch einmal kodiert. Verschwenderisch — aber es ist das Format,
in dem Bestandsdaten liegen, und dieser Task ändert kein Format.

**Das `false` bei unlesbarem Chiffretext.** `entschluesseln()` gibt es weiter, statt zu werfen.
Der alte Code tat das auch, und ein Task, der kein Verhalten ändern soll, ändert auch das nicht.

### Nachweis

| Probe | Ergebnis |
|---|---|
| Neue Tests | `OK (6 tests, 8 assertions)` |
| Volle Suite | `OK (274 tests, 650 assertions)`, 0 übersprungen |
| PHPStan | `[OK] No errors` |
| Deprecation-Gate | 0 Zeilen, 0 Ausnahmen |
| `openssl_` in den Typ-Klassen | 0 Treffer |
| Chiffretext aus dem alten Code | wird gelesen |
