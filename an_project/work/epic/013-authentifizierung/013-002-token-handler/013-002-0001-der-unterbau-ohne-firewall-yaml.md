---
id: 013-002-0001
title: Der Unterbau: security-http ohne Firewall-YAML
status: todo
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
- [ ] `symfony/security-http` steht im Root-Manifest; `composer audit --locked` bleibt ohne Advisories und ohne abandoned Pakete.
- [ ] `Areanet\PIM\Entity\User` erfüllt `Symfony\Component\Security\Core\User\UserInterface`. `getUserIdentifier()` liefert die Kennung; `getRoles()` bildet **nur** ab, was der Zugriffsschutz braucht — `Permission`, `I18nPermission`, `Group` und `isAdmin` bleiben unangetastet.
- [ ] Ein Benutzerlader lädt einen Benutzer über seine Kennung und weist einen gesperrten ab.
- [ ] Ein Treiber fährt den `AccessTokenAuthenticator` ohne Firewall und liefert den Benutzer oder null.
- [ ] Der alte Weg ist unverändert: Kein Aufrufer von `checkToken()` ist angefasst.

## Verification
Unit-Tests für Benutzerlader und Treiber, gegen einen Handler- und einen Extractor-Stub — der
Treiber ist damit prüfbar, bevor es einen echten Handler gibt. Dazu die volle Suite (sie muss
unverändert grün bleiben, denn nichts ist verdrahtet), PHPStan und `composer audit --locked`.
