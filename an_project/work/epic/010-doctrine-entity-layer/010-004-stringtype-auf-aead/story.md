---
id: 010-004-0000
title: StringType von AES-CBC auf AEAD heben
status: review
depends_on: []
---

# StringType von AES-CBC auf AEAD heben

## Goal
`StringType` und `TextareaType` verschlüsseln mit einem authentifizierten Verfahren
(XChaCha20-Poly1305 über libsodium), und es gibt einen ausführbaren Re-Encrypt-Befehl mit
Trockenlauf und Rückweg.

**Was heute steht, ist AES-256-CBC ohne MAC.** Gemessen in
`Classes/Types/StringType.php` und `TextareaType.php`: zufälliger IV, `openssl_encrypt` mit
`$options = 0`, Ergebnis als `base64(iv . ciphertext)`. CBC ohne Authentifizierung ist
**manipulierbar** — wer den Chiffretext ändern kann, ändert den Klartext gezielt mit, ohne dass
die Anwendung es merkt. Dazu kommt, dass `SECURITY_CIPHER_KEY` roh als Schlüssel durchgereicht
wird, ohne Ableitung.

**Der Befehl läuft in dieser Story auf keinen echten Daten.** Er entsteht hier, wird gegen die
Testdatenbank belegt und in Epic `007` je Bestandsprojekt angewandt. Deshalb gehören Trockenlauf
und ein beschriebener Rückweg zur Abnahme, nicht zur Kür: Eine Migration, die man nicht vorher
ansehen und nicht zurücknehmen kann, wird nicht ausgeführt.

Bestandsdaten müssen lesbar bleiben, solange sie nicht umgeschlüsselt sind — das Verfahren ist
am Chiffretext zu erkennen, nicht an der Konfiguration.

## Ausgangslage, gemessen am 2026-09-10

| | |
|---|---|
| Entities mit `encoded=true` | **null** — die Verschlüsselung ist heute nicht auslösbar |
| `SECURITY_CIPHER_KEY` | Vorgabe `null`; `StringType` wirft dann von sich aus |
| Verschlüsselungscode | **doppelt**, in `StringType` und `TextareaType`, Zeile für Zeile gleich |
| libsodium in `php:8.3-cli` und `php:8.4-cli` | vorhanden, XChaCha20-Poly1305 verfügbar |
| `ext-sodium` / `ext-openssl` im Manifest | **nicht angefordert** |

`ConstraintApiTest::testKeineEntityNutztDieEncodedVerschluesselung` hält den Zustand fest und
schlägt an, sobald jemand das Flag setzt. Das bestimmt den Zuschnitt: Der neue Code lässt sich
**nur über eigene Tests** belegen, nicht über die vorhandene Suite, und der Re-Encrypt-Befehl
braucht eine eigene Testentity.

### Entschieden am 2026-09-10, mit dem Auftraggeber

**Altes Format: lesen ja, schreiben nein.** Ein vorhandener Chiffretext wird weiter
entschlüsselt, jeder neue Schreibvorgang benutzt AEAD. Das Verfahren steht am Chiffretext, nicht
in der Konfiguration — ein Bestandsprojekt migriert damit im laufenden Betrieb, und der Befehl
beschleunigt es nur.

Verworfen: das alte Format nur hinter einem Schalter zu lesen (ein Bestandsprojekt startet nach
dem Update dann erstmal mit unlesbaren Daten) und der harte Schnitt (die Migration würde zur
Voraussetzung des Updates statt zu einem Schritt danach).

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [x] 010-004-0001 — Den doppelten Krypto-Code an eine Stelle ziehen
- [x] 010-004-0002 — XChaCha20-Poly1305 einführen, altes Format weiter lesen
- [x] 010-004-0003 — Der Re-Encrypt-Befehl mit Trockenlauf und Rückweg
- [x] 010-004-0004 — Manifest, Dokumente und Bruchstellen
