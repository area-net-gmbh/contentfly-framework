---
id: 010-004-0002
title: XChaCha20-Poly1305 einführen, altes Format weiter lesen
status: review
depends_on: [010-004-0001]
---

# XChaCha20-Poly1305 einführen, altes Format weiter lesen

## Context
Der Verfahrenswechsel. Geschrieben wird ab hier **XChaCha20-Poly1305** über libsodium;
gelesen wird beides.

**Warum AES-CBC weg muss:** Es ist unauthentifiziert. Wer den Chiffretext ändern kann, ändert
den Klartext gezielt mit, und die Anwendung merkt es nicht — sie entschlüsselt Unsinn und
liefert ihn aus. Ein AEAD-Verfahren erkennt die Manipulation und verweigert.

**Wie das Format erkannt wird:** am Chiffretext, nicht an der Konfiguration. Ein Präfix
unterscheidet die beiden; alles ohne Präfix ist das alte Format. Damit liest eine frisch
aktualisierte Instanz ihre Bestandsdaten weiter, ohne dass jemand etwas umstellt.

**Schreiben ausschliesslich neu.** Es gibt keinen Schalter, der das alte Format zurückholt —
sonst wäre die Migration nie abgeschlossen, und ein Angreifer könnte auf das schwächere
Verfahren zurückdrängen.

**Der Schlüssel wird abgeleitet, nicht durchgereicht.** `SECURITY_CIPHER_KEY` ist heute roh der
OpenSSL-Schlüssel; libsodium verlangt genau 32 Byte. Eine Ableitung löst beides und ist der
zweite Befund neben dem fehlenden MAC.

## Acceptance criteria
- [x] Neu geschriebene Werte tragen das AEAD-Format, erkennbar am Präfix.
- [x] Ein Wert im alten Format wird weiterhin entschlüsselt — mit einem Test, der einen fest hinterlegten alten Chiffretext liest.
- [x] Ein manipulierter AEAD-Chiffretext wird **abgewiesen**, nicht stillschweigend zu Unsinn entschlüsselt. Das ist der Unterschied, um den es geht, und er gehört in einen Test.
- [x] Die Ableitung des Schlüssels ist begründet und deterministisch: derselbe `SECURITY_CIPHER_KEY` ergibt denselben Schlüssel, sonst wären Bestandsdaten verloren.
- [x] Ohne `SECURITY_CIPHER_KEY` verweigert die Verschlüsselung wie bisher, mit derselben Meldung.
- [x] `ext-sodium` steht im Manifest — es wird jetzt gebraucht und war nie angefordert.

## Verification
Unit-Tests: Rundlauf neu, Lesen alt, Manipulation abgewiesen, Ableitung deterministisch. Dazu
die volle Suite. Der Manipulationstest ist der eigentliche Nachweis der Story — er muss gegen
den Stand von `010-004-0001` **rot** sein.

## Ergebnis

**Geschrieben wird XChaCha20-Poly1305, gelesen wird beides, und jede Manipulation fällt auf.**
Die Suite wächst von 274 auf **277**.

### Der Nachweis der Story

`testEinManipulierterChiffretextFaelltHeuteNichtAuf` aus `010-004-0001` ist umgedreht zu
`testJedeManipulationFaelltAuf`. Er war gegen den neuen Stand **rot**, bevor ich ihn umgedreht
habe — das ist die Gegenprobe, die der Task verlangt hat:

```
Es gibt eine Manipulation, die nicht auffaellt — CBC ohne MAC weist sie nicht ab
Failed asserting that null is not null.
```

**Der Test probiert jede Position durch, nicht eine ausgewählte.** Beim alten Verfahren genügte
**eine** durchgehende Manipulation, um die Zusicherung wertlos zu machen; also muss hier jede
abgewiesen werden — das Nonce eingeschlossen.

### Das Format steht am Chiffretext

Ein neuer Wert beginnt mit `PIM1:`, alles ohne dieses Präfix ist das alte Format. Der Doppelpunkt
ist die Wahl, die es unverwechselbar macht: Ein alter Chiffretext ist base64, und base64 kennt
keinen Doppelpunkt.

Eine Konfiguration hätte denselben Zweck erfüllt und einen Nachteil gehabt: Sie wäre umzustellen,
und bis dahin läge in der Datenbank beides ohne Unterscheidungsmerkmal.

**Geschrieben wird ausschliesslich neu.** Es gibt keinen Schalter zurück — er wäre ein Weg, auf
das schwächere Verfahren zurückzudrängen, und die Migration wäre nie abgeschlossen.

### Die Schlüsselableitung, und ein Fehlgriff dabei

`SECURITY_CIPHER_KEY` ist eine Passphrase beliebiger Länge, libsodium verlangt genau 32 Byte.
Abgeleitet wird mit `crypto_generichash` (BLAKE2b), deterministisch — derselbe konfigurierte
Wert muss denselben Schlüssel ergeben, sonst wären Bestandsdaten verloren. Belegt über zwei
Instanzen: Was die eine verschlüsselt, liest die andere.

**Der erste Versuch war falsch.** Ich hatte den Namensraum als *Schlüsselparameter* übergeben;
der verlangt mindestens 16 Byte, und `contentfly-feld` hat 15. Die Tests meldeten
`SodiumException: unsupported key length`. Jetzt steht der Namensraum in der **Nachricht** —
eine Kennung, die man nur aufbläht, um eine Längenvorgabe zu erfüllen, sagt weniger als eine,
die man lesen kann.

**Kein Argon2id an dieser Stelle, und das ist eine Entscheidung, keine Nachlässigkeit.** Für
eine vom Menschen gewählte Passphrase wäre es das richtige Werkzeug, es braucht aber ein
gespeichertes Salz — das müsste bei jedem Feld mitgeführt werden und ist damit eine Entscheidung
über das Format. Steht als *Revidieren, wenn* an der Klasse.

### Die Erweiterungen sind jetzt angefordert

`ext-sodium` **und** `ext-openssl` stehen in `composer.json`. Beide wurden immer gebraucht, das
Manifest hatte aber **keine einzige** `ext-*`-Angabe. Nachgemessen in beiden Pipeline-Images:
alle geforderten Erweiterungen vorhanden. `composer.lock` trägt nur den neuen `content-hash`.

`ext-openssl` gehört dem Wortlaut nach zu `010-004-0004`; es hier zu lassen wäre eine halbe
Angabe gewesen, denn dieselbe Klasse braucht ab jetzt beide.

### Nachweis

| Probe | Ergebnis |
|---|---|
| Krypto-Tests | `OK (9 tests, 11 assertions)` |
| Volle Suite | `OK (277 tests, 653 assertions)`, 0 übersprungen |
| PHPStan | `[OK] No errors` |
| Deprecation-Gate | 0 Zeilen, 0 Ausnahmen |
| Gegenprobe: der alte Test gegen den neuen Stand | **rot**, wie beabsichtigt |
| Chiffretext aus dem alten Code | wird weiterhin gelesen |
| `ext-*` in `php:8.3-cli` und `php:8.4-cli` | vollständig vorhanden |
