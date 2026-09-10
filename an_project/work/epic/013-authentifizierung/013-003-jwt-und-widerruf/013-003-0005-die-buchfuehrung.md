---
id: 013-003-0005
title: Die Buchführung
status: todo
depends_on: [013-003-0001, 013-003-0002, 013-003-0003, 013-003-0004]
---

# Die Buchführung

## Context
Was diese Story an Betriebswissen erzeugt, ist wertlos, solange es nur im Code steht: welche
Claims ein Token trägt und welche bewusst nicht, welche Umgebungsvariablen es braucht, wie ein
Schlüsselwechsel abläuft, und was ein Bestandsprojekt beim Update zu tun hat.

## Acceptance criteria
- [ ] Der Claim-Satz ist dokumentiert: was drinsteht, was bewusst **nicht**, und warum.
- [ ] Der Betrieb steht im Runbook beziehungsweise in `deployment.md`: welche Umgebungsvariablen nötig sind, welche Werte sie brauchen, und wie ein Schlüsselwechsel Schritt für Schritt abläuft.
- [ ] Die Bruchstellen der Story stehen in `an_project/docs/breaking-changes.md`, je mit dem, was ein Projekt zu tun hat.
- [ ] Der Authentifizierungs-Abschnitt in `an_project/docs/technical.md` ist nachgezogen.
- [ ] Die Gates sind grün: volle Suite auf PHP 8.3 **und** 8.4, PHPStan, `composer audit --locked`, Deprecation-Log.

## Verification
Volle Suite auf beiden PHP-Versionen, PHPStan, `composer audit --locked`. Die Doku wird gegen
den Code gelesen, nicht aus dem Gedächtnis geschrieben — jede genannte Umgebungsvariable und
jeder genannte Claim muss sich im Baum wiederfinden.
