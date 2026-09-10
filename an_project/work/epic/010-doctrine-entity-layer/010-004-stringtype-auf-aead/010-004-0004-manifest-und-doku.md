---
id: 010-004-0004
title: Manifest, Dokumente und Bruchstellen
status: todo
depends_on: [010-004-0003]
---

# Manifest, Dokumente und Bruchstellen

## Context
Der Abschluss: Was das Verfahren für ein Bestandsprojekt bedeutet, und was das Framework
künftig voraussetzt.

- **`ext-sodium`** gehört ins Manifest. Es wird ab `010-004-0002` gebraucht, und beide
  Pipeline-Images bringen es mit — nachgemessen, aber angefordert war es nie. Dasselbe gilt für
  `ext-openssl`, solange das alte Format gelesen wird.
- **`breaking-changes.md`**: das neue Format, die Lesbarkeit des alten, der Befehl, und die
  Tatsache, dass ein manipulierter Wert jetzt eine Ausnahme wirft statt Unsinn zu liefern.
- **`deployment.md` oder `runbook.md`**: wann der Befehl läuft, mit Trockenlauf zuerst.
- **`custom/config.php`**: Die Erklärung zu `SECURITY_CIPHER_KEY` beschreibt heute den rohen
  Schlüssel.

## Acceptance criteria
- [ ] `ext-sodium` und `ext-openssl` stehen in `composer.json`, und `composer install` läuft in beiden Pipeline-Images durch.
- [ ] Die Bruchstellen stehen in `breaking-changes.md`: Format, Lesbarkeit des alten Stands, der Befehl, das neue Verhalten bei Manipulation.
- [ ] Der Ablauf für ein Bestandsprojekt ist beschrieben — Trockenlauf, Sicherung, Lauf, Prüfung.
- [ ] Die Erklärung in `custom/config.php` beschreibt die Ableitung statt des rohen Schlüssels.
- [ ] Die Gates bleiben grün, und der Lauf auf PHP 8.4 ebenfalls.

## Verification
`composer install` in `php:8.3-cli` und `php:8.4-cli`, volle Suite, `composer audit --locked`,
PHPStan. Dazu ein Blick auf das, was ein Bestandsprojekt liest: Ist der Ablauf in der
Dokumentation ohne Rückfrage durchführbar?
