---
id: 013-004-0005
title: Der Bruch für Epic 007
status: todo
depends_on: [013-004-0001, 013-004-0002, 013-004-0003, 013-004-0004]
---

# Der Bruch für Epic 007

## Context
Die alte `LoginManager`-Schnittstelle faellt, und jedes Bestandsprojekt, das einen hat, muss ihn
ueberfuehren. Was dabei zu tun ist, weiss nach dieser Story genau eine Person — solange es
niemand aufschreibt.

**Die Frage, die Epic `007` beantworten muss, gehoert hier gestellt und beantwortet, soweit es
geht:** Wie kommt ein vorhandener Manager auf den neuen Vertrag, und was passiert mit den
Benutzern, die `createManagedUser()` mit MD5-Praefix angelegt hat? Die Zeilen stehen in
`pim_user` und tragen einen Alias, den niemand mehr erzeugt.

## Acceptance criteria
- [ ] Die Bruchstellen stehen in `an_project/docs/breaking-changes.md`, je mit dem, was ein Projekt zu tun hat.
- [ ] Der Weg vom alten `LoginManager` zum neuen Vertrag ist beschrieben — Schritt fuer Schritt, nicht als Absichtserklaerung.
- [ ] Ueber die Altbestaende mit MD5-Praefix ist entschieden und begruendet: uebernehmen, umschreiben oder stehenlassen.
- [ ] `an_project/docs/technical.md` ist nachgezogen; Befund A-6 ist durchgestrichen.
- [ ] Die Gates sind gruen: volle Suite auf PHP 8.3 **und** 8.4, PHPStan, `composer audit --locked`, Deprecation-Log.

## Verification
Volle Suite auf beiden PHP-Versionen, PHPStan, `composer audit --locked`. Die Doku wird gegen den
Code gelesen, nicht aus dem Gedaechtnis.
