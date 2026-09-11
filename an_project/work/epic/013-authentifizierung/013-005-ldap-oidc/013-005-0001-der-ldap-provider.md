---
id: 013-005-0001
title: Der LDAP-Provider
status: todo
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
- [ ] `symfony/ldap` steht im Root-Manifest; `composer audit --locked` bleibt ohne Advisories.
- [ ] Ein `LdapProvider` erfuellt `Anmeldeprovider` und fasst die Datenbank nicht an.
- [ ] Der Bind-Weg ist entschieden und **im Code begruendet**, nicht nur umgesetzt.
- [ ] Die Konfiguration (Host, Basis-DN, Filter, ggf. Dienstkonto) kommt aus der Umgebung; kein Geheimnis steht in einer Datei im Repo.
- [ ] Was das Verzeichnis an Gruppen liefert, geht unveraendert in die `Fremdkennung` — die Abbildung bleibt bei `Gruppenabbildung`.
- [ ] Ein falsches Passwort, eine unbekannte Kennung und ein Verzeichnis, das nicht antwortet, enden alle in `null` — ununterscheidbar.
- [ ] Die Einschraenkung „gegen Doppelgaenger geprueft, nicht gegen ein Verzeichnis" steht im Ergebnis.

## Verification
Unit-Tests gegen einen `LdapInterface`-Doppelgaenger: gelungener Bind, falsches Passwort,
unbekannte Kennung, Verzeichnis nicht erreichbar, Gruppen aus dem Eintrag. Volle Suite.
