---
id: 000-000-0019
title: Der Upload-Pfad bricht mit Symfony 4
status: review
depends_on: [006-002-0003]
---

# Der Upload-Pfad bricht mit Symfony 4

## Context
**Epic `008` hat diesen Bruch vorhergesagt.** Der Testkommentar in `FileApiTest`, geschrieben
mit `008-002`:

> Der Upload nimmt ein rohes `$_FILES`-Array entgegen, kein `UploadedFile`.
>
> Das funktioniert heute **zufällig**: PHP 8.1 ergänzt `$_FILES` um den Schlüssel
> `full_path`; die Erkennung in HttpFoundation 3.4 (`FileBag::$fileKeys`) vergleicht die
> Schlüssel exakt, scheitert daran und reicht das rohe Array durch — genau das, was
> `FileController::uploadAction()` mit `$file['name']` und `$file['tmp_name']` erwartet.
>
> Auf einem aktuellen Symfony liefert `$request->files->get()` ein `UploadedFile`, und der
> Array-Zugriff wird zum Fatal Error. Dieser Test hält fest, dass der Upload heute
> funktioniert — **schlägt er nach dem Kernel-Wechsel fehl, ist es genau diese Stelle.**

Mit `006-002-0003` (Symfony 3.4 → 4.4) ist er fehlgeschlagen. Wörtlich an dieser Stelle.

## Umfang
`FileController::uploadAction()` greift auf das Ergebnis von `$request->files->get()` als
Array zu. HttpFoundation 4.4 liefert dort ein `UploadedFile`-Objekt.

Zu ändern ist der Zugriff — `$file->getClientOriginalName()` statt `$file['name']`,
`$file->getPathname()` statt `$file['tmp_name']`. Zu prüfen ist, ob weitere Stellen dasselbe
Muster benutzen (`grep` über `$_FILES` und `files->get`).

**Der Umbau ist nicht auf 4.4 zu beschränken.** Epic `009` bringt Symfony 7.4; die neue
Fassung sollte gegen beide laufen oder wenigstens nicht wieder versionsabhängig sein.

## Abgrenzung
Keine Änderung an der Upload-Route, am Speicherort oder am Antwortformat. Nur der Zugriff auf
die hochgeladene Datei.

## Acceptance criteria
- [x] `FileController::uploadAction()` liest die Datei über die `UploadedFile`-API.
- [x] Weitere Stellen mit demselben Muster sind gesucht und behandelt.
- [x] Die sechs Tests in `FileApiTest`, die heute an diesem Bruch scheitern, sind grün.
- [x] Der vorhersagende Testkommentar ist umgeschrieben: aus der Warnung wird die Notiz,
      dass der Fall eingetreten und behoben ist.

## Verification
`FileApiTest` vollständig grün gegen den neuen Baum. Zusätzlich ein Upload von Hand mit einer
Datei, deren Name Umlaute und Leerzeichen enthält — `getClientOriginalName()` verhält sich
dort anders als der rohe `$_FILES`-Eintrag.

## Ergebnis
**Der Upload läuft wieder, und `FileApiTest` ist vollständig grün.** Die Suite geht von 7 auf
**1** verbleibende Failure — die gehört zu `000-000-0020`.

### Genau eine Stelle im Anwendungscode
`grep` über `lib/`, `custom/`, `bin/` nach `files->get` und `$_FILES`: **ein** Treffer,
`FileController.php:53`. Dahinter aber **17 Array-Zugriffe** auf vier Schlüssel, verteilt über
beide Zweige von `uploadAction()`.

Umgebaut wurde nicht Zugriff für Zugriff, sondern einmal am Kopf:

```php
$uploadName    = $file->getClientOriginalName();   // <- $_FILES['name']
$uploadTmpPath = $file->getPathname();             // <- $_FILES['tmp_name']
$uploadType    = $file->getClientMimeType();       // <- $_FILES['type']
$uploadSize    = $file->getSize();                 // <- $_FILES['size']
```

Der Rumpf sieht danach **kein Symfony mehr**. Das ist der Punkt, den der Task verlangt hat: Er
ist beim Kernel-Tausch (Epic `009`) nicht wieder die Stelle, die bricht.

**`getClientMimeType()` und nicht `getMimeType()`.** Letzteres rät den Typ aus dem Inhalt und
lieferte damit etwas anderes als bisher. `$_FILES['type']` ist die Angabe des Clients — die
Zuordnung ist wortgetreu, damit sich das Verhalten nicht nebenbei ändert. Bei einem
Charakterisierungstest ist das kein Detail.

### Ein Befund: `006-002-0006` hat eine Erwartung an einen Defekt angepasst
Nach dem Upload-Fix waren **zwei** Tests rot, die es vorher nicht waren:

```
testAuslieferungAntwortetMitRedirectAufDieDatei   Failed asserting that 301 is identical to 302
testAuslieferungBrauchtKeinenToken                Failed asserting that 301 is identical to 302
```

Nachgesehen statt angepasst, und die Historie ist eindeutig:

| | |
|---|---|
| `getAction()` ruft `redirect($redirectUri, 301)` | seit `b928409`, dem **initialen Import** |
| README beschreibt „HTTP-Redirects (301)" | seit demselben Commit |
| Test-Zusicherung `301` → `302` geändert | `c1d1706` — Task `006-002-0006` |

`006-002-0006` hiess „Testerwartungen an den neuen Stack anpassen". Zu diesem Zeitpunkt war der
Upload durch **genau den Bruch, den dieser Task behebt**, bereits kaputt: `data.id` kam als
`null` zurück, der Aufruf ging an `/file/get/` **ohne Id**, und dort antwortet nicht die
Auslieferung, sondern die WEB_ROOT-Umleitung auf `/` — mit 302.

Gemessen wurde also ein **Symptom des Upload-Defekts** und als neues Symfony-4.4-Verhalten
festgeschrieben. Beide Zusicherungen stehen wieder auf 301, mit der Begründung im Test.

`testAuslieferungBrauchtKeinenToken` war deshalb auch nie wirklich grün: Er lief gegen den
Umleitungspfad, nicht gegen die Auslieferung. Er zählte nicht zu den sechs bekannten Failures
und wurde trotzdem hier mit repariert — sonst hätte dieser Task einen grünen Test rot
hinterlassen.

**Das ist die Illustration zu der Regel aus `technical.md`:** *Eine Testanpassung ist ein
Verhaltenswechsel und braucht eine Begründung — sie ist kein Wartungsschritt.* Wird sie
vorgenommen, um einen roten Test grün zu bekommen, schreibt sie den Defekt fest. Der Hinweis
steht jetzt als Kommentar am Test, nicht nur hier.

### Der umbenannte Test
`testUploadFunktioniertUeberDenRohenFilesArrayPfad` beschrieb einen Mechanismus, den es nicht
mehr gibt. Er heisst jetzt `testUploadUebernimmtDieGemeldeteDateigroesse` — nach dem, was er
prüft. **Seine beiden Zusicherungen sind unverändert**; geändert sind Name und Kommentar, und
der Kommentar hält fest, dass der vorhergesagte Fall eingetreten und behoben ist.

### Verification
| Prüfung | Ergebnis |
|---|---|
| `FileApiTest` | **OK (10 tests, 20 assertions)** |
| volle Suite mit `CI=true` | **249 Tests / 605 Assertions, 1 Failure**, 0 übersprungen |
| verbleibende Failure | `RouteSecurityApiTest` — `000-000-0020` |

**Upload von Hand mit Umlauten und Leerzeichen**, wie der Task es verlangt:

| | |
|---|---|
| gesendeter Name | `Grüße Übung Prüfbericht.txt` |
| gespeicherter Name | `grüße-übung-prüfbericht.txt` |
| `size` · `type` | 24 · `text/plain` |
| Inhalt auf der Platte | byte-gleich |

`getClientOriginalName()` liefert den Namen unverändert; die Bereinigung macht wie bisher
`sanitizeFileName()`.

### Ein Fehlgriff beim Aufräumen
Beim manuellen Test habe ich `rm -rf "data/files/$ID"` ausgeführt, während `$ID` **leer** war —
ein vorangegangener Shell-Aufruf war an den Umlauten gescheitert und hatte nichts geliefert.
Der Pfad wurde damit zu `data/files/`, und das Verzeichnis war weg.

Aufgefallen am nächsten Upload (`mkdir(): No such file or directory`), wiederhergestellt mit
`git checkout HEAD -- data/files/.gitkeep` — das Verzeichnis liegt versioniert im Repo, genau
für diesen Zweck. Kein Datenverlust: Es enthielt nur Testdateien der laufenden Wegwerf-Instanz.

Die Lehre ist nicht neu, aber sie gehört aufgeschrieben: Eine leere Variable in einem
`rm -rf`-Pfad löscht das Elternverzeichnis. Wer den Wert nicht selbst gesetzt hat, prüft ihn.
