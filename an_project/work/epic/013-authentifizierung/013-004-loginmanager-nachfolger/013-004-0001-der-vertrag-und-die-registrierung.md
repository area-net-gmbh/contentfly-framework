---
id: 013-004-0001
title: Der Vertrag und die Registrierung
status: todo
depends_on: []
---

# Der Vertrag und die Registrierung

## Context
Heute kommt der Klassenname als Request-Parameter `loginManager` und wird zu
`Custom\Classes\<Name>` aufgeloest. Der Praefix und die `instanceof`-Pruefung begrenzen den
Schaden — **aber die Auswahl gehoert nicht in die Hand des Aufrufers.** Welche Klasse eine
Anwendung instanziiert, ist eine Entscheidung des Betreibers.

Es gibt genau einen Einstieg: `AuthController::getLoginProvider()`. Der Umbau ist damit lokal.

**Ein Name statt einer Klasse.** Ein Projekt registriert seine Provider unter einem Namen —
`ldap`, `saml`, was es braucht —, der Request nennt diesen Namen, und ein Name, den niemand
registriert hat, wird abgewiesen. Der Unterschied ist nicht kosmetisch: Aus „der Aufrufer sagt,
was geladen wird" wird „der Aufrufer waehlt aus dem, was der Betreiber freigegeben hat".

**Der Vertrag wird zugleich neu gefasst.** `LoginManager` hat genau eine Pflichtmethode `auth()`
und den Helfer `createManagedUser()`. Alles darueber hinaus — Rollen, Gruppen, Abmelden — muss
heute jedes Projekt neu erfinden. Was das Projekt beisteuert (Pruefung gegen das Fremdsystem)
und was das Framework beisteuert (Benutzer finden oder anlegen, Token ausstellen) gehoert
getrennt benannt.

## Acceptance criteria
- [ ] Ein Projekt registriert einen Provider unter einem Namen, ohne Framework-Code zu aendern.
- [ ] `POST /auth/login` waehlt ueber diesen Namen. Ein unbekannter Name wird abgewiesen — ununterscheidbar wie jeder andere Fehlschlag.
- [ ] **Ein Klassenname im Request waehlt keine Klasse mehr aus.** Ein Test zeigt einen gueltigen Klassennamen vor und erwartet eine Abweisung.
- [ ] Der Vertrag benennt, was das Projekt liefert und was das Framework beisteuert; die Pruefung gegen das Fremdsystem ist die einzige Pflicht des Projekts.
- [ ] Die alte Aufloesung ueber `Custom\Classes\<Name>` und `Plugins\…` ist entfallen, nicht danebengestellt.

## Verification
Ein Test, der einen Provider registriert und sich ueber seinen Namen anmeldet; einer, der einen
unbekannten Namen schickt; einer, der einen Klassennamen schickt. Volle Suite.
