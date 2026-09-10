---
id: 010-004-0001
title: Den doppelten Krypto-Code an eine Stelle ziehen
status: todo
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
- [ ] Eine Klasse trägt Ver- und Entschlüsselung; `StringType` und `TextareaType` rufen nur noch sie.
- [ ] Das Format des Chiffretexts ist unverändert — ein mit dem alten Code erzeugter Wert wird von der neuen Klasse gelesen und umgekehrt, beides im Test belegt.
- [ ] Die Tests decken ab: Rundlauf, leerer Wert, fehlender Schlüssel (die Ausnahme bleibt), und ein manipulierter Chiffretext — der Letzte **schlägt heute nicht an**, und genau das hält der Test fest.
- [ ] Die vorhandene Suite bleibt grün, `ConstraintApiTest` eingeschlossen.

## Verification
`./vendor/bin/phpunit --testsuite unit`, dann die volle Suite. Der Formatnachweis gehört in
den Test: ein fest hinterlegter Chiffretext aus dem alten Code, der entschlüsselt werden muss.
