---
id: 013-002-0001
title: Der Unterbau: security-http ohne Firewall-YAML
status: done
depends_on: []
---

# Der Unterbau: security-http ohne Firewall-YAML

## Context
Der Umfang der Story zeigt eine `firewalls:`-Konfiguration. **Die gibt es hier nicht:** Der Baum
hat kein `config/`-Verzeichnis und kein SecurityBundle — der Kernel ist der eigene aus Epic
`009`, mit `before()`-Hooks je Provider.

Das ist kein Hindernis. `AccessTokenAuthenticator` ist eine gewöhnliche Klasse aus
`symfony/security-http` und braucht keine Firewall, sondern einen Handler, einen Extractor und
optional einen Benutzerlader. Nachgelesen an der Klasse: `supports()` fragt den Extractor,
`authenticate()` liefert einen `SelfValidatingPassport` mit dem `UserBadge`, das der Handler
gebaut hat, und `$passport->getUser()` löst ihn auf. Ein Treiber, der das im `before()`-Hook
fährt, sind wenige Zeilen — die Firewall-Maschinerie mit `AuthenticatorManager`, `FirewallMap`
und `TokenStorage` braucht es dafür nicht.

**Dieser Task verdrahtet nichts.** `checkToken()` bleibt die einzige Autorität; der neue Weg
steht daneben und wird für sich geprüft. Erst `013-002-0004` legt den Schalter um. Das ist das
Muster, mit dem Epic `010` gearbeitet hat: erst unter dem alten Stand aufbauen, dann umschalten,
eine messbare Variable je Schritt.

## Acceptance criteria
- [x] `symfony/security-http` steht im Root-Manifest; `composer audit --locked` bleibt ohne Advisories und ohne abandoned Pakete.
- [x] `Areanet\PIM\Entity\User` erfüllt `Symfony\Component\Security\Core\User\UserInterface`. `getUserIdentifier()` liefert die Kennung; `getRoles()` bildet **nur** ab, was der Zugriffsschutz braucht — `Permission`, `I18nPermission`, `Group` und `isAdmin` bleiben unangetastet.
- [x] Ein Benutzerlader lädt einen Benutzer über seine Kennung und weist einen gesperrten ab.
- [x] Ein Treiber fährt den `AccessTokenAuthenticator` ohne Firewall und liefert den Benutzer oder null.
- [x] Der alte Weg ist unverändert: Kein Aufrufer von `checkToken()` ist angefasst.

## Verification
Unit-Tests für Benutzerlader und Treiber, gegen einen Handler- und einen Extractor-Stub — der
Treiber ist damit prüfbar, bevor es einen echten Handler gibt. Dazu die volle Suite (sie muss
unverändert grün bleiben, denn nichts ist verdrahtet), PHPStan und `composer audit --locked`.

## Ergebnis

**Der Unterbau steht, und er ändert nichts.** `checkToken()` ist unangetastet, kein Aufrufer ist
angefasst, die Suite läuft unverändert durch — sie ist nur um sechzehn Tests gewachsen.

### Der Authenticator braucht keine Firewall

Das war die offene Frage des Tasks, und sie ist beantwortet. `AccessTokenAuthenticator` ist eine
gewöhnliche Klasse; nachgelesen an ihr sind es drei Aufrufe:

| Aufruf | tut |
|---|---|
| `supports()` | fragt den Extractor, ob überhaupt ein Token im Request steht |
| `authenticate()` | lässt den Handler ein `UserBadge` bauen und packt es in einen `SelfValidatingPassport` |
| `getUser()` | löst das Badge auf — über dessen eigenen Lader oder den Benutzerlader |

`Anmeldetreiber` ist genau das, plus ein `try`. Die Maschinerie darum — `AuthenticatorManager`,
`FirewallMap`, `TokenStorage`, die `kernel.request`-Listener — bleibt aussen vor. Sie trägt
Zustand über Sitzungen und mehrere Firewalls; Contentfly hat weder das eine noch das andere, die
Sitzung ist mit Story `012-004` entfallen.

### `=== false`, nicht `!`

`supports()` liefert **`null`**, wenn ein Token da ist — die Kennzeichnung für „vielleicht,
entscheide später". Nur `false` heisst „gar kein Token". Ein `!$this->authenticator->supports()`
behandelte beide Fälle gleich und wiese **jeden** Request ab. Ein eigener Test fällt genau dann
um.

### Jeder Fehlschlag sieht gleich aus

Gefangen wird `AuthenticationException`, die gemeinsame Oberklasse. Unbekannte Kennung,
gesperrter Benutzer, abgelaufenes oder manipuliertes Token münden alle in `null`. Dasselbe im
Benutzerlader: **Ein gesperrter Benutzer wirft dieselbe Ausnahme wie ein unbekannter.** Das ist
kein Versehen — eine eigene Ausnahme für „gesperrt" wäre ein Orakel dafür, welche Konten es gibt
und welche gerade abgeschaltet sind.

### Die Grenze zum Berechtigungsmodell

`User` erfüllt jetzt `UserInterface`, und mehr wird auch nicht abgebildet:

| Methode | liefert |
|---|---|
| `getUserIdentifier()` | den `alias` — unique, und das, was ein Mensch als Benutzernamen kennt |
| `getRoles()` | `ROLE_USER`, für einen Administrator zusätzlich `ROLE_ADMIN` |
| `eraseCredentials()` | nichts |

`Permission`, `I18nPermission` und `Group` bleiben, wo sie sind. Wer hier anfängt, Berechtigungen
in Rollen zu übersetzen, baut ein zweites Berechtigungsmodell neben dem vorhandenen — und zwei
Modelle, die dasselbe sagen sollen, laufen auseinander.

`eraseCredentials()` ist seit Symfony 7.3 deprecated, steht aber weiter im Interface und muss
deklariert werden. Sie bleibt leer: `$pass` ist der gespeicherte Argon2id-Hash, kein flüchtiger
Wert. Gerufen wird sie von niemandem — das Deprecation-Gate meldet 0 Zeilen.

### Nachweis

| Probe | Ergebnis |
|---|---|
| Volle Suite | `OK (331 tests, 871 assertions)`, 0 übersprungen (vorher 321) |
| PHPStan | `[OK] No errors` |
| Deprecations | 0 protokollierte Zeilen |
| `composer audit --locked` | 0 Advisories, 0 abandoned |
| `checkToken()`-Aufrufer | unverändert, alle fünf |

`symfony/security-http` bringt fünf Pakete mit (`security-core`, `password-hasher`,
`property-access`, `property-info`, `type-info`), alle aus der Symfony-7.4-Familie — die
Constraint-Kette aus dem Manifest bleibt unberührt.
