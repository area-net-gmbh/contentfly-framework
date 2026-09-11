---
id: 000-000-0028
title: LoadMetadata verlangt eine modified-Spalte von jeder Entity
status: done
depends_on: []
---

# LoadMetadata verlangt eine modified-Spalte von jeder Entity

## Context
`Classes/Events/LoadMetadata` haengt an **jede** Entity, die kein `BaseTree` oder `BaseI18nTree`
ist, einen Index `modified_index` auf die Spalte `modified`. Fehlt die Spalte, scheitert schon
die Installation:

```
Die Installation ist fehlgeschlagen: There is no column with name "modified" on table "pim_revoked_token".
```

**Die Meldung nennt den Grund nicht.** Sie sagt, was fehlt, aber nicht, wer es verlangt — und der
Listener steht an einer Stelle, an der niemand sucht, der gerade eine neue Entity geschrieben
hat. Gefunden bei `013-003-0003`, beim Anlegen von `RevokedToken`; die Spalte steht dort jetzt
mit einem Kommentar, der sagt warum.

**Die Annahme ist nirgends festgehalten.** Ein Projekt, das eine eigene Entity anlegt, die nicht
von `Base` erbt, laeuft in dasselbe — und `an_project/docs/dev-guide.md` erwaehnt es nicht.

**Drei Wege stehen offen, und die Wahl gehoert begruendet:**

1. Der Listener ueberspringt Entities ohne `modified`-Feld. Am wenigsten ueberraschend, aendert
   aber stillschweigend, welche Tabellen den Index bekommen.
2. Der Listener wirft mit einer Meldung, die ihn selbst benennt. Ehrlicher, bricht aber die
   Installation weiterhin ab.
3. Die Anforderung wird dokumentiert und bleibt. Billig, und verschiebt das Problem auf den
   naechsten, der eine Entity schreibt.

## Acceptance criteria
- [x] Der Weg ist gewaehlt und im Code begruendet, nicht nur umgesetzt.
- [x] Eine Entity ohne `modified` fuehrt entweder zu einer Meldung, die `LoadMetadata` benennt, oder gar nicht mehr zum Abbruch — und ein Test haelt fest, welches von beidem gilt.
- [x] Die Anforderung steht in `an_project/docs/dev-guide.md` bei dem, was eine neue Entity braucht.
- [x] `RevokedToken` ist nachgezogen: Bleibt die Spalte, sagt der Kommentar warum; faellt die Anforderung, faellt die Spalte mit.
- [x] Die volle Suite bleibt gruen, und `appcms:install` laeuft durch.

## Verification
Eine Wegwerf-Entity ohne `modified` anlegen und `appcms:install` laufen lassen — vorher und
nachher. Volle Suite, PHPStan.

## Ergebnis

**Gewaehlt ist Weg 1: Der Listener ueberspringt eine Entity ohne `modified`-Feld.** Die
Begruendung steht im Klassenkommentar von `Classes/Events/LoadMetadata`, nicht nur hier — drei
Gruende: Der Index dient den Sync-Endpunkten, an denen eine Entity ohne `modified` ohnehin nicht
teilnimmt; ein fehlender Zeitstempel ist keine Fehlkonfiguration, fuer die man eine Installation
abbricht; und fuer den Bestand aendert es nichts. Der nicht gewaehlte Weg 2 (werfen, aber die
eigene Meldung) steht daneben, mit dem Grund gegen ihn.

**„Fuer den Bestand aendert es nichts" ist gemessen, nicht behauptet** — und die Messung hat
meinen ersten Entwurf widerlegt. Ich hatte „16 Tabellen vorher, 16 nachher" in den Kommentar
geschrieben, bevor ich nachgezaehlt hatte. Eine frische `appcms:install` sagt **16 vorher, 15
nachher**, und der Unterschied ist genau eine Tabelle: `pim_revoked_token`. Die verliert den
Index, weil derselbe Task ihr die `modified`-Spalte genommen hat. Jede andere Tabelle traegt ihn
unveraendert. Der Kommentar sagt jetzt das Gemessene.

**`RevokedToken` ist nachgezogen, und zwar in die zweite Richtung.** Der Task liess beides offen:
Bleibt die Spalte, sagt der Kommentar warum — faellt die Anforderung, faellt die Spalte mit. Die
Anforderung ist gefallen, also ist die Spalte gefallen. Sie hat nie etwas getan: Ein widerrufener
Token wird geschrieben und spaeter geloescht, nie geaendert. Sie existierte allein, um diesen
Listener zufriedenzustellen. An ihrer Stelle steht ein Kommentarblock, der das festhaelt, damit
niemand sie „nachtraegt", weil sie zu fehlen scheint.

**Damit brauchte es keine Wegwerf-Entity fuer die Verification.** Die *Verification* des Tasks
sah eine kuenstliche Entity ohne `modified` vor. `RevokedToken` **ist** diese Entity jetzt, im
echten Baum: `appcms:install` laeuft durch, die Tabelle entsteht mit `id`, `jti`, `expiresAt`,
`created` — und ohne `modified_index`. Geprueft am realen Fall statt am nachgebauten.

**Drei Unit-Tests halten fest, welche der beiden Moeglichkeiten gilt** (`tests/Unit/Kernel/
LoadMetadataTest.php`): Eine Entity mit `modified` bekommt den Index; eine ohne wird
uebersprungen — ohne Exception, ohne Index; Baeume bleiben ausgenommen wie zuvor. Der zweite ist
der eigentliche: Ohne ihn koennte jemand die Bedingung wieder entfernen und nur der
Installations-Lauf wuerde es merken.

**In `an_project/docs/dev-guide.md` steht jetzt ein Abschnitt *Eine neue Entity anlegen***, mit
der Regel und mit dem alten Fehlerbild — wer auf einem aelteren Stand die Doctrine-Meldung
`There is no column with name "modified"` sieht, findet dort die Ursache.

**Zahlen:** Volle Suite `OK (474 tests, 1155 assertions)`, 0 Deprecations bei 0 Ausnahmen, 0 Byte
Postausgang. PHPStan `[OK] No errors`. `appcms:install` laeuft durch.

**Ein Lauf dazwischen war rot, und das gehoert erwaehnt.** Er meldete 5 Fehlschlaege, deren
Ausgabe mein `tail -20` abgeschnitten hatte. Drei Laeufe danach — `AuthApiTest` allein, dann
zweimal die volle Suite — waren gruen. Wahrscheinlichste Ursache: In genau jenem Aufruf lief
`docker compose down -v` unmittelbar vor dem `up -d` der Suite, und die Installation danach
schreibt ihre Ausgabe nach `/dev/null`; ein misslungener Aufbau faellt dann erst als Testfehler
auf. Bewiesen ist das nicht. **Es ist dieselbe Blindheit, die `000-000-0029` behandelt:**
umgeleitete Ausgabe macht einen echten Fehler unsichtbar und laesst ihn als etwas anderes
erscheinen.
