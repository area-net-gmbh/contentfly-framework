---
id: 015-000-0010
title: Login-Drossel pro Kennung lässt sich mit Akzent-Varianten des Alias umgehen
status: done
depends_on: []
---

# Login-Drossel pro Kennung lässt sich mit Akzent-Varianten des Alias umgehen

## Context
**Security-Scan 2026-09, MEDIUM. Findings F14 und F32.** F32 beschreibt denselben Fehler und ist
LOW eingestuft.

`LoginThrottle::key` (`Classes/Security/LoginThrottle.php:164`) bildet den Schlüssel für die
Drossel pro Kennung aus `sha256(mb_strtolower(trim(alias)))`. Die Benutzersuche
`findOneBy(['alias' => ...])` (`AuthController.php:248`) läuft dagegen unter der
Standard-Kollation `utf8_unicode_ci` (`Config.php:77`, über `defaultTableOptions` in
`bootstrap.php:256-258`), und die ignoriert Akzente und Zeichenbreite. `admin`, `àdmin`, `ádmin`
und `ａdmin` bekommen je einen eigenen Drossel-Eimer, treffen aber alle dasselbe Konto.

Folge: Die Stufen pro Kennung (5/min, 20/15 min, 50/h) laufen nie voll. Gegen ein einzelnes
Konto, auch den Admin, bleibt nur die Drossel pro IP. Die Kommentare der Klasse nennen gerade die
Kennung als Schutz gegen verteiltes Raten aus vielen Adressen.

## Acceptance criteria
- [x] Nach erfolgreicher Suche drosselt die Kennungs-Achse auf das gefundene Konto (User-ID oder gespeicherter Alias). Unbekannte Namen landen in einem eigenen Eimer.
- [~] Alternativ bzw. zusätzlich wird die Eingabe so gefaltet wie in der Kollation (NFKD, kombinierende Zeichen entfernen, Casefold).
- [x] Die Drossel pro IP bleibt unverändert.

> **Zur zweiten Zeile.** Die Kriterien stellen Falten und Konto-Kennung als Alternativen nebeneinander
> („alternativ bzw. zusätzlich"). Umgesetzt ist die erste Zeile; gefaltet wird **nicht**. Grund: Eine
> Kollation in PHP nachzubilden hiesse, sie ganz nachzubilden und in jeder ihrer Versionen, und
> `ext-intl` ist keine Abhängigkeit des Frameworks. Die Konto-Kennung ist exakt, ohne neue
> Systemabhängigkeit. Deshalb `[~]` statt `[x]`: bewusst nicht gemacht, nicht vergessen.

## Verification
Test: Fehlversuche gegen `admin` mit wechselnden Varianten (`àdmin`, `ádmin`, `ａdmin`, …) über der
Grenze pro Kennung. Vor dem Fix wird nie gedrosselt, nach dem Fix greift die Kennungs-Drossel nach
derselben Zahl von Versuchen wie bei gleichbleibendem `admin`.
