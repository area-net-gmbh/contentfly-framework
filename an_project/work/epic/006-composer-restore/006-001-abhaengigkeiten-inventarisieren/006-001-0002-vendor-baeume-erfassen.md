---
id: 006-001-0002
title: Beide Vendor-Bäume vollständig erfassen
status: done
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
- [x] Eine Tabelle listet **alle** Pakete beider Bäume mit Version, `php`-Constraint,
      Herkunft (`installed.json` oder von Hand) und der Frage, ob sie im Code benutzt werden.
- [x] Die Gesamtzahl ist genannt und stimmt mit den beiden `installed.json` plus den
      Geisterpaketen überein — 87 ist die Erwartung, nicht die Vorgabe.
- [x] Die vier PHP-8-Blocker sind **verifiziert**; Abweichungen von der Erwartung sind
      festgehalten.
- [x] Bestätigt oder widerlegt: `silex/silex` und die Symfony-3.4-Komponenten haben nach oben
      offene `php`-Constraints.
- [x] Die Geisterpakete sind durch einen **systematischen** Abgleich ermittelt, nicht aus dem
      Story-Text übernommen.
- [x] Die Dubletten sind durch einen Namensraum-Abgleich beider Autoloader vollständig
      erfasst.

## Verification
Die Zahlen müssen aus den Quelldateien reproduzierbar sein: Der Befehl, der die Tabelle
erzeugt hat, gehört ins Ergebnis — eine abgetippte Liste veraltet beim ersten
`composer update` und niemand merkt es.

Stichprobe gegen die Wirklichkeit: Für drei Pakete aus der Tabelle wird von Hand geprüft, dass
Version und Constraint mit dem `composer.json` im Verzeichnis übereinstimmen.

## Ergebnis
`tools/dependency-inventory.php` erzeugt `an_project/docs/abhaengigkeiten-inventar.md`:

```sh
php tools/dependency-inventory.php > an_project/docs/abhaengigkeiten-inventar.md
```

Ein Skript statt einer abgetippten Liste, weil eine Tabelle im Fliesstext beim ersten
`composer update` veraltet und es niemand merkt.

### **89** Pakete, nicht 87
Der Story-Text zählte die beiden Geisterpakete nicht mit: 39 (Root `installed.json`) + **2**
(von Hand) + 48 (`custom/`). Die Erwartung war eine Erwartung, keine Vorgabe — genau deshalb
stand sie so im Task.

### Der grosse Befund: **20** Pakete mit oberer PHP-Grenze, nicht vier
Der Story-Text nannte vier. Tatsächlich cappt der **gesamte Doctrine-Baum** (11 Pakete) auf
`^7.1`, dazu fünf Symfony-Pakete auf `^7.1.3`, sowie `ellumilel`, `twig`, `ramsey/uuid` und
`paragonie/random_compat`.

Das berührt den Zuschnitt von Epic `006` unmittelbar — und die Antwort ist präziser als
gedacht:

**Die vier von Silex gepinnten Pakete sind nach oben offen** (`^5.5.9|>=7.0.8`):
`symfony/event-dispatcher`, `http-foundation`, `http-kernel`, `routing`. Silex selbst
verlangt nur `>=5.5.9`.

**Fünf weitere Symfony-Pakete im Baum sind es nicht** (`^7.1.3`): `console`, `contracts`,
`debug`, `translation`, `validator`. Sie hängen nicht an Silex.

Der Epic-Text warf beide Gruppen zusammen („die Symfony-3.4-Komponenten sind nach oben
offen"). Das gilt nur für die erste. Ob der Ist-Stack trotzdem auflösbar ist, entscheidet
`006-001-0003` mit einem echten Auflösungslauf — die Vorauswahl hier ist ein
Zeichenketten-Urteil, kein Composer-Urteil.

### Geisterpakete: die erwarteten zwei, systematisch bestätigt
`phpmailer/phpmailer` und `scssphp/scssphp` — ermittelt durch Verzeichnisabgleich gegen die
`installed.json`, nicht aus dem Story-Text übernommen. **Es gibt keine weiteren.**

### Dubletten: vier, davon zwei mit unterschiedlichem Major
| Paket | Root | `custom/` | gleicher Major |
|---|---|---|---|
| `phpmailer/phpmailer` | (unbekannt, von Hand) | v6.10.0 | **nein** |
| `psr/log` | 1.1.3 | 3.0.2 | **nein** |
| `symfony/polyfill-ctype` | v1.14.0 | v1.37.0 | ja |
| `symfony/polyfill-mbstring` | v1.14.0 | v1.38.2 | ja |

Der Namensraum-Abgleich beider Autoloader bestätigt genau diese vier als Kollisionen — **keine
weiteren**. Der Root wird zuerst geladen und gewinnt; bei `psr/log` heisst das 1.1.3 über
3.0.2, bei `phpmailer` die von Hand kopierte Fassung über das gepflegte `^6.10`.

### Die Heuristik lügt — und sagt es selbst
Die Spalte *benutzt?* ist eine `grep`-Näherung. Sie meldet `twig/twig` als **benutzt**, weil
das Wort „Twig" in einem Kommentar von `Command/InstallCommand.php` steht. Das Dokument
benennt diesen Fehlalarm ausdrücklich: Ein *ja* heisst „nachsehen", ein *—* ist die
belastbarere Aussage.

Nicht wegoptimiert, weil der Task es so vorgibt: „entscheidend ist die **Frage**, nicht die
perfekte Antwort". Die genaue Prüfung leistet `006-001-0004` für die Fälle, auf die es ankommt.

### Verifikation
Stichprobe gegen die Quelldateien, drei Pakete:

| Paket | Inventar | `vendor/<paket>/composer.json` |
|---|---|---|
| `doctrine/orm` | `dev-bugfix-many2many`, `^7.1` | `^7.1` ✓ |
| `ramsey/uuid` | `3.8.0`, `^5.4 \|\| ^7.0` | `^5.4 \|\| ^7.0` ✓ |
| `silex/silex` | `v2.2.2`, `>=5.5.9` | `>=5.5.9` ✓ |

Die Gesamtzahl geht auf: 39 + 2 + 48 = 89.
