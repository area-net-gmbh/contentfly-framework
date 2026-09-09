---
id: 010-004-0000
title: StringType von AES-CBC auf AEAD heben
status: todo
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

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
