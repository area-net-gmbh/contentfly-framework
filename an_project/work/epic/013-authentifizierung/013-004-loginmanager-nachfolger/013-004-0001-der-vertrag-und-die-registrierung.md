---
id: 013-004-0001
title: Der Vertrag und die Registrierung
status: review
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
- [x] Ein Projekt registriert einen Provider unter einem Namen, ohne Framework-Code zu aendern.
- [x] `POST /auth/login` waehlt ueber diesen Namen. Ein unbekannter Name wird abgewiesen — ununterscheidbar wie jeder andere Fehlschlag.
- [x] **Ein Klassenname im Request waehlt keine Klasse mehr aus.** Ein Test zeigt einen gueltigen Klassennamen vor und erwartet eine Abweisung.
- [x] Der Vertrag benennt, was das Projekt liefert und was das Framework beisteuert; die Pruefung gegen das Fremdsystem ist die einzige Pflicht des Projekts.
- [x] Die alte Aufloesung ueber `Custom\Classes\<Name>` und `Plugins\…` ist entfallen, nicht danebengestellt.

## Verification
Ein Test, der einen Provider registriert und sich ueber seinen Namen anmeldet; einer, der einen
unbekannten Namen schickt; einer, der einen Klassennamen schickt. Volle Suite.

## Ergebnis

**Der Name wählt aus einer Allowlist, nicht eine Klasse.** `AuthController::providerKlasse()`
und `getLoginProvider()` gibt es nicht mehr.

### Der Vertrag, in drei Stücken

| Stück | trägt |
|---|---|
| `Anmeldeprovider` (Interface) | **eine** Pflicht des Projekts: `pruefen(Request): ?Fremdkennung` |
| `Fremdkennung` | was das Fremdsystem sagt — Kennung, Gruppen, Attribute; unveränderlich |
| `Anbieterverzeichnis` | die Allowlist, gefüllt aus `custom/app.php` |

**Ein Provider fasst die Datenbank nicht mehr an.** Vorher musste `LoginManager::auth()` eine
fertige `User`-Entity liefern und dafür `createManagedUser()` rufen — damit lag die
Provisionierung im Projekt, jedes für sich, mit jeweils eigenen Fehlern. `setPass($alias)` ist
das prominenteste Ergebnis dieser Aufteilung.

`Fremdkennung` ist `readonly`. Ein Wert, den die Anmeldung unterwegs noch ändern könnte, wäre ein
Einfallstor: Ein Hook, der `kennung` umschreibt, meldete jemand anderen an.

### Der Unterschied, den die Allowlist macht

Aus „der Aufrufer sagt, was geladen wird" wird „der Aufrufer wählt aus dem, was der Betreiber
freigegeben hat". Ein Name, den niemand eingetragen hat, existiert nicht — **und ein Klassenname
ist so ein Name.** Der Test schickt drei davon, darunter den der alten Basisklasse selbst.

Zwei Entscheidungen im Verzeichnis, die nicht offensichtlich sind:

- **Ein zweiter Eintrag unter demselben Namen wird abgewiesen.** Stillschweigend zu
  überschreiben hiesse, dass die Reihenfolge zweier Zeilen in `custom/app.php` darüber
  entscheidet, gegen welches Fremdsystem geprüft wird.
- **Der Eintrag ist faul.** Ein Provider baut womöglich eine Verbindung zu einem Fremdsystem
  auf; das darf nicht bei jedem Request passieren, sondern erst, wenn ihn jemand anfragt.

### Ein unbekannter Name fällt nicht auf das Passwort zurück

Sonst wäre ein Tippfehler im Providernamen eine stille Anmeldung über den falschen Weg — mit
richtigem Passwort sogar eine erfolgreiche. Und ein geratener Name wäre ein Orakel dafür, welche
Fremdsysteme diese Installation kennt.

### Der Provider erzählt nichts mehr

Vorher stand die Meldung einer Ausnahme aus dem Provider **wörtlich in der Antwort** —
`return $abweisen($e->getMessage())`. Ein LDAP-Fehler landete damit samt Servernamen beim
Aufrufer. Jetzt ist auch eine Ausnahme schlicht eine Ablehnung.

### Was dieser Task bewusst nicht tut

**Keine Provisionierung.** Der Weg findet einen Benutzer, den es schon gibt; anlegen, Passwort
sperren und die Fremdkennung in einer eigenen Spalte führen ist `013-004-0002`. Bis dahin bleibt
`createManagedUser()` unangetastet — es hat nur keinen Aufrufer mehr im Framework.

**Der end-to-end-Nachweis „registrieren und anmelden" fehlt noch, und das ist gesagt statt
verschwiegen.** Die Verification dieses Tasks nennt ihn; er braucht einen Provider, den man
wirklich laufen lassen kann, und den gibt es erst mit `013-004-0004` — dessen Context sagt
denselben Satz von der anderen Seite. Gemessen ist hier die Mechanik (acht Unit-Tests am
Verzeichnis) und die Abweisungsseite über HTTP (drei Klassennamen, ein unbekannter Name, die
Gegenprobe ohne Parameter).

Der Parameter heisst weiterhin `loginManager`. Bestandsclients schicken ihn so, und ihn
umzubenennen wäre ein Bruch am Draht ohne Gewinn innerhalb dieser Story; er gehört zu Epic `007`.

### Nachweis

| Probe | Ergebnis |
|---|---|
| Volle Suite | `OK (415 tests, 1032 assertions)`, 0 übersprungen (vorher 404) |
| PHPStan | `[OK] No errors` |
| Deprecations | 0 protokollierte Zeilen |
| Klassenname im Request | 401, kein Token — drei Varianten |
| Verzeichnis im Auslieferungszustand | leer |

`LoginProviderAufloesungTest` ist umgedreht, nicht gelöscht: Er hielt die Auflösung fest, die
`013-001-0005` repariert hat, und hält jetzt fest, dass es sie nicht mehr gibt. Ein Test, der
eine entfernte Mechanik bewacht, ist die einzige Art zu merken, wenn jemand sie zurückbaut.
