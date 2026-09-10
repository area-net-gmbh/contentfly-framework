---
id: 013-004-0002
title: Provisionierung ohne setzbares Passwort
status: todo
depends_on: [013-004-0001]
---

# Provisionierung ohne setzbares Passwort

## Context
**Befund A-6, und er ist der Grund fuer diese Story.** `createManagedUser()` setzt
`setPass($alias)` — das Passwort ist der Benutzername. Entschaerft ist das heute allein durch den
Riegel „nur ueber LoginManager authorisierbar"; jeder Pfad, der ihn umgeht, ist eine triviale
Kontouebernahme. Eine Sicherung, die aus einem einzigen `if` besteht, ist keine.

**Ein ueber ein Fremdsystem angelegter Benutzer hat kein Passwort.** Nicht ein zufaelliges,
sondern gar keines: Der Hash bekommt einen Wert, gegen den `password_verify()` niemals passt.
Der Unterschied zu einem Zufallswert ist, dass man es der Zeile ansieht.

**Der MD5-Praefix verliert die Kennung.** `md5($klasse).'-'.$alias` macht Benutzer
unwiedererkennbar: Wer in `pim_user` nachsieht, findet `3f2a…-mmustermann` und weiss nicht, wer
das ist. Der Praefix loest ein echtes Problem — zwei Fremdsysteme, die denselben Benutzernamen
liefern, duerfen nicht dasselbe Konto bekommen —, aber er loest es, indem er die Antwort
unleserlich macht. Kennung und Herkunft gehoeren in eigene Felder, und die Eindeutigkeit gilt
ueber beide zusammen.

## Acceptance criteria
- [ ] Ein ueber einen Provider angelegter Benutzer hat einen Passwort-Hash, gegen den keine Eingabe passt. Ein Test versucht die Anmeldung mit dem Benutzernamen als Passwort und erwartet eine Abweisung.
- [ ] Die Kennung des Fremdsystems steht lesbar in der Datenbank, nicht in einem MD5-Praefix.
- [ ] Zwei Provider, die dieselbe Kennung liefern, ergeben zwei Konten — die Eindeutigkeit gilt ueber Provider **und** Kennung.
- [ ] Ein bereits vorhandener Benutzer wird wiedergefunden statt ein zweites Mal angelegt.
- [ ] Der Riegel „nur ueber Provider authorisierbar" bleibt — er ist jetzt die zweite Sicherung, nicht die einzige.
- [ ] Der Charakterisierungstest zum MD5-Praefix ist umgedreht, nicht geloescht.

## Verification
Integrationstests ueber HTTP: Anmeldung ueber den Provider legt an, ein zweiter Lauf findet
wieder; die Anmeldung mit dem Benutzernamen als Passwort scheitert; zwei Provider mit derselben
Kennung ergeben zwei Zeilen. Ein Blick in `pim_user` zeigt die Kennung im Klartext. Volle Suite.
