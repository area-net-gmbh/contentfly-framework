---
id: 006-001-0002
title: Beide Vendor-Bäume vollständig erfassen
status: todo
depends_on: [006-001-0001]
---

# Beide Vendor-Bäume vollständig erfassen

## Context
Reine Erhebung. **Hier wird nichts entschieden** — die Zuordnung ist `006-001-0004`, und diese
Trennung ist Absicht: Wer Fakten und Entscheidung vermischt, schreibt die Begründung zur
bereits getroffenen Wahl.

Ohne diese Liste lässt sich weder ein Manifest schreiben noch beurteilen, ob der Ist-Stack
überhaupt auflösbar ist (`006-001-0003`).

## Umfang

### A — Alle 87 Pakete mit den Angaben, die zählen
39 im Root, 48 unter `custom/`. Je Paket:

- Name und installierte Version,
- der `php`-Constraint aus dem eigenen `composer.json`,
- Herkunft: steht es in einer `installed.json`, oder ist es von Hand hineinkopiert?
- wird es im Code benutzt? Ein `grep` über `lib/`, `custom/` und `bin/` genügt als erste
  Näherung; entscheidend ist die **Frage**, nicht die perfekte Antwort.

Das Ergebnis ist maschinell zu erzeugen, nicht abzutippen. `vendor/composer/installed.json`
und `custom/vendor/composer/installed.json` tragen alles Nötige.

### B — Die vier PHP-8-Blocker verifizieren
Der Story-Text nennt sie bereits; sie sind zu **prüfen**, nicht zu übernehmen:

| Paket | erwarteter `php`-Constraint | erwartete Benutzung |
|---|---|---|
| `ellumilel/php-excel-writer` | `^5.4\|^7.0` | nein |
| `twig/twig` | `^7.0` | nein |
| `ramsey/uuid` 3.8.0 | `^5.4 \|\| ^7.0` | **ja** — ID-Strategie `UUID` |
| `doctrine/orm` `dev-bugfix-many2many` | `^7.1` | **ja** |

Ebenso zu bestätigen: dass `silex/silex` und die Symfony-3.4-Komponenten **nicht** blockieren,
weil ihre `php`-Constraints nach oben offen sind. Der geänderte Zuschnitt von Epic `006` steht
auf dieser Aussage.

> Beim Schneiden dieser Story hat sich eine Annahme des Story-Texts bereits als falsch
> erwiesen (die „toten" SCSS-Importe). Die Tabelle oben ist deshalb eine **Erwartung**, kein
> Befund — Abweichungen sind festzuhalten, nicht wegzurunden.

### C — Die Geisterpakete
`vendor/scssphp` und `vendor/phpmailer` stehen in **keiner** `installed.json`, sind aber im
Autoloader registriert (`vendor/composer/autoload_psr4.php`, Zeilen 42 und 43). Sie wurden von
Hand hineinkopiert.

Zu erfassen ist, ob es noch weitere gibt: Ein Abgleich der Verzeichnisse unter `vendor/` und
`custom/vendor/` gegen die jeweilige `installed.json` beantwortet das vollständig — der
Story-Text nennt zwei, aber niemand hat bisher systematisch nachgesehen.

### D — Die Dubletten
Drei Pakete liegen in beiden Bäumen in inkompatiblen Majors:

| Paket | Root | `custom/` |
|---|---|---|
| `psr/log` | 1.1.3 | 3.0.2 |
| `symfony/polyfill-ctype` | v1.14.0 | v1.37.0 |
| `symfony/polyfill-mbstring` | v1.14.0 | v1.38.2 |

Dazu die **vierte, über den Autoloader**: `PHPMailer\PHPMailer\` ist in beiden
`autoload_psr4.php` registriert (Root Zeile 43, `custom/` Zeile 20). Der Root wird zuerst
geladen — also gewinnt die von Hand hineinkopierte Fassung über das gepflegte `^6.10` aus
`custom/`.

Auch hier gilt: Die Liste ist zu **verifizieren und zu vervollständigen**. Ein
Namensraum-Abgleich beider Autoloader findet jede weitere Überschneidung.

### E — Wo das Ergebnis hingehört
Eine Datei unter `an_project/docs/` — Name beim Umsetzen wählen, etwa
`abhaengigkeiten-inventar.md`. Sie ist die Grundlage für `006-001-0003` und `006-001-0004` und
bleibt danach als Beleg stehen: Epic `007` braucht sie, um Bestandsprojekten zu erklären, was
sich geändert hat.

## Abgrenzung
Keine Zuordnung, keine Entscheidung, kein Manifest. Wer beim Erfassen eine Meinung entwickelt,
notiert sie als Anmerkung — entschieden wird in `006-001-0004`.

## Acceptance criteria
- [ ] Eine Tabelle listet **alle** Pakete beider Bäume mit Version, `php`-Constraint,
      Herkunft (`installed.json` oder von Hand) und der Frage, ob sie im Code benutzt werden.
- [ ] Die Gesamtzahl ist genannt und stimmt mit den beiden `installed.json` plus den
      Geisterpaketen überein — 87 ist die Erwartung, nicht die Vorgabe.
- [ ] Die vier PHP-8-Blocker sind **verifiziert**; Abweichungen von der Erwartung sind
      festgehalten.
- [ ] Bestätigt oder widerlegt: `silex/silex` und die Symfony-3.4-Komponenten haben nach oben
      offene `php`-Constraints.
- [ ] Die Geisterpakete sind durch einen **systematischen** Abgleich ermittelt, nicht aus dem
      Story-Text übernommen.
- [ ] Die Dubletten sind durch einen Namensraum-Abgleich beider Autoloader vollständig
      erfasst.

## Verification
Die Zahlen müssen aus den Quelldateien reproduzierbar sein: Der Befehl, der die Tabelle
erzeugt hat, gehört ins Ergebnis — eine abgetippte Liste veraltet beim ersten
`composer update` und niemand merkt es.

Stichprobe gegen die Wirklichkeit: Für drei Pakete aus der Tabelle wird von Hand geprüft, dass
Version und Constraint mit dem `composer.json` im Verzeichnis übereinstimmen.
