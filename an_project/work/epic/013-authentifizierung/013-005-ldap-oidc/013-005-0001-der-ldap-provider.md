---
id: 013-005-0001
title: Der LDAP-Provider
status: done
depends_on: []
---

# Der LDAP-Provider

## Context
Der erste der beiden Faelle, die in der Praxis gefragt werden — und der Nachweis, dass der
Vertrag aus `013-004` traegt.

**Symfony bringt das Meiste mit.** `symfony/ldap` kapselt Bind und Suche; was ein
Anmeldeprovider daraus macht, sind wenige Zeilen. Die Arbeit steckt im Drumherum, und die Story
nennt drei Fragen. Zwei davon gehoeren hierher:

**Wie wird gebunden?** Zwei Wege, und sie schliessen einander nicht aus:

  Direkter Bind      Die Anwendung bindet mit der Kennung des Benutzers und seinem Passwort.
                     Kein Dienstkonto noetig; dafuer muss der DN aus der Kennung ableitbar
                     sein, und Gruppen liest man erst danach.
  Dienstkonto        Die Anwendung bindet mit einem eigenen Konto, sucht den Benutzer und
                     bindet dann ein zweites Mal mit dessen Passwort. Braucht ein Geheimnis
                     mehr, findet den Benutzer aber ueber jedes Attribut.

**Woher kommen die Gruppen?** Aus `memberOf` am Benutzereintrag, oder aus einer eigenen Suche
ueber die Gruppenobjekte. Was das Verzeichnis liefert, geht unveraendert in die `Fremdkennung`;
abgebildet wird es von `Gruppenabbildung` (`013-004-0003`), nicht hier.

**Die dritte Frage — der verschwundene Benutzer — ist `013-005-0002`.**

## Einschraenkung, ausdruecklich
Geprueft wird gegen einen `LdapInterface`-Doppelgaenger, nicht gegen ein laufendes Verzeichnis.
Das misst die eigene Logik — Bind-Reihenfolge, Suchfilter, was in die `Fremdkennung` geht — und
**nicht**, dass ein echter Bind gegen ein AD funktioniert. Entschieden am 2026-09-11; ein
OpenLDAP-Dienst in der Testumgebung waere der Preis dafuer, und er steht in keinem Verhaeltnis
zu dem, was er zusaetzlich belegte. Das gehoert ins Ergebnis, nicht in eine Fussnote.

## Acceptance criteria
- [x] `symfony/ldap` steht im Root-Manifest; `composer audit --locked` bleibt ohne Advisories.
- [x] Ein `LdapProvider` erfuellt `Anmeldeprovider` und fasst die Datenbank nicht an.
- [x] Der Bind-Weg ist entschieden und **im Code begruendet**, nicht nur umgesetzt.
- [x] Die Konfiguration (Host, Basis-DN, Filter, ggf. Dienstkonto) kommt aus der Umgebung; kein Geheimnis steht in einer Datei im Repo.
- [x] Was das Verzeichnis an Gruppen liefert, geht unveraendert in die `Fremdkennung` — die Abbildung bleibt bei `Gruppenabbildung`.
- [x] Ein falsches Passwort, eine unbekannte Kennung und ein Verzeichnis, das nicht antwortet, enden alle in `null` — ununterscheidbar.
- [x] Die Einschraenkung „gegen Doppelgaenger geprueft, nicht gegen ein Verzeichnis" steht im Ergebnis.

## Verification
Unit-Tests gegen einen `LdapInterface`-Doppelgaenger: gelungener Bind, falsches Passwort,
unbekannte Kennung, Verzeichnis nicht erreichbar, Gruppen aus dem Eintrag. Volle Suite.

## Ergebnis

**`LdapProvider` erfüllt den Vertrag aus `013-004` und fasst die Datenbank nicht an.** Er prüft
gegen das Verzeichnis und gibt eine `Fremdkennung` zurück — mehr ist seine Aufgabe nicht.

### Suchen, dann binden — und warum nicht der direkte Bind

Der direkte Weg setzt den DN aus der Kennung zusammen (`uid=<kennung>,ou=…`) und kommt ohne
Dienstkonto aus. Er funktioniert aber nur, solange alle Benutzer flach in **einer** OU liegen.
**Im Active Directory tun sie das nicht:** Dort hängen sie in verschachtelten
Organisationseinheiten, und angemeldet wird mit `sAMAccountName`, der im DN überhaupt nicht
vorkommt. Ein Framework, das nur den einfachen Fall kann, ist für den Fall, um den es hier geht,
nutzlos.

Also: mit dem Dienstkonto binden, den Benutzer suchen, dann ein zweites Mal mit **seinem** DN und
**seinem** Passwort binden. Wo das Verzeichnis eine anonyme Suche erlaubt, bleibt das
Dienstkonto leer — ein eigener Test deckt den Fall ab.

Ein Test prüft die Reihenfolge der Aufrufe selbst: `bind(Dienstkonto)` · `query(Basis-DN)` ·
`bind(DN aus dem Verzeichnis)`. Der dritte Aufruf benutzt den DN, den das Verzeichnis geliefert
hat, und keinen gebauten.

### Die wichtigste Zeile: das leere Passwort

LDAP kennt den **unauthenticated bind**. Ein Bind mit gültigem DN und leerem Passwort gilt als
erfolgreich — er bedeutet „ich will mich nicht anmelden", nicht „das Passwort stimmt". Wer das
Ergebnis als Anmeldung liest, lässt jeden herein, dessen Kennung er kennt. Es ist einer der
ältesten Fehler in LDAP-Anbindungen.

Der Provider weist ein leeres Passwort ab, **bevor er das Verzeichnis überhaupt anspricht**, und
der Test prüft beides: dass abgelehnt wird, und dass kein einziger Aufruf stattfand.

### Maskiert, und zwar als Filter

Ohne `escape()` trägt eine Kennung wie `admin)(|(objectClass=*` den Suchfilter um —
LDAP-Injection, dasselbe Muster wie SQL-Injection und genauso alt. Ein Test schickt genau diese
Zeichenkette und sieht im Filter nach.

**Der Doppelgänger hatte dabei selbst einen Fehler**, und er ist lehrreich: Sein `escape()` nahm
`str_replace()` mit Arrays, und das arbeitet die Paare **nacheinander** ab — die letzte Regel
maskierte noch einmal die Backslashes, die die vorherigen gerade eingefügt hatten. Aus `\28`
wurde `\5c28`. Mit `strtr()` in einem Durchgang stimmt es. Der Provider war nie betroffen; der
Test wäre es gewesen.

### Jeder Fehlschlag sieht gleich aus

Sechs Wege hinein, ein Weg hinaus:

| Fall | Antwort |
|---|---|
| Kennung unbekannt (kein Treffer) | `null` |
| Zwei Treffer — der Filter ist nicht eindeutig | `null` |
| Falsches Passwort | `null` |
| Verzeichnis nicht erreichbar | `null` |
| Dienstkonto abgelehnt | `null` |
| Leeres Passwort oder leere Kennung | `null`, ohne Rückfrage ans Verzeichnis |

Bei zwei Treffern zu raten, welcher gemeint war, wäre die schlechteste aller Antworten.

### Die Gruppen gehen unverändert weiter

Was in `memberOf` steht, landet so in der `Fremdkennung`. Abgebildet wird es von
`Gruppenabbildung` (`013-004-0003`) — hier etwas umzuschreiben hiesse, die Abbildung an zwei
Stellen zu haben.

### Einschränkung: gegen Doppelgänger geprüft, nicht gegen ein Verzeichnis

**Das misst die eigene Logik und nicht, dass ein echter Bind gegen ein Active Directory
funktioniert.** Bind-Reihenfolge, Maskierung, Trefferzahl, was in die `Fremdkennung` geht — alles
davon ist geprüft. Ob ein AD auf `(sAMAccountName=…)` so antwortet, wie der Provider es erwartet,
ist es nicht.

So entschieden am 2026-09-11: Ein OpenLDAP-Dienst in der Testumgebung wäre der Preis für einen
echten Bind gewesen, dazu `ext-ldap` im CI-Image — und beides steht in keinem Verhältnis zu dem,
was es zusätzlich belegte. **Der Satz steht hier und nicht in einer Fussnote**, weil eine grüne
Suite sonst mehr zu versprechen scheint, als sie hält.

### Nachweis

| Probe | Ergebnis |
|---|---|
| Volle Suite | `OK (455 tests, 1124 assertions)`, 0 übersprungen (vorher 442) |
| PHPStan | `[OK] No errors` |
| Deprecations | 0 protokollierte Zeilen |
| `composer audit --locked` | 0 Advisories nach `symfony/ldap` |

Dreizehn Tests, davon sechs für die Abweisungen. Der Provider ist **nicht** vorregistriert: Die
Vorlage zeigt den Eintrag auskommentiert, und das bleibt die Entscheidung des Projekts.
