---
id: 010-004-0002
title: XChaCha20-Poly1305 einführen, altes Format weiter lesen
status: todo
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
- [ ] Neu geschriebene Werte tragen das AEAD-Format, erkennbar am Präfix.
- [ ] Ein Wert im alten Format wird weiterhin entschlüsselt — mit einem Test, der einen fest hinterlegten alten Chiffretext liest.
- [ ] Ein manipulierter AEAD-Chiffretext wird **abgewiesen**, nicht stillschweigend zu Unsinn entschlüsselt. Das ist der Unterschied, um den es geht, und er gehört in einen Test.
- [ ] Die Ableitung des Schlüssels ist begründet und deterministisch: derselbe `SECURITY_CIPHER_KEY` ergibt denselben Schlüssel, sonst wären Bestandsdaten verloren.
- [ ] Ohne `SECURITY_CIPHER_KEY` verweigert die Verschlüsselung wie bisher, mit derselben Meldung.
- [ ] `ext-sodium` steht im Manifest — es wird jetzt gebraucht und war nie angefordert.

## Verification
Unit-Tests: Rundlauf neu, Lesen alt, Manipulation abgewiesen, Ableitung deterministisch. Dazu
die volle Suite. Der Manipulationstest ist der eigentliche Nachweis der Story — er muss gegen
den Stand von `010-004-0001` **rot** sein.
