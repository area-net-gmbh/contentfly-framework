---
id: 013-004-0002
title: Provisionierung ohne setzbares Passwort
status: review
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
- [x] Ein ueber einen Provider angelegter Benutzer hat einen Passwort-Hash, gegen den keine Eingabe passt. Ein Test versucht die Anmeldung mit dem Benutzernamen als Passwort und erwartet eine Abweisung.
- [x] Die Kennung des Fremdsystems steht lesbar in der Datenbank, nicht in einem MD5-Praefix.
- [x] Zwei Provider, die dieselbe Kennung liefern, ergeben zwei Konten — die Eindeutigkeit gilt ueber Provider **und** Kennung.
- [x] Ein bereits vorhandener Benutzer wird wiedergefunden statt ein zweites Mal angelegt.
- [x] Der Riegel „nur ueber Provider authorisierbar" bleibt — er ist jetzt die zweite Sicherung, nicht die einzige.
- [x] Der Charakterisierungstest zum MD5-Praefix ist umgedreht, nicht geloescht.

## Verification
Integrationstests ueber HTTP: Anmeldung ueber den Provider legt an, ein zweiter Lauf findet
wieder; die Anmeldung mit dem Benutzernamen als Passwort scheitert; zwei Provider mit derselben
Kennung ergeben zwei Zeilen. Ein Blick in `pim_user` zeigt die Kennung im Klartext. Volle Suite.

## Ergebnis

**Befund A-6 ist geschlossen.** Ein über ein Fremdsystem angelegter Benutzer hat kein Passwort —
nicht ein zufälliges, sondern gar keines.

### Ein Stern, kein Zufallswert

`User::PASSWORT_GESPERRT` ist `'*'`, wie in `/etc/shadow` seit jeher: kein gültiger Hash, keiner
Eingabe zuzuordnen, und **man sieht der Zeile an, dass es Absicht war.** Ein Zufallswert täte
dasselbe, aber niemand könnte ihn von einem echten Hash unterscheiden.

`isPass()` prüft das ausdrücklich, statt sich auf die Nebenwirkung zu verlassen. Der Stern ist
kein gültiger Hash, weshalb die beiden Zweige darunter jede Eingabe ohnehin abwiesen — eine
Sicherheitszusicherung aus einer Nebenwirkung zu beziehen heisst, sie bei der nächsten Änderung
an der Hashform zu verlieren, ohne dass jemand es bemerkt.

`brauchtNeuenHash()` gibt bei einem gesperrten Passwort `false` zurück. Ohne diese Zeile hielte
es den Stern für einen Altformat-Hash, und der Login-Upgrade aus `013-001-0001` versuchte, ihn
durch das vorgezeigte Passwort zu ersetzen.

**Gesperrt wird, bevor irgendetwas anderes passiert** — nicht am Ende der Methode. Ein `return`
oder eine Ausnahme dazwischen hinterliesse sonst eine Zeile mit dem Vorgabewert der Spalte und
ein Konto, das jemand übernehmen kann.

### Der MD5-Präfix ist weg, die Eindeutigkeit geblieben

| | vorher | jetzt |
|---|---|---|
| Alias | `md5(<klasse>)-<kennung>` | `<provider>:<kennung>` |
| Herkunft | im Präfix versteckt | Spalte `loginManager`, jetzt ein **Name** statt eines Klassennamens |
| Kennung | im Alias verschmolzen | Spalte `externalId`, lesbar |
| Eindeutigkeit | aus dem Präfix | Bedingung über `loginManager` **und** `externalId` |

Der Präfix löste ein echtes Problem — zwei Fremdsysteme, die denselben Benutzernamen liefern,
dürfen nicht dasselbe Konto bekommen. Er löste es nur, indem er die Antwort unleserlich machte:
Wer in `pim_user` nachsah, fand `3f2a…-mueller` und wusste nicht, wer das ist.

### Die Provisionierung gehört ins Framework

`createManagedUser()` sass auf der abstrakten `LoginManager`-Klasse und wurde aus dem
Projekt-Code gerufen — jedes Projekt konnte es anders machen oder vergessen. `setPass($alias)`
ist das prominenteste Ergebnis dieser Aufteilung.

`Benutzerbereitstellung` macht es jetzt einmal, und ein Provider fasst die Datenbank gar nicht
mehr an. **`Areanet\PIM\Classes\Manager\LoginManager` ist damit entfallen**; der verwaiste
Import in `Classes/Mailer.php` ist mit weg.

**Ein vorhandener Benutzer wird nicht nachträglich gesperrt.** Ein Administrator kann einem Konto
einen Provider zuordnen, das schon existierte; die Sperre gehört zum **Anlegen**, nicht zum
Anmelden. Ein eigener Test hält das fest.

### Was dieser Task nicht misst

Die Verification nennt „Anmeldung über den Provider legt an" über HTTP. Das braucht einen
Provider, den man wirklich laufen lassen kann — `013-004-0004`. Gemessen ist hier stattdessen:

| Probe | wo |
|---|---|
| Gesperrtes Passwort, Alias und Kennung, zwei Provider, Wiederfinden | acht Unit-Tests an der Bereitstellung |
| Der Benutzername als Passwort öffnet nichts | über HTTP, gegen eine direkt eingefügte Zeile |
| Die Bedingung steht über **beiden** Spalten | über `information_schema` |
| Der Riegel steht weiterhin | über HTTP |

### Nachweis

| Probe | Ergebnis |
|---|---|
| Volle Suite | `OK (425 tests, 1065 assertions)`, 0 übersprungen (vorher 415) |
| PHPStan | `[OK] No errors` |
| Deprecations | 0 protokollierte Zeilen |

Der Charakterisierungstest zum MD5-Präfix ist umgedreht, nicht gelöscht: Er hielt fest, wie die
Eindeutigkeit zustande kam, und hält jetzt fest, dass sie aus der Spaltenbedingung kommt.
