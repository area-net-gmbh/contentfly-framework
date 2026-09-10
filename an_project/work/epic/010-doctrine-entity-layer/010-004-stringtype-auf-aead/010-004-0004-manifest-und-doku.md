---
id: 010-004-0004
title: Manifest, Dokumente und Bruchstellen
status: review
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
- [x] `ext-sodium` und `ext-openssl` stehen in `composer.json`, und `composer check-platform-reqs`
      erfüllt beide in beiden Pipeline-Images.

> **Vorgezogen nach `010-004-0002`.** Die Angabe gehörte dem Wortlaut nach hierher, aber
> dieselbe Klasse braucht ab dem Verfahrenswechsel beide Erweiterungen — sie dort wegzulassen
> wäre eine halbe Angabe gewesen. Hier nur noch nachgemessen.
- [x] Die Bruchstellen stehen in `breaking-changes.md`: Format, Lesbarkeit des alten Stands, der Befehl, das neue Verhalten bei Manipulation.
- [x] Der Ablauf für ein Bestandsprojekt ist beschrieben — Trockenlauf, Sicherung, Lauf, Prüfung.
- [x] Die Erklärung in `custom/config.php` beschreibt die Ableitung statt des rohen Schlüssels.
- [x] Die Gates bleiben grün, und der Lauf auf PHP 8.4 ebenfalls.

## Verification
`composer install` in `php:8.3-cli` und `php:8.4-cli`, volle Suite, `composer audit --locked`,
PHPStan. Dazu ein Blick auf das, was ein Bestandsprojekt liest: Ist der Ablauf in der
Dokumentation ohne Rückfrage durchführbar?

## Ergebnis

**Die Bruchstellen stehen, der Ablauf ist beschrieben, und die Vorlage erklärt die Ableitung.**

### Vier Bruchstellen in `breaking-changes.md`

| Eintrag | Was ein Projekt tun muss |
|---|---|
| XChaCha20-Poly1305 statt AES-CBC | nichts Zwingendes — jeder Wert stellt sich beim nächsten Schreiben um |
| Ein manipulierter Wert wirft jetzt | nichts; wer die Meldung sieht, hat ein Problem, das vorher unsichtbar war |
| `SECURITY_CIPHER_KEY` wird abgeleitet | nichts — die Ableitung ist deterministisch |
| `ext-sodium` und `ext-openssl` angefordert | nichts auf üblichen Installationen |

**Alle vier sagen „nichts zu tun", und das ist kein Versehen.** Der Schnitt aus `010-004-0002`
war genau darauf angelegt: Das Format steht am Chiffretext, also liest eine aktualisierte
Instanz ihre Bestandsdaten weiter. Was bleibt, ist eine **Verhaltensänderung ohne
Handlungsbedarf** — mit einer Ausnahme, und die ist die wichtigste: Wo die Anwendung bisher bei
einem beschädigten Wert irgendetwas zurückgab, verweigert sie jetzt.

### Die Vorlage erklärt jetzt, was der Schlüssel ist

`custom/config.php` beschrieb `SECURITY_CIPHER_KEY` als den Schlüssel. Er **ist** keiner — er ist
die Passphrase, aus der einer abgeleitet wird. Dazu steht jetzt dort, was fehlte: dass ein
erzeugtes Geheimnis besser ist als eine ausgedachte Passphrase (`openssl rand -base64 32`), und
dass eine Änderung vorhandene Werte unlesbar macht.

Nebenbei richtiggestellt: Der Kommentar nannte noch die Annotationsform `@PIM\Config(encoded=true)`.
Seit `010-001` ist es ein Attribut.

### Der Ablauf ist in `deployment.md` durchführbar beschrieben

Sicherung, Trockenlauf, Lauf, zweiter Trockenlauf als Prüfung — mit dem Hinweis, dass ein
abgebrochener Lauf kein Schaden ist und wofür die Sicherung wirklich da ist.

### Nachweis

| Probe | Ergebnis |
|---|---|
| Volle Suite | `OK (282 tests, 692 assertions)`, 0 übersprungen |
| PHPStan | `[OK] No errors` |
| Deprecation-Gate | 0 Zeilen, 0 Ausnahmen |
| `composer check-platform-reqs` in `php:8.3-cli` | beide Erweiterungen erfüllt |
| `composer check-platform-reqs` in `php:8.4-cli` | beide Erweiterungen erfüllt |
