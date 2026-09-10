---
id: 013-002-0003
title: Der verzweigende TokenHandler
status: todo
depends_on: [013-002-0001]
---

# Der verzweigende TokenHandler

## Context
Symfony erlaubt genau **einen** `token_handler` je Firewall und bringt keine Verkettung mit. Die
Verzweigung schreibt man selbst: drei punktgetrennte Segmente und ein plausibler Header →
JWT-Zweig, alles andere → opaquer Zweig.

**Der opaque Zweig ist, was `checkToken()` heute tut:** `pim_token` über den SHA-256 des
vorgezeigten Tokens nachschlagen (`013-001-0004`), Benutzer vorhanden und aktiv, Timeout gegen
`APP_TOKEN_TIMEOUT` oder die Gruppe des Benutzers, ein Token mit `referrer` verfällt nicht — und
der Sliding-Expiration-Write auf `modified`.

**Der JWT-Zweig prüft Signatur und Claims und fasst die Datenbank nicht an.** Ausgestellt werden
JWT erst in `013-003`; hier wird nur verifiziert, und der Test signiert sich sein Token selbst.
Die Schlüsselverwaltung bleibt entsprechend minimal — ein Geheimnis aus der Umgebung. Wechsel,
Übergangszeit und Widerruf gehören zu `013-003`.

**Ununterscheidbar scheitern** ist eine Anforderung, keine Geschmacksfrage: Verschiedene
Fehlermeldungen verraten, welche Tokenart erwartet wird, und damit, welche ein Angreifer bauen
muss.

## Acceptance criteria
- [ ] Ein opaques Token aus `pim_token` und ein selbst signiertes JWT münden in dasselbe `UserBadge` für denselben Benutzer.
- [ ] Der JWT-Zweig setzt **nachweislich** keine Abfrage auf `pim_token` ab.
- [ ] Der Sliding-Expiration-Write passiert nur im opaquen Zweig.
- [ ] Ungültige, abgelaufene und manipulierte Tokens werden in beiden Zweigen abgewiesen — mit gleicher Ausnahme und gleicher Meldung. Ein Test vergleicht beide Antworten miteinander.
- [ ] Das Signaturgeheimnis steht in keiner Datei im Repo. Ohne gesetztes Geheimnis wird der JWT-Zweig abgewiesen statt stillschweigend übersprungen.
- [ ] Der alte Weg ist weiterhin unverändert.

## Verification
Unit-Tests für beide Zweige. Für „kein Datenbankzugriff" wird gezählt, nicht behauptet: ein
Zähler auf dem DBAL-Logger oder ein `EntityManager`, der bei jedem Zugriff wirft. Für die
Ununterscheidbarkeit ein Test, der beide Fehlerantworten gegeneinander hält. Volle Suite.
