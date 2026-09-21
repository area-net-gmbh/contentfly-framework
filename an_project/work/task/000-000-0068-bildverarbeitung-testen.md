---
id: 000-000-0068
title: Die Bildverarbeitung testen — sie verarbeitet hochgeladene Dateien
status: done
depends_on: []
---

# Die Bildverarbeitung testen — sie verarbeitet hochgeladene Dateien

## Context
**Gefunden bei der ersten Coverage-Messung (`000-000-0056`, 2026-09-18).** `Classes/File` ist zu
**23 %** abgedeckt: `Processing/Image.php` 4 % (198 von 206 Zeilen offen), `Processing/ImageMagick.php`
**0 %**. Die Bildverarbeitung erzeugt Vorschaubilder aus **hochgeladenen** Dateien — sie ist die
Stelle, an der fremde Eingabe einen Bild-Parser erreicht. Genau dort erwartet ein Prüfer Befunde.

## Acceptance criteria
- [x] Ein Integrationstest lädt ein Bild hoch und prüft die erzeugten Vorschaubilder (Grösse, Format) — für den GD-Weg und, wo die Erweiterung vorhanden ist, für ImageMagick.
- [x] Ein Test mit einer **manipulierten** Bilddatei (falsche Endung, beschädigter Kopf, übergrosse Abmessungen): Die Verarbeitung scheitert kontrolliert, ohne 500 und ohne liegengebliebene Dateien.
- [x] Entschieden und begründet, ob `ImageMagick.php` im Umfang bleibt — 0 % heisst auch: Niemand weiss, ob es noch funktioniert.

## Verification
Die neuen Tests, und der Coverage-Lauf aus `0056`.

## Ergebnis (2026-09-21)
**Die Bildverarbeitung hat jetzt 14 Integrationstests — und die zweite Hälfte hat drei Lücken
gezeigt, die vorher niemand gesehen hat, weil kein Test ein Bild hochlud.**

### Die Tests (`tests/Integration/Api/ImageProcessingApiTest.php`)
**Echte Bilder** — gezeichnet im Test mit GD, nicht eingecheckt: JPEG, PNG (Hochformat), GIF, ein
kleines Bild, das nicht vergrössert wird, die gespeicherten Abmessungen, eine auf Anfrage erzeugte
Variante über `/file/get`, eine responsive Einstellung (`2x@`/`1x@`) und `forceJpeg`. Geprüft wird
das Vorschaubild auf der Platte: Breite, Höhe, Format.

**Manipulierte Dateien** — jede muss mit 4xx scheitern und **nichts** hinterlassen (kein Datensatz,
kein Verzeichnis): Text als `image/jpeg`, JPEG-Kopf mit Müll dahinter, PNG als `image/jpeg`, ein
PNG-Kopf mit 50.000 × 50.000 Pixeln, ein PNG mit lesbarem Kopf und zerstörten Bilddaten.

### Drei Lücken, im selben Task geschlossen
| Fall | vorher | jetzt |
|---|---|---|
| Datei, die nur behauptet, ein Bild zu sein | **500**, Datensatz und Datei blieben liegen | **415** vor dem Speichern (`UploadValidator`) |
| Kopf mit 50.000 × 50.000 Pixeln | GD fordert ~10 GB an | **413** vor dem Dekodieren, neu `FILE_IMAGE_MAX_PIXELS` (24 MP) |
| Kopf lesbar, Daten kaputt | **500** (`imagesx(false)`) | **415**, der neue Datensatz samt Verzeichnis wird entfernt |

Dazu ein Fehler ohne Sicherheitsbezug, aber mit Wirkung: **Jeder GIF-Upload endete mit 500.**
`imagegif()` nimmt seit PHP 8 keine Qualität, der Prozessor übergab immer eine.

**Gegenprobe:** Ohne die Änderungen in `lib/` werden genau die sechs Tests rot, die diese Fälle
beschreiben. Zwei Einträge im Register.

**Offen, bewusst:** Der erneute Upload auf eine **bestehende** Id rollt eine Datei mit lesbarem Kopf
und kaputten Daten nicht zurück — die Kopf-Prüfungen greifen auch dort. 

**Nebenbei behoben:** Die responsiven Varianten und die Höhenskalierung rechneten mit Kommazahlen und
liessen GD abschneiden (199 statt 200 px) — mit einer Deprecation je Aufruf im Server-Log, die das
Gate aus `006-005` rot gemacht hätte, sobald ein Test den Weg erreicht. Jetzt gerundet.

### Coverage, neu gemessen (Lauf aus `0056`)
| | vorher | jetzt |
|---|---|---|
| `Classes/File` | 23 % | **70,1 %** (232 von 331 Zeilen) |
| `Processing/Image.php` | 4 % | **65,3 %** |
| `UploadValidator.php` | — | 98,6 % |

Offen in `Image.php`: der EXIF-Drehpfad (die CI hat keine `exif`-Erweiterung) und die
Prozent-Einstellung ohne Breite und Höhe. **Der Lauf brauchte eine Ausnahme:** `tests/Unit/Migration`
und `DeadImportTest` blieben draussen. `DeadImportTest` lädt per `class_exists()` die Rector-Klassen
aus `lib/contentfly/Migration/` und damit Rectors eigene Kopie von `nikic/php-parser`; sobald PHPUnit
für die Coverage die Projekt-Version braucht, prallen beide aufeinander — auch mit fester
Reihenfolge. Das ist `000-000-0071`.

### Entschieden: `ImageMagick.php` ist entfernt
- **Nicht lauffähig mit der Voreinstellung.** `is_executable('convert')` ist für einen relativen
  Namen immer falsch — wer den Prozessor einschaltete, bekam bei jedem Bild eine Exception.
- **Shell-Zeile ohne `escapeshellarg()`.** Der Dateiname ging in Anführungszeichen in `exec()`.
  Heute ungefährlich, weil der Upload Namen bereinigt (`000-000-0038`) — aber sicher nur durch eine
  Regel an anderer Stelle.
- **0 % Coverage, kein Nutzer im Framework oder in der Vorlage**, und GD deckt dieselben Formate ab.

Mit der Klasse fällt `IMAGEMAGICK_EXECUTABLE`. Die Config-Klasse erlaubt dynamische Properties; ein
Projekt, das den Schlüssel noch setzt, bricht nicht.

