---
id: 015-000-0005
title: Datei-ID aus dem Request wird zum Pfad — Upload und Löschen ausserhalb von data/files
status: review
depends_on: []
---

# Datei-ID aus dem Request wird zum Pfad — Upload und Löschen ausserhalb von data/files

## Context
**Security-Scan 2026-09, MEDIUM. Findings F8 und F21.** Beide haben dieselbe Ursache.

Die ID eines `PIM\File` kommt ungeprüft aus dem Request: das Feld `id` von `/file/upload`
(`Controller/FileController.php:212`, `$fileObject->setId(...)`) bzw. `data.id` von `/api/insert`.
Mit `DB_GUID_STRATEGY` (Vorgabe der Config) ist die Spalte ein freier String.
`FileSystem::getPath()` baut daraus `Paths::data().'/files/'.$file->getId()`. So entstehen zwei
Lücken:
- **F8, Schreiben:** `move_uploaded_file()` legt den Upload in dem Verzeichnis ab, auf das die ID
  zeigt, zum Beispiel `../cache/x`. `UploadValidator` sperrt ausführbare Endungen und Dotfiles,
  nicht aber das Verzeichnis. Ein erreichbares Ziel ist `data/cache`: Bei
  `APP_ENABLE_SCHEMA_CACHE` gibt `Api::getSchema()` die Schema-Cache-Datei dort an `unserialize()`
  (`Api.php:1565`).
- **F21, Löschen:** `Api::doDelete` (`Api.php:177`) löscht jede reguläre Datei in
  `getPath($object)` und danach das Verzeichnis. `FileController::overwriteAction` (Zeile 566) tut
  dasselbe mit dem Verzeichnis des Ziel-Datensatzes.

Die Panel-Stimmen haben F8 von HIGH auf MEDIUM gesenkt.

## Acceptance criteria
- [x] Eine Datei-ID wird nie aus dem Request übernommen, oder sie wird vor `setId()` gegen das strikte Format der gewählten ID-Strategie geprüft (UUID bzw. Ganzzahl).
- [x] `FileSystem::getPath()` lehnt IDs im falschen Format ab und stellt sicher, dass der aufgelöste Pfad unter `data/files` liegt.
- [x] Upload, Löschen und Overwrite mit einer ID wie `../cache/x` schlagen fehl, ohne ausserhalb von `data/files` etwas anzulegen oder zu löschen.
- [x] Optional, in einem eigenen Commit: Der Schema-Cache nutzt `unserialize(..., ['allowed_classes' => false])` oder JSON.

## Verification
Integrationstests: `/file/upload` mit `id = "../cache/probe"` legt keine Datei unter `data/cache`
an. Ein `PIM\File` mit einer ID ausserhalb von `data/files` lässt sich nicht anlegen, und
`/api/delete` darauf löscht nichts ausserhalb. Vor dem Fix sind beide Tests rot.
