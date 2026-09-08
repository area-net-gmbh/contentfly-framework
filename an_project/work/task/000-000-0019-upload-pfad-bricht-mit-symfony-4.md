---
id: 000-000-0019
title: Der Upload-Pfad bricht mit Symfony 4
status: todo
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
- [ ] `FileController::uploadAction()` liest die Datei über die `UploadedFile`-API.
- [ ] Weitere Stellen mit demselben Muster sind gesucht und behandelt.
- [ ] Die sechs Tests in `FileApiTest`, die heute an diesem Bruch scheitern, sind grün.
- [ ] Der vorhersagende Testkommentar ist umgeschrieben: aus der Warnung wird die Notiz,
      dass der Fall eingetreten und behoben ist.

## Verification
`FileApiTest` vollständig grün gegen den neuen Baum. Zusätzlich ein Upload von Hand mit einer
Datei, deren Name Umlaute und Leerzeichen enthält — `getClientOriginalName()` verhält sich
dort anders als der rohe `$_FILES`-Eintrag.
